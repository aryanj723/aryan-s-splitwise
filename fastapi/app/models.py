from pydantic import BaseModel
from typing import List, Dict, Union

class Entry(BaseModel):
    type: str
    description: str
    amount: float
    paid_by: str
    date: str
    added_by: str

class Expense(Entry):
    shares: Dict[str, float]

class Payment(Entry):
    paid_to: str

class Group(BaseModel):
    id: str
    name: str
    creator_email: str
    members: List[str]
    entries: List[Union[Expense, Payment]]
    balances: List[List[Union[str, float]]] = []
