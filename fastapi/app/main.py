from fastapi import FastAPI, HTTPException, Body, BackgroundTasks
from pydantic import BaseModel
from app import crud, schemas
from app.logging_config import logger
from app.session_manager import session_manager  # Ensure session_manager is imported

app = FastAPI()

@app.on_event("startup")
async def startup_event():
    # Initialize the session manager
    _ = session_manager

@app.on_event("shutdown")
async def shutdown_event():
    # End the session on shutdown
    session_manager.end_session()

class AddExpenseRequest(BaseModel):
    name: str
    email: str
    expense: schemas.ExpenseCreate

class AddPaymentRequest(BaseModel):
    name: str
    email: str
    payment: schemas.PaymentCreate

class AddUserRequest(BaseModel):
    group_name: str
    member_email: str
    new_member_email: str
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

class AddCurrencyRequest(BaseModel):
    group_name: str
    email: str
    currency: str
    conversion_rate: float

class RemoveExpenseRequest(BaseModel):
    group_name: str
    member_email: str
    expense_datetime: str

@app.get("/yo")
def hello():
    return {"message": "Hello World"}

@app.post("/groups/create")
async def create_group(background_tasks: BackgroundTasks, group: schemas.GroupCreate = Body(...)):
    logger.info(f"Received request to create group: {group}")

    all_members = [group.creator_email] + group.members
    for member in all_members:
        if crud.db_get_group_id_by_name(group.name, member):
            logger.error(f"Group creation failed: Member {member} is already part of a group with the name {group.name}.")
            raise HTTPException(status_code=400, detail="Group creation failed: One or more members are already part of a group with the same name. Please try with a different name.")
    
    def create_group_task():
        crud.create_group(group.name, group.creator_email, group.members, group.local_currency)

    background_tasks.add_task(create_group_task)
    return {"message": "Group created successfully"}

@app.post("/groups/add_currency")
async def add_currency(background_tasks: BackgroundTasks, request: AddCurrencyRequest):
    logger.info(f"Received request to add currency {request.currency} with conversion rate {request.conversion_rate} to group {request.group_name} by {request.email}")

    group_id = crud.db_get_group_id_by_name(request.group_name, request.email)
    if not group_id:
        logger.error(f"Adding currency failed: Group {request.group_name} not found for user {request.email}")
        raise HTTPException(status_code=404, detail="Group not found.")
     
    def add_currency_task():
        crud.add_currency(group_id, request.currency, request.conversion_rate)
     
    background_tasks.add_task(add_currency_task)
    return {"message": "Currency added successfully"}

@app.post("/groups/add_expense")
async def add_expense(background_tasks: BackgroundTasks, add_expense_request: AddExpenseRequest = Body(...)):
    logger.info(f"Received request to add expense to group {add_expense_request.name} for user {add_expense_request.email}: {add_expense_request.expense}")

    # Validate shares
    if sum(add_expense_request.expense.shares.values()) != add_expense_request.expense.amount:
        logger.error("Adding expense failed: Shares do not sum up to the total amount.")
        raise HTTPException(status_code=400, detail="Shares do not sum up to the total amount.")

    def add_expense_task():
        crud.add_expense(add_expense_request.name, add_expense_request.email, add_expense_request.expense)

    background_tasks.add_task(add_expense_task)
    return {"message": "Success"}

@app.post("/groups/remove_expense")
async def remove_expense(background_tasks: BackgroundTasks, remove_expense_request: RemoveExpenseRequest = Body(...)):
    logger.info(f"Received request to remove expense at {remove_expense_request.expense_datetime} from group {remove_expense_request.group_name} by {remove_expense_request.member_email}")

    def remove_expense_task():
        crud.remove_expense(remove_expense_request.group_name, remove_expense_request.member_email, remove_expense_request.expense_datetime)

    background_tasks.add_task(remove_expense_task)
    return {"message": "Expense removal is successful"}


@app.post("/groups/add_payment")
async def add_payment(background_tasks: BackgroundTasks, add_payment_request: AddPaymentRequest = Body(...)):
    logger.info(f"Received request to add payment to group {add_payment_request.name} for user {add_payment_request.email}: {add_payment_request.payment}")

    def add_payment_task():
        crud.add_payment(add_payment_request.name, add_payment_request.email, add_payment_request.payment)

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

    group = crud.db_get_group_id_by_name(request.name, request.email)
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

@app.post("/groups/add_user")
async def add_user_to_group(background_tasks: BackgroundTasks, request: AddUserRequest):
    group_id = crud.db_get_group_id_by_name(request.group_name, request.member_email)
    if not group_id:
        raise HTTPException(status_code=404, detail="Group not found or member does not belong to the group")
    
    def add_user_task():
        crud.add_user_to_group(group_id, request.new_member_email)

    background_tasks.add_task(add_user_task)
    return {"message": "User added to group"}
