from pydantic import BaseModel, validator
from typing import List, Dict
from app import crud

class ExpenseCreate(BaseModel):
    description: str
    amount: float
    paid_by: str
    shares: Dict[str, float]
    
    @validator('shares')
    def validate_shares(cls, shares, values):
        amount = values.get('amount')
        # Validate that the sum of shares equals the amount
        if sum(shares.values()) != amount:
            raise ValueError("Shares do not sum up to the total amount.")
        return shares

class PaymentCreate(BaseModel):
    description: str
    amount: float
    paid_by: str
    paid_to: str
    
    @validator('paid_to')
    def validate_shares(cls, paid_to, values):
        paid_by = values.get('paid_by')
        # Validate that the sum of shares equals the amount
        if paid_to==paid_by:
            raise ValueError("Can't pay to yourself")
        return paid_to

class GroupCreate(BaseModel):
    name: str
    creator_email: str
    members: List[str]
    
    @validator('name')
    def name_must_be_unique(cls, name: str, values):
        for member in [values['creator_email']] + values['members']:
            user_data = crud.db_load_user_data(member)
            if "groups" in user_data:
                for group_id in user_data["groups"]:
                    existing_group = crud.db_get_group_details(group_id)
                    if existing_group and existing_group.get('name') == name:
                        raise ValueError("Group name must be unique among the members' existing groups.")
        return name

class GroupDeleteRequest(BaseModel):
    name: str
    email: str

class AddExpenseRequest(BaseModel):
    name: str
    email: str
    expense: ExpenseCreate

class AddPaymentRequest(BaseModel):
    name: str
    email: str
    payment: PaymentCreate

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