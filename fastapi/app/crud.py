from typing import List, Dict, Union
import decimal
import time ,random
from cachetools import LRUCache
from datetime import datetime, timezone
import uuid
import heapq
from app.models import Group, Expense, Payment
from app import schemas
from app.logging_config import logger
from app.db_handler import (
    load_user_data as db_load_user_data,
    save_user_data as db_save_user_data,
    save_group as db_save_group,
    get_group_details as db_get_group_details,
    get_group_minimal_details as db_get_group_minimal_details,
    get_group_id_by_name as db_get_group_id_by_name,
    update_group_balances,
    append_group_entry,
    mark_entry_cancelled,
    get_group_lock,
    release_group_lock,
    update_group_data,
    get_entry_by_index as db_get_entry_by_index,
    group_locks,
    user_locks
)

# Global cache for the number of entries
entries_cache = LRUCache(maxsize=5000)

def get_number_of_entries(group_id: str) -> int:
    if group_id in entries_cache:
        return entries_cache[group_id]
    
    group = db_get_group_details(group_id)
    if group:
        entries_cache[group_id] = len(group["entries"])
        return entries_cache[group_id]
    
    return 0

def format_datetime(dt: datetime) -> str:
    # Convert to UTC and round microseconds to two decimal places
    dt = dt.replace(tzinfo=timezone.utc)
    rounded_seconds = round(dt.second + dt.microsecond / 1_000_000, 2)
    return dt.strftime('%Y-%m-%d %H:%M:') + f'{int(dt.minute):02}:{rounded_seconds:05.2f}'

def generate_simple_id():
    # Use UTC time (integer part only)
    utc_time = int(time.time())
    # Convert to a hex string (remove '0x' and ensure consistent length)
    time_part = hex(utc_time)[2:]
    # Generate a random 32-bit hex string
    random_part = hex(random.getrandbits(32))[2:]
    # Ensure the random part is of consistent length
    random_part = random_part.zfill(8)
    # Combine time and random parts
    return f"{time_part}{random_part}"

def create_group(name: str, creator_email: str, members: List[str], local_currency: str) -> Group:
    group_id = str(generate_simple_id())
    with group_locks[group_id]:
        logger.info(f"Creating group with ID: {group_id}, name: {name}, creator: {creator_email}, members: {members}")
        group = Group(id=group_id, name=name, creator_email=creator_email, members=[creator_email] + members, local_currency=local_currency, entries=[], balances=[], currency_conversion_rates={})

        db_save_group(group.dict())

        for member in [creator_email] + members:
            with user_locks[member]:
                user_data = db_load_user_data(member)
                if "groups" not in user_data:
                    user_data["groups"] = []
                user_data["groups"].append(group_id)
                db_save_user_data(user_data)

        return group

def add_currency(group_id: str, currency: str, conversion_rate: float):
    with group_locks[group_id]:
        group = db_get_group_minimal_details(group_id)
        if not group:
            logger.error(f"Adding currency failed: Group {group_id} not found.")
            return None

        group["currency_conversion_rates"][currency] = conversion_rate
        update_group_data(group_id, {"currency_conversion_rates": group["currency_conversion_rates"]})


def get_group_minimal_details(group_id: str) -> dict:
    group_data = db_get_group_minimal_details(group_id)
    if not group_data:
        logger.error(f"Group minimal details not found: {group_id}")
        return None
    return group_data

def get_group_details_by_name(name: str, email: str) -> Group:
    group_id = db_get_group_id_by_name(name, email)
    with group_locks[group_id]:
        group_data = db_get_group_details(group_id)
        found_group = Group(**group_data)
        entries_cache[group_id] = len(group_data["entries"])
        return found_group

def add_expense(group_name: str, email: str, expense_data: schemas.ExpenseCreate) -> Expense:
    group_id = db_get_group_id_by_name(group_name, email)
    if not group_id:
        logger.error(f"Adding expense failed: Group {group_name} not found for user {email}.")
        return None

    with group_locks[group_id]:
        group = db_get_group_minimal_details(group_id)
        if not group:
            logger.error(f"Adding expense failed: Group {group_id} not found.")
            return None

        # Calculate total amount spent in local currency
        conversion_rate = group["currency_conversion_rates"].get(expense_data.currency, 1)

        expense = Expense(
            type="expense",
            description=expense_data.description,
            amount=expense_data.amount,
            currency=expense_data.currency,
            paid_by=expense_data.paid_by,
            shares=expense_data.shares,
            date=str(format_datetime(datetime.now())),
            added_by=email,
            cancelled=False
        )

        append_group_entry(group_id, expense.dict())

        # Update spends
        for member, share in expense_data.shares.items():
            share_in_local_currency = share * conversion_rate
            for spend in group["spends"]:
                if spend["member"] == member:
                    spend["amount"] += share_in_local_currency
                    break
            else:
                group["spends"].append({"member": member, "amount": share_in_local_currency})

        update_group_data(group_id, {"spends": group["spends"]})
        update_balances(group, expense)
        update_group_balances(group_id, group["balances"])
        logger.info("Updated balances after adding expense")

def add_payment(group_name: str, email: str, payment_data: schemas.PaymentCreate) -> Payment:
    group_id = db_get_group_id_by_name(group_name, email)
    if not group_id:
        logger.error(f"Adding payment failed: Group {group_name} not found for user {email}.")
        return None

    with group_locks[group_id]:
        group = db_get_group_minimal_details(group_id)
        if not group:
            logger.error(f"Adding payment failed: Group {group_id} not found.")
            return None

        payment = Payment(
            type="settlement",
            description=payment_data.description,
            amount=payment_data.amount,
            currency=payment_data.currency,
            paid_by=payment_data.paid_by,
            paid_to=payment_data.paid_to,
            date=str(format_datetime(datetime.now())),
            added_by=email,
            cancelled=False
        )
        
        append_group_entry(group_id, payment.dict())
        # Update balances in-memory
        update_balances(group, payment)

        # Save the updated balances only
        update_group_balances(group_id, group["balances"])
        logger.info("Updated balances after adding payment")

def get_entry_by_time(group_id: str, target_datetime: str) -> dict:
    low, high = 0, get_number_of_entries(group_id) - 1

    while low <= high:
        mid = (low + high) // 2
        mid_entry = db_get_entry_by_index(group_id, mid)

        if not mid_entry:
            break  # If mid_entry is not found, exit the loop

        mid_datetime = mid_entry["date"]

        if mid_datetime == target_datetime:
            return mid_entry, mid
        elif mid_datetime < target_datetime:
            low = mid + 1
        else:
            high = mid - 1

    return None, None


def remove_expense(group_name: str, email: str, expense_datetime: str):
    group_id = db_get_group_id_by_name(group_name, email)
    if not group_id:
        logger.error(f"Removing expense failed: Group {group_name} not found for user {email}.")
        return None

    with group_locks[group_id]:
        entry, ind = get_entry_by_time(group_id, expense_datetime)
        if not entry:
            logger.error(f"Removing expense failed: Entry with {expense_datetime} not found.")
            return None

        if entry["cancelled"]:
            logger.error(f"Removing expense failed: Entry {expense_datetime} already cancelled.")
            return None
        
        group = db_get_group_minimal_details(group_id)
        conversion_rate = group["currency_conversion_rates"].get(entry["currency"], 1)
        transactions = []
        # Add existing balances to transactions
        for balance in group["balances"]:
            transactions.append((balance[1], balance[0], balance[2]))
        for member, share in entry["shares"].items():
            share_in_local_currency = share * conversion_rate
            if member != entry["paid_by"]:
                transactions.append((member, entry["paid_by"], share_in_local_currency))

        # Simplify debts to update balances
        simplified_balances = simplify_debts(transactions)
        group["balances"] = [[payer, payee, amount] for payer, payee, amount in simplified_balances]
        update_group_balances(group_id, group["balances"])
        # Mark the expense as cancelled and save to DB
        
        for member, share in entry["shares"].items():
            share_in_local_currency = share * conversion_rate
            for spend in group["spends"]:
                 if spend["member"] == member:
                    spend["amount"] -= share_in_local_currency
                    break

        update_group_data(group_id, {"spends": group["spends"]})
        
        mark_entry_cancelled(group_id, ind)

        cancellation_entry = Expense(
            type="expense",
            description=f"Expense {entry["description"]} on {entry["date"]} was cancelled",
            amount=entry["amount"],
            currency="N.A",
            paid_by="N.A",
            shares={},
            date=str(format_datetime(datetime.now())),
            added_by=email,
            cancelled=False
        )
        append_group_entry(group_id, cancellation_entry.dict())
        logger.info(f'Group {group_id} Balances after revert {group["balances"]}')

def update_balances(group, new_entry: Union[Expense, Payment, None] = None):
    logger.info("Updating balances")
    transactions = []

    # Add all existing balances
    for balance in group["balances"]:
        transactions.append((balance[1], balance[0], balance[2]))

    # Add new entry if provided
    if new_entry:
        if new_entry.type == "expense":
            payer = new_entry.paid_by
            shares = new_entry.shares
            for member, share in shares.items():
                if member != payer:
                    # Convert shares to local currency
                    conversion_rate = group["currency_conversion_rates"].get(new_entry.currency, 1)
                    transactions.append((payer, member, share * conversion_rate))
        elif new_entry.type == "settlement":
            payer = new_entry.paid_by
            payee = new_entry.paid_to
            amount = new_entry.amount
            # Convert amount to local currency
            conversion_rate = group["currency_conversion_rates"].get(new_entry.currency, 1)
            transactions.append((payer, payee, amount * conversion_rate))

    # Simplify debts
    simplified_balances = simplify_debts(transactions)

    group["balances"] = [[payer, payee, amount] for payer, payee, amount in simplified_balances]
    logger.info(f"Balances updated !") 

def simplify_debts(transactions):
    logger.info("Simplifying ")
    # Step 1: Calculate net balances
    balances = {}
    for transaction in transactions:
        debtor, creditor, amount = transaction
        balances[debtor] = balances.get(debtor, 0) - amount
        balances[creditor] = balances.get(creditor, 0) + amount

    # Step 2: Separate creditors and debtors and build heaps
    debtors = []
    creditors = []
    for person, balance in balances.items():
        if balance < 0:
            heapq.heappush(debtors, (balance, person))  # min-heap for debtors
        elif balance > 0:
            heapq.heappush(creditors, (-balance, person))  # max-heap for creditors (negative values to simulate max-heap)

    simplified_transactions = []

    # Step 3: Match debts and credits
    while debtors and creditors:
        debt_balance, debtor = heapq.heappop(debtors)
        credit_balance, creditor = heapq.heappop(creditors)
        credit_balance = -credit_balance  # convert back to positive

        settlement_amount = min(-debt_balance, credit_balance)
        simplified_transactions.append((creditor, debtor, settlement_amount))

        new_debt_balance = debt_balance + settlement_amount
        new_credit_balance = credit_balance - settlement_amount

        if new_debt_balance < 0:
            heapq.heappush(debtors, (new_debt_balance, debtor))
        if new_credit_balance > 0:
            heapq.heappush(creditors, (-new_credit_balance, creditor))
    logger.info("Simplification completed")
    return simplified_transactions

def get_groups_by_user_email(email: str) -> List[str]:
    with user_locks[email]:
        user_data = db_load_user_data(email)
    group_names = []
    for group_id in user_data.get("groups", []):
        with group_locks[group_id]:
            group_data = db_get_group_minimal_details(group_id)
        if group_data:
            group_names.append(group_data["name"])
    return group_names

def delete_group(group_name: str, user_email: str):
    group = get_group_details_by_name(group_name, user_email)
    if not group:
        logger.error(f"Delete group failed: Group {group_name} not found for user {user_email}")
        return

    group_id = group.id
    user_data = db_load_user_data(user_email)
    user_data["groups"].remove(group_id)
    db_save_user_data(user_data)

def add_user_to_group(group_id: str, new_member_email: str):
    with user_locks[new_member_email]:
        user_data = db_load_user_data(new_member_email)
        if "groups" not in user_data:
            logger.info("Adding new user")
            user_data["groups"] = []
        if group_id not in user_data["groups"]:
            user_data["groups"].append(group_id)
        db_save_user_data(user_data)

    with group_locks[group_id]:
        group = db_get_group_minimal_details(group_id)
        if new_member_email in group["members"]:
            logger.warning(f"User {new_member_email} is already a member of group {group_id}")
            return

        group["members"].append(new_member_email)
        group["spends"].append({"member": new_member_email, "amount": 0.0})
        update_group_data(group_id, {"members": group["members"], "spends": group["spends"]})
        logger.info(f"User {new_member_email} added to group {group_id}")
