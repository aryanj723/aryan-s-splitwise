import threading
from collections import defaultdict
from pymongo import ReturnDocument
from pymongo.client_session import ClientSession
from pymongo.errors import PyMongoError
import time
from retrying import retry
from app.logging_config import logger
from app.session_manager import session_manager

db = session_manager.client["group_expense_manager"]

# Collections
users_collection = db["users"]
groups_collection = db["groups"]
entries_collection = db["entries"]

# Lock management
group_locks = defaultdict(threading.Lock)

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