import threading
from collections import defaultdict
from pymongo import MongoClient, ReturnDocument
import time

# MongoDB connection setup
client = MongoClient("mongodb://mongo:27017/")
db = client["group_expense_manager"]

# Collections
users_collection = db["users"]
groups_collection = db["groups"]
entries_collection = db["entries"]

# Lock management
group_locks = defaultdict(threading.Lock)


def get_group_lock(group_id: str, timeout: int = 5) -> bool:
    start_time = time.time()
    while time.time() - start_time < timeout:
        result = groups_collection.find_one_and_update(
            {"id": group_id, "locked": False},
            {"$set": {"locked": True}},
            return_document=ReturnDocument.AFTER
        )
        if result:
            return True
        time.sleep(0.1)  # Sleep for 100ms before retrying
    return False

def release_group_lock(group_id: str):
    groups_collection.update_one(
        {"id": group_id},
        {"$set": {"locked": False}}
    )

def update_group_data(group_id: str, update_fields: dict):
    groups_collection.update_one(
        {"id": group_id},
        {"$set": update_fields}
    )

def load_user_data(email: str) -> dict:
    user_data = users_collection.find_one({"email": email})
    if not user_data:
        user_data = {"email": email}
    return user_data

def save_user_data(user_data: dict):
    users_collection.update_one({"email": user_data["email"]}, {"$set": user_data}, upsert=True)

def save_group(group: dict):
    groups_collection.insert_one(group)

def get_group_details(group_id: str) -> dict:
    return groups_collection.find_one({"id": group_id})

def update_group_data(group_id: str, data: dict):
    groups_collection.update_one({"id": group_id}, {"$set": data}, upsert=True)

def save_entry(entry: dict):
    entries_collection.insert_one(entry)

def get_entries_for_group(group_id: str) -> list:
    return list(entries_collection.find({"group_id": group_id}))

def update_group_balances(group_id: str, balances: list):
    groups_collection.update_one({"id": group_id}, {"$set": {"balances": balances}})

def append_group_entry(group_id: str, entry: dict):
    groups_collection.update_one({"id": group_id}, {"$push": {"entries": entry}})

def remove_group_member(group_id: str, member_email: str):
    groups_collection.update_one({"id": group_id}, {"$pull": {"members": member_email}})

def db_delete_group(group_id: str):
    groups_collection.delete_one({"id": group_id})
    entries_collection.delete_many({"group_id": group_id})


