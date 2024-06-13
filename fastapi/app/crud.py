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
    remove_group_member,
    db_delete_group,
    get_group_lock,
)

def create_group(name: str, creator_email: str, members: List[str]) -> Group:
    group_id = str(uuid.uuid4())
    logger.info(f"Creating group with ID: {group_id}, name: {name}, creator: {creator_email}, members: {members}")
    group = Group(id=group_id, name=name, creator_email=creator_email, members=[creator_email] + members, entries=[], balances=[])

    db_save_group(group.dict())

    for member in [creator_email] + members:
        user_data = db_load_user_data(member)
        if "groups" not in user_data:
            user_data["groups"] = []
        user_data["groups"].append(group_id)
        db_save_user_data(user_data)

    return group

def get_group_details(group_id: str) -> Group:
    group_data = db_get_group_details(group_id)
    if not group_data:
        logger.error(f"Group not found: {group_id}")
        return None
    return Group(**group_data)

def get_group_details_by_name(name: str, email: str) -> Group:
    user_data = db_load_user_data(email)
    for group_id in user_data.get("groups", []):
        group_data = db_get_group_details(group_id)
        if group_data and group_data.get('name') == name:
            return Group(**group_data)
    logger.error(f"Group not found: {name} for user {email}")
    return None

def add_expense(group_id: str, expense_data: schemas.ExpenseCreate, added_by: str) -> Expense:
    lock = get_group_lock(group_id)
    with lock:
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
            paid_by=expense_data.paid_by,
            shares=filtered_shares,
            date=str(datetime.now()),
            added_by=added_by
        )
        append_group_entry(group_id, expense.dict())

        # Update balances in-memory
        update_balances(group, expense)

        # Save the updated balances only
        update_group_balances(group_id, group.balances)


def add_payment(group_id: str, payment_data: schemas.PaymentCreate, added_by: str) -> Payment:
    lock = get_group_lock(group_id)
    with lock:
        group = get_group_details(group_id)
        if not group:
            logger.error(f"Adding payment failed: Group {group_id} not found.")
            return None

        payment = Payment(
            type="settlement",
            description=payment_data.description,
            amount=payment_data.amount,
            paid_by=payment_data.paid_by,
            paid_to=payment_data.paid_to,
            date=str(datetime.now()),
            added_by=added_by
        )
        append_group_entry(group_id, payment.dict())

        # Update balances in-memory
        update_balances(group, payment)

        # Save the updated balances only
        update_group_balances(group_id, group.balances)


def update_balances(group: Group, entry: Dict):
    transactions = [(payer, payee, amount) for payee, payer, amount in group.balances]  # Read in reversed order

    if entry.type == "expense":
        payer = entry.paid_by
        shares = entry.shares
        for member, share in shares.items():
            if member != payer:
                transactions.append((payer, member, share))
    elif entry.type == "settlement":
        payer = entry.paid_by
        payee = entry.paid_to
        amount = entry.amount
        transactions.append((payer, payee, amount))

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
    lock = get_group_lock(group_id)

    with lock:
        # Remove user from group members
        remove_group_member(group_id, user_email)

        # Remove group from user's document
        user_data = db_load_user_data(user_email)
        user_data["groups"].remove(group_id)
        db_save_user_data(user_data)

        # If no members left in group, delete the group document
        if not group.members:
            logger.info(f"Deleting group {group_name} as it has no members left.")
            db_delete_group(group_id)