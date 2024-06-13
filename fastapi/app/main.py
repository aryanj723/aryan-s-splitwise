from fastapi import FastAPI, HTTPException, Body, BackgroundTasks
from pydantic import BaseModel
from app import crud, schemas
from app.logging_config import logger
from datetime import datetime

app = FastAPI()

@app.on_event("startup")
def on_startup():
    logger.info("Application startup: initializing the system.")

class AddExpenseRequest(BaseModel):
    name: str
    email: str
    expense: schemas.ExpenseCreate

class AddPaymentRequest(BaseModel):
    name: str
    email: str
    payment: schemas.PaymentCreate

class UserBalanceRequest(BaseModel):
    group_name: str
    user_email: str

class EmailRequest(BaseModel):
    email: str

class GroupNameRequest(BaseModel):
    name: str
    email: str

class GetBalancesRequest(BaseModel):
    group_id: str

@app.post("/groups/create")
async def create_group(background_tasks: BackgroundTasks, group: schemas.GroupCreate = Body(...)):
    logger.info(f"Received request to create group: {group}")

    for member in [group.creator_email] + group.members:
        user_data = crud.db_load_user_data(member)
        if "groups" not in user_data:
            user_data["groups"] = []
        for group_id in user_data["groups"]:
            existing_group = crud.db_get_group_details(group_id)
            if existing_group and existing_group.get('name') == group.name:
                logger.error(f"Group creation failed: Member {member} is already part of a group with the name {group.name}.")
                raise HTTPException(status_code=400, detail="Group creation failed: One or more members are already part of a group with the same name. Please try with a different name.")
    
    created_group = crud.create_group(group.name, group.creator_email, group.members)
    if not created_group:
        logger.error(f"Group creation failed in the background for group: {group.name}")

    return {"message": "Group creation is successful."}

@app.post("/groups/add_expense")
async def add_expense(background_tasks: BackgroundTasks, add_expense_request: AddExpenseRequest = Body(...)):
    logger.info(f"Received request to add expense to group {add_expense_request.name} for user {add_expense_request.email}: {add_expense_request.expense}")

    # Validate shares
    if sum(add_expense_request.expense.shares.values()) != add_expense_request.expense.amount:
        logger.error("Adding expense failed: Shares do not sum up to the total amount.")
        raise HTTPException(status_code=400, detail="Shares do not sum up to the total amount.")

    def add_expense_task():
        group = crud.get_group_details_by_name(add_expense_request.name, add_expense_request.email)
        if not group:
            logger.error(f"Adding expense failed: Group {add_expense_request.name} not found for user {add_expense_request.email}")
            return
        crud.add_expense(group.id, add_expense_request.expense, add_expense_request.email)

    background_tasks.add_task(add_expense_task)
    return {"message": "Success"}

@app.post("/groups/add_payment")
async def add_payment(background_tasks: BackgroundTasks, add_payment_request: AddPaymentRequest = Body(...)):
    logger.info(f"Received request to add payment to group {add_payment_request.name} for user {add_payment_request.email}: {add_payment_request.payment}")

    def add_payment_task():
        group = crud.get_group_details_by_name(add_payment_request.name, add_payment_request.email)
        if not group:
            logger.error(f"Adding payment failed: Group {add_payment_request.name} not found for user {add_payment_request.email}")
            return
        crud.add_payment(group.id, add_payment_request.payment, add_payment_request.email)

    background_tasks.add_task(add_payment_task)
    return {"message": "Success"}


@app.post("/groups/get_group_details")
async def get_group_details(request: GroupNameRequest = Body(...)):
    logger.info(f"Received request to get group details for: {request.name} by {request.email}")
    group = crud.get_group_details_by_name(request.name, request.email)
    if not group:
        raise HTTPException(status_code=404, detail="Group not found.")
    return group

@app.post("/groups/get_groups")
async def get_groups(email: EmailRequest = Body(...)):
    logger.info(f"Received request to get groups for user: {email.email}")
    groups = crud.get_groups_by_user_email(email.email)
    return groups

@app.delete("/groups/delete")
async def delete_group(background_tasks: BackgroundTasks, request: GroupNameRequest = Body(...)):
    logger.info(f"Received request to delete group: {request.name} by {request.email}")

    group = crud.get_group_details_by_name(request.name, request.email)
    if not group:
        logger.error(f"Delete group failed: Group {request.name} not found for user {request.email}")
        raise HTTPException(status_code=404, detail="Group not found.")

    # Check if the user has any balances
    user_in_balances = any(request.email in balance for balance in group.balances)
    if user_in_balances:
        logger.error(f"Delete group failed: User {request.email} has pending balances in group {request.name}")
        raise HTTPException(status_code=400, detail="Cannot leave group with pending balances.")

    def delete_group_task():
        crud.delete_group(request.name, request.email)

    background_tasks.add_task(delete_group_task)
    return {"message": "Success"}

