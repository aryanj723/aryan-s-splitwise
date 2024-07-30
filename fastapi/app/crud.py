from typing import List, Dict, Union
from datetime import datetime
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
    update_group_balances,
    append_group_entry,
    mark_entry_cancelled,
    get_group_lock,
    release_group_lock,
    update_group_data,
    group_locks
)

def create_group(name: str, creator_email: str, members: List[str], local_currency: str) -> Group:
    group_id = str(uuid.uuid4())
    logger.info(f"Creating group with ID: {group_id}, name: {name}, creator: {creator_email}, members: {members}")
    group = Group(id=group_id, name=name, creator_email=creator_email, members=[creator_email] + members, local_currency=local_currency, entries=[], balances=[], currency_conversion_rates={})

    db_save_group(group.dict())

    for member in [creator_email] + members:
        user_data = db_load_user_data(member)
        if "groups" not in user_data:
            user_data["groups"] = []
        user_data["groups"].append(group_id)
        db_save_user_data(user_data)

    return group

def add_currency(group_id: str, currency: str, conversion_rate: float):
    if not get_group_lock(group_id):
        logger.error(f"Adding currency failed: Could not acquire lock for group {group_id}.")
        return None
    
    try:
        group = get_group_details(group_id)
        if not group:
            logger.error(f"Adding currency failed: Group {group_id} not found.")
            return None

        group.currency_conversion_rates[currency] = conversion_rate
        update_group_data(group_id, {"currency_conversion_rates": group.currency_conversion_rates})
    
    finally:
        release_group_lock(group_id)

def get_group_details(group_id: str) -> Group:
    group_data = db_get_group_details(group_id)
    if not group_data:
        logger.error(f"Group not found: {group_id}")
        return None
    return Group(**group_data)

def get_group_details_by_name(name: str, email: str) -> Group:
    user_data = db_load_user_data(email)
    found_group = None
    for group_id in user_data.get("groups", []):
        if not get_group_lock(group_id):
            logger.warning(f"Could not acquire lock for group {group_id}, skipping.")
            continue
        try:
            logger.info("Getting group info now")
            group_data = db_get_group_details(group_id)
            if group_data and group_data.get('name') == name:
                found_group = Group(**group_data)
                break
        finally:
            release_group_lock(group_id)
    if not found_group:
        logger.error(f"Group not found: {name} for user {email}")
    return found_group

def add_expense(group_id: str, expense_data: schemas.ExpenseCreate, added_by: str) -> Expense:
    with group_locks[group_id]:
        group = get_group_details(group_id)
        if not group:
            logger.error(f"Adding expense failed: Group {group_id} not found.")
            return None

        # Filter out zero shares
        filtered_shares = {k: v for k, v in expense_data.shares.items() if v != 0}

        expense = Expense(
            type="expense",
            description=expense_data.description,
            amount=expense_data.amount,
            currency=expense_data.currency,
            paid_by=expense_data.paid_by,
            shares=filtered_shares,
            date=str(datetime.now()),
            added_by=added_by,
            cancelled=False
        )
        
        append_group_entry(group_id, expense.dict())

    if not get_group_lock(group_id):
        logger.error(f"Adding expense failed: Could not acquire lock for group {group_id}.")
        return None
    
    try:
        # Update balances in-memory
        update_balances(group, expense)

        # Save the updated balances only
        update_group_balances(group_id, group.balances)
        logger.info("Updated balances after adding expense")
    finally:
        release_group_lock(group_id)

def add_payment(group_id: str, payment_data: schemas.PaymentCreate, added_by: str) -> Payment:
    with group_locks[group_id]:
        group = get_group_details(group_id)
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
            date=str(datetime.now()),
            added_by=added_by,
            cancelled=False
        )
        
        append_group_entry(group_id, payment.dict())
    if not get_group_lock(group_id):
        logger.error(f"Adding payment failed: Could not acquire lock for group {group_id}.")
        return None
    
    try:
        # Update balances in-memory
        update_balances(group, payment)

        # Save the updated balances only
        update_group_balances(group_id, group.balances)
        logger.info("Updated balances after adding payment")
    finally:
        release_group_lock(group_id)

def remove_expense(group_id: str, expense_index: int, cancelled_by: str):
    if not get_group_lock(group_id):
        logger.error(f"Removing expense failed: Could not acquire lock for group {group_id}.")
        return None
    
    try:
        group = get_group_details(group_id)
        if not group:
            logger.error(f"Removing expense failed: Group {group_id} not found.")
            return None

        if expense_index >= len(group.entries):
            logger.error(f"Removing expense failed: Expense index {expense_index} out of range.")
            return None

        expense = group.entries[expense_index-1]
        logger.info(f"Removing expense {expense} from group")
        if expense.cancelled:
            logger.error(f"Removing expense failed: Expense {expense_index} already cancelled.")
            return None

        # Mark the expense as cancelled and save to DB
        mark_entry_cancelled(group_id, expense_index - 1)
        
        calcellation_entry = Expense(
            type="expense",
            description=f"Expense entry {expense_index} was cancelled",
            amount=0,
            currency="N.A",
            paid_by="",
            shares={},
            date=str(datetime.now()),
            added_by=cancelled_by,
            cancelled=False
        )
        append_group_entry(group_id, calcellation_entry.dict())

        # Revert the cancelled expense's impact on balances
        revert_expense_balances(group, expense)

        # Save the updated entries and balances
        update_group_balances(group_id, group.balances)
    
    finally:
        release_group_lock(group_id)


def revert_expense_balances(group, expense):
    if expense.type != "expense":
        raise ValueError("Only expenses can be reverted")

    transactions = []

    # Add all existing balances to transactions
    for balance in group.balances:
        transactions.append((balance[1], balance[0], balance[2]))

    # Revert the cancelled expense's impact
    payer = expense.paid_by
    shares = expense.shares
    expense_currency = expense.currency
    conversion_rate = group.currency_conversion_rates.get(expense_currency, 1)  # Default to 1 if no rate is found

    for member, share in shares.items():
        if member != payer:
            # Convert share to local currency if necessary
            amount_in_local_currency = share * conversion_rate
            transactions.append((member, payer, amount_in_local_currency))

    # Simplify debts to update balances
    simplified_balances = simplify_debts(transactions)
    group.balances = [[payer, payee, amount] for payer, payee, amount in simplified_balances]

def update_balances(group: Group, new_entry: Union[Expense, Payment, None] = None):
    transactions = []

    # Add all existing balances
    for balance in group.balances:
        transactions.append((balance[1], balance[0], balance[2]))

    # Add new entry if provided
    if new_entry:
        if new_entry.type == "expense":
            payer = new_entry.paid_by
            shares = new_entry.shares
            for member, share in shares.items():
                if member != payer:
                    # Convert shares to local currency
                    conversion_rate = group.currency_conversion_rates.get(new_entry.currency, 1)
                    transactions.append((payer, member, share * conversion_rate))
        elif new_entry.type == "settlement":
            payer = new_entry.paid_by
            payee = new_entry.paid_to
            amount = new_entry.amount
            # Convert amount to local currency
            conversion_rate = group.currency_conversion_rates.get(new_entry.currency, 1)
            transactions.append((payer, payee, amount * conversion_rate))

    # Simplify debts
    simplified_balances = simplify_debts(transactions)

    group.balances = [[payer, payee, amount] for payer, payee, amount in simplified_balances]  # Write in correct order

def simplify_debts(transactions):
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

    return simplified_transactions

def get_groups_by_user_email(email: str) -> List[str]:
    user_data = db_load_user_data(email)
    group_names = []
    for group_id in user_data.get("groups", []):
        group_data = db_get_group_details(group_id)
        if group_data:
            group = Group(**group_data)
            group_names.append(group.name)
    return group_names

def delete_group(group_name: str, user_email: str):
    group = get_group_details_by_name(group_name, user_email)
    if not group:
        logger.error(f"Delete group failed: Group {group_name} not found for user {user_email}")
        return

    group_id = group.id
    if not get_group_lock(group_id):
        logger.error(f"Deleting group failed: Could not acquire lock for group {group_id}.")
        return
    
    try:
        # Remove user from group members
        #remove_group_member(group_id, user_email)

        # Remove group from user's document
        user_data = db_load_user_data(user_email)
        user_data["groups"].remove(group_id)
        db_save_user_data(user_data)

        # If no members left in group, delete the group document
        # if not group.members:
        #     logger.info(f"Deleting group {group_name} as it has no members left.")
        #     db_delete_group(group_id)
    
    finally:
        release_group_lock(group_id)

def add_user_to_group(group_id: str, new_member_email: str):
    if not get_group_lock(group_id):
        logger.error(f"Adding user failed Could not acquire lock for group {group_id}.")
        return
    
    try:
        group_data = db_get_group_details(group_id)
        group = Group(**group_data)
        if new_member_email in group.members:
            logger.warning(f"User {new_member_email} is already a member of group {group_id}")
            return
        
        # Add new member to the group
        group.members.append(new_member_email)
        update_group_data(group_id, {"members": group.members})
        logger.info(f"User {new_member_email} added to group {group_id}")

        # Ensure the user exists in the user collection
        user_data = db_load_user_data(new_member_email)
        if "groups" not in user_data:
            user_data["groups"] = []
        if group_id not in user_data["groups"]:
            user_data["groups"].append(group_id)
        db_save_user_data(user_data)
        logger.info(f"User {new_member_email} updated with group {group_id}")
    
    finally:
        release_group_lock(group_id)
