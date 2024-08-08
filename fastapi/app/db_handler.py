import threading
from collections import defaultdict
from pymongo import ReturnDocument
from pymongo.client_session import ClientSession
from pymongo.errors import PyMongoError
import time
from retrying import retry
from app.logging_config import logger
from app.session_manager import session_manager
import functools
from fastapi import HTTPException


db = session_manager.client["group_expense_manager"]

# Collections
users_collection = db["users"]
groups_collection = db["groups"]
entries_collection = db["entries"]

# Lock management
group_locks = defaultdict(threading.Lock)
user_locks = defaultdict(threading.Lock)

# Retry decorator
def retry_if_pymongo_error(exception):
    return isinstance(exception, PyMongoError)

@retry(retry_on_exception=retry_if_pymongo_error, stop_max_attempt_number=3, wait_fixed=1000)
def get_group_lock(group_id: str, timeout: int = 5) -> bool:
    start_time = time.time()
    while time.time() - start_time < timeout:
        with group_locks[group_id]:  # Ensure only one thread accesses this section at a time per group
            result = groups_collection.find_one_and_update(
                {"id": group_id, "locked": {"$ne": True}},  # Only update if 'locked' is not True
                {"$set": {"locked": True}},
                return_document=ReturnDocument.AFTER,
                session=session_manager.session
            )
            if result:
                logger.info(f"Lock acquired for group {group_id}")
                return True
            logger.info(f"Failed to acquire lock for {group_id}..  retrying")
        time.sleep(1)
    logger.warning(f"Could not acquire lock for group {group_id} within timeout")
    return False

@retry(retry_on_exception=retry_if_pymongo_error, stop_max_attempt_number=3, wait_fixed=1000)
def release_group_lock(group_id: str):
    with group_locks[group_id]:  # Ensure only one thread accesses this section at a time per group
        result = groups_collection.update_one(
            {"id": group_id},
            {"$set": {"locked": False}},
            session=session_manager.session
        )
        if result.modified_count > 0:
            logger.info(f"Lock released for group {group_id}")
        else:
            logger.warning(f"Failed to release lock for group {group_id}")

@retry(retry_on_exception=retry_if_pymongo_error, stop_max_attempt_number=3, wait_fixed=1000)
def update_group_data(group_id: str, update_fields: dict, session: ClientSession = None):
    groups_collection.update_one(
        {"id": group_id},
        {"$set": update_fields},
        session=session
    )

@retry(retry_on_exception=retry_if_pymongo_error, stop_max_attempt_number=3, wait_fixed=1000)
def load_user_data(email: str) -> dict:
    user_data = users_collection.find_one({"email": email}, session=session_manager.session)
    if not user_data:
        user_data = {"email": email}
    return user_data

@retry(retry_on_exception=retry_if_pymongo_error, stop_max_attempt_number=3, wait_fixed=1000)
def save_user_data(user_data: dict, session: ClientSession = None):
    users_collection.update_one(
        {"email": user_data["email"]},
        {"$set": user_data},
        upsert=True,
        session=session
    )

@retry(retry_on_exception=retry_if_pymongo_error, stop_max_attempt_number=3, wait_fixed=1000)
def save_group(group: dict, session: ClientSession = None):
    groups_collection.insert_one(group, session=session)

@retry(retry_on_exception=retry_if_pymongo_error, stop_max_attempt_number=3, wait_fixed=1000)
def get_group_details(group_id: str) -> dict:
    return groups_collection.find_one({"id": group_id}, session=session_manager.session)

@retry(retry_on_exception=retry_if_pymongo_error, stop_max_attempt_number=3, wait_fixed=1000)
def update_group_data(group_id: str, data: dict, session: ClientSession = None):
    groups_collection.update_one({"id": group_id}, {"$set": data}, upsert=True, session=session)

@retry(retry_on_exception=retry_if_pymongo_error, stop_max_attempt_number=3, wait_fixed=1000)
def save_entry(entry: dict, session: ClientSession = None):
    entries_collection.insert_one(entry, session=session)

@retry(retry_on_exception=retry_if_pymongo_error, stop_max_attempt_number=3, wait_fixed=1000)
def get_entries_for_group(group_id: str) -> list:
    return list(entries_collection.find({"group_id": group_id}, session=session_manager.session))

@retry(retry_on_exception=retry_if_pymongo_error, stop_max_attempt_number=3, wait_fixed=1000)
def update_group_balances(group_id: str, balances: list, session: ClientSession = None):
    groups_collection.update_one({"id": group_id}, {"$set": {"balances": balances}}, session=session)

@retry(retry_on_exception=retry_if_pymongo_error, stop_max_attempt_number=3, wait_fixed=1000)
def append_group_entry(group_id: str, entry: dict, session: ClientSession = None):
    groups_collection.update_one(
        {"id": group_id},
        {"$push": {"entries": entry}},
        session=session
    )

@retry(retry_on_exception=retry_if_pymongo_error, stop_max_attempt_number=3, wait_fixed=1000)
def add_group_member(group_id: str, member_email: str, session: ClientSession = None):
    groups_collection.update_one(
        {"id": group_id},
        {"$push": {"members": member_email}},
        session=session
    )

@retry(retry_on_exception=retry_if_pymongo_error, stop_max_attempt_number=3, wait_fixed=1000)
def remove_group_member(group_id: str, member_email: str, session: ClientSession = None):
    groups_collection.update_one(
        {"id": group_id},
        {"$pull": {"members": member_email}},
        session=session
    )

@retry(retry_on_exception=retry_if_pymongo_error, stop_max_attempt_number=3, wait_fixed=1000)
def db_delete_group(group_id: str, session: ClientSession = None):
    groups_collection.delete_one({"id": group_id}, session=session)
    entries_collection.delete_many({"group_id": group_id}, session=session)

@retry(retry_on_exception=retry_if_pymongo_error, stop_max_attempt_number=3, wait_fixed=1000)
def mark_entry_cancelled(group_id: str, entry_index: int, session: ClientSession = None):
    result = groups_collection.update_one(
        {"id": group_id},
        {"$set": {f"entries.{entry_index}.cancelled": True}},
        session=session
    )
    if result.modified_count > 0:
        logger.info(f"Marked entry {entry_index} as cancelled in group {group_id}")
    else:
        logger.warning(f"Failed to mark entry {entry_index} as cancelled in group {group_id}")

@retry(retry_on_exception=retry_if_pymongo_error, stop_max_attempt_number=3, wait_fixed=1000)
def get_group_minimal_details(group_id: str) -> dict:
    group_data = groups_collection.find_one(
        {"id": group_id},
        {"_id": 0, "name": 1, "members": 1, "balances": 1, "currency_conversion_rates": 1, "spends": 1},
        session=session_manager.session
    )
    if not group_data:
        logger.error(f"Group minimal details not found: {group_id}")
        return None
    return group_data

def lru_cache_without_none(maxsize=5000):
    def decorator(func):
        cache = functools.lru_cache(maxsize=maxsize)(func)
        
        @functools.wraps(func)
        def wrapper(*args, **kwargs):
            result = cache(*args, **kwargs)
            if result is None:
                cache.cache_clear()
            return result
        
        wrapper.cache_info = cache.cache_info
        wrapper.cache_clear = cache.cache_clear
        return wrapper
    
    return decorator

@retry(retry_on_exception=retry_if_pymongo_error, stop_max_attempt_number=3, wait_fixed=1000)
@lru_cache_without_none(maxsize=10000)
def get_group_id_by_name(group_name: str, email: str) -> str:
    user_data = users_collection.find_one({"email": email}, {"_id": 0, "groups": 1}, session=session_manager.session)
    if not user_data or "groups" not in user_data:
        logger.info(f"User {email} not found or user has no groups.")
        return None

    matching_group_ids = []
    for group_id in user_data["groups"]:
        # Retrieve only the id and name fields for verification
        group_data = groups_collection.find_one({"id": group_id, "name": group_name}, {"_id": 0, "id": 1, "name": 1}, session=session_manager.session)
        if group_data:
            matching_group_ids.append(group_data["id"])

    if len(matching_group_ids) > 1:
        logger.error(f"Multiple groups found for user {email} with the name {group_name}. Group IDs: {matching_group_ids}")
        raise HTTPException(status_code=500, detail=f"Multiple groups found for user {email} with the name {group_name}. Please contact support.")

    if matching_group_ids:
        return matching_group_ids[0]

    logger.info(f"Group {group_name} not found for user {email}")
    return None

@retry(retry_on_exception=retry_if_pymongo_error, stop_max_attempt_number=3, wait_fixed=1000)
def get_entry_by_index(group_id: str, entry_index: int) -> dict:
    pipeline = [
        {"$match": {"id": group_id}},
        {"$project": {"entry": {"$arrayElemAt": ["$entries", entry_index]}}}
    ]
    result = list(groups_collection.aggregate(pipeline, session=session_manager.session))
    if not result or "entry" not in result[0]:
        logger.error(f"Entry {entry_index} not found for group {group_id}")
        return None
    return result[0]["entry"]

def append_log_entry(group_id: str, log_entry: str, session: ClientSession = None):
    result = groups_collection.update_one(
        {"id": group_id},
        {"$push": {"logs": log_entry}},
        session=session
    )
    if result.modified_count > 0:
        logger.info(f"Log entry appended for group {group_id}: {log_entry}")
    else:
        logger.warning(f"Failed to append log entry for group {group_id}: {log_entry}")
