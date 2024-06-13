from pydantic import BaseModel
from typing import List, Dict, Union

class ExpenseCreate(BaseModel):
    description: str
    amount: float
    paid_by: str
    shares: Dict[str, float]

class PaymentCreate(BaseModel):
    description: str
    amount: float
    paid_by: str
    paid_to: str

class Entry(BaseModel):
    type: str
    description: str
    amount: float
    paid_by: str
    date: str
    added_by: str
    shares: Dict[str, float] = None
    paid_to: str = None


class GroupCreate(BaseModel):
    name: str
    creator_email: str
    members: List[str]

class Group(BaseModel):
    id: str
    name: str
    creator_email: str
    members: List[str]
    entries: List[Entry]
    balances: List[List[Union[str, float]]] = []

class GroupNameRequest(BaseModel):
    name: str
    email: str

