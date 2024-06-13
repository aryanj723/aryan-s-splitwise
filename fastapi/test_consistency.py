import requests
import time

BASE_URL = "http://localhost:8001"

def call_create_group(name, creator_email, members):
    url = f"{BASE_URL}/groups/create"
    payload = {
        "name": name,
        "creator_email": creator_email,
        "members": members
    }
    headers = {
        "Content-Type": "application/json"
    }

    requests.post(url, json=payload, headers=headers)
    print(f"Group {name} creation: API called")

def call_add_expense(name, email, expense):
    url = f"{BASE_URL}/groups/add_expense"
    payload = {
        "name": name,
        "email": email,
        "expense": expense
    }
    headers = {
        "Content-Type": "application/json"
    }

    requests.post(url, json=payload, headers=headers)
    print(f"Expense addition to {name} by {email}: API called")

def call_add_payment(name, email, payment):
    url = f"{BASE_URL}/groups/add_payment"
    payload = {
        "name": name,
        "email": email,
        "payment": payment
    }
    headers = {
        "Content-Type": "application/json"
    }

    requests.post(url, json=payload, headers=headers)
    print(f"Payment addition to {name} by {email}: API called")

def get_group_id(name, email, retries=10, delay=1):
    url = f"{BASE_URL}/groups/get_group_details"
    payload = {
        "name": name,
        "email": email
    }
    headers = {
        "Content-Type": "application/json"
    }

    for _ in range(retries):
        response = requests.post(url, json=payload, headers=headers)
        if response.status_code == 200:
            return response.json()["id"]
        time.sleep(delay)
    raise Exception(f"Failed to fetch group ID for {name} after {retries} retries")

def get_balances(name, email, retries=10, delay=1):
    url = f"{BASE_URL}/groups/get_group_details"
    payload = {
        "name": name,
        "email": email
    }
    headers = {
        "Content-Type": "application/json"
    }

    for _ in range(retries):
        response = requests.post(url, json=payload, headers=headers)
        if response.status_code == 200:
            return response.json()["balances"]
        time.sleep(delay)
    raise Exception(f"Failed to fetch group ID for {name} after {retries} retries")

if __name__ == "__main__":
    # Create initial groups and add expenses/payments
    call_create_group("Trip", "ali@example.com", ["bobi@example.com"])
    call_create_group("Dinner", "charl@example.com", ["dav@example.com"])

    call_add_expense("Trip", "ali@example.com", {
        "description": "Hotel",
        "amount": 200,
        "paid_by": "ali@example.com",
        "shares": {
            "ali@example.com": 100,
            "bobi@example.com": 100
        }
    })

    call_add_expense("Trip", "bobi@example.com", {
        "description": "Food",
        "amount": 100,
        "paid_by": "bobi@example.com",
        "shares": {
            "ali@example.com": 50,
            "bobi@example.com": 50
        }
    })

    call_create_group("Trip", "alice@example.com", ["bob@example.com", "charlie@example.com", "dave@example.com", "eve@example.com"])

    call_add_expense("Dinner", "charl@example.com", {
        "description": "Pizza",
        "amount": 50,
        "paid_by": "charl@example.com",
        "shares": {
            "charl@example.com": 25,
            "dav@example.com": 25
        }
    })

    call_add_expense("Trip", "alice@example.com", {
        "description": "Hotel",
        "amount": 500,
        "paid_by": "alice@example.com",
        "shares": {
            "alice@example.com": 100,
            "bob@example.com": 100,
            "charlie@example.com": 100,
            "dave@example.com": 100,
            "eve@example.com": 100
        }
    })

    call_add_expense("Trip", "bob@example.com", {
        "description": "Food",
        "amount": 200,
        "paid_by": "bob@example.com",
        "shares": {
            "alice@example.com": 40,
            "bob@example.com": 40,
            "charlie@example.com": 40,
            "dave@example.com": 40,
            "eve@example.com": 40
        }
    })

    call_add_expense("Trip", "charlie@example.com", {
        "description": "Transport",
        "amount": 300,
        "paid_by": "charlie@example.com",
        "shares": {
            "alice@example.com": 60,
            "bob@example.com": 60,
            "charlie@example.com": 60,
            "dave@example.com": 60,
            "eve@example.com": 60
        }
    })
    call_add_payment("Trip", "charlie@example.com", {
        "description": "Payment to Dave",
        "amount": 50,
        "paid_by": "charlie@example.com",
        "paid_to": "dave@example.com"
    })

    call_add_expense("Dinner", "dav@example.com", {
        "description": "Drinks",
        "amount": 30,
        "paid_by": "dav@example.com",
        "shares": {
            "charl@example.com": 15,
            "dav@example.com": 15
        }
    })

    call_add_payment("Trip", "bobi@example.com", {
        "description": "Payment to Ali",
        "amount": 50,
        "paid_by": "bobi@example.com",
        "paid_to": "ali@example.com"
    })
    call_add_payment("Dinner", "dav@example.com", {
        "description": "Payment to Charl",
        "amount": 20,
        "paid_by": "dav@example.com",
        "paid_to": "charl@example.com"
    })

    trip_group_id = get_group_id("Trip", "ali@example.com")
    dinner_group_id = get_group_id("Dinner", "charl@example.com")

    expected_trip_balances = []
    expected_dinner_balances = [
        ["charl@example.com", "dav@example.com", 10]
    ]

    actual_trip_balances = get_balances("Trip", "ali@example.com")
    assert sorted(actual_trip_balances) == sorted(expected_trip_balances), f"Expected {expected_trip_balances} but got {actual_trip_balances}"

    actual_dinner_balances = get_balances("Dinner", "charl@example.com")
    assert sorted(actual_dinner_balances) == sorted(expected_dinner_balances), f"Expected {expected_dinner_balances} but got {actual_dinner_balances}"

    print("Balance verification for initial cases: Passed")


    call_add_expense("Trip", "dave@example.com", {
        "description": "Activities",
        "amount": 250,
        "paid_by": "dave@example.com",
        "shares": {
            "alice@example.com": 50,
            "bob@example.com": 50,
            "charlie@example.com": 50,
            "dave@example.com": 50,
            "eve@example.com": 50
        }
    })

    call_add_expense("Trip", "eve@example.com", {
        "description": "Miscellaneous",
        "amount": 150,
        "paid_by": "eve@example.com",
        "shares": {
            "alice@example.com": 30,
            "bob@example.com": 30,
            "charlie@example.com": 30,
            "dave@example.com": 30,
            "eve@example.com": 30
        }
    })

    call_add_payment("Trip", "bob@example.com", {
        "description": "Payment to Alice",
        "amount": 60,
        "paid_by": "bob@example.com",
        "paid_to": "alice@example.com"
    })
    call_add_payment("Trip", "dave@example.com", {
        "description": "Payment to Eve",
        "amount": 70,
        "paid_by": "dave@example.com",
        "paid_to": "eve@example.com"
    })
    call_add_payment("Trip", "eve@example.com", {
        "description": "Payment to Bob",
        "amount": 50,
        "paid_by": "eve@example.com",
        "paid_to": "bob@example.com"
    })
    call_add_payment("Trip", "alice@example.com", {
        "description": "Payment to Charlie",
        "amount": 30,
        "paid_by": "alice@example.com",
        "paid_to": "charlie@example.com"
    })

    trip_group_id = get_group_id("Trip", "alice@example.com")

    expected_trip_balances = [
        ["eve@example.com", "alice@example.com", 150.0],
        ["bob@example.com", "alice@example.com", 40.0],
        ["bob@example.com", "charlie@example.com", 30.0],
        ["dave@example.com", "charlie@example.com", 10.0]
    ]

    actual_trip_balances = get_balances("Trip", "alice@example.com")
    assert sorted(actual_trip_balances) == sorted(expected_trip_balances), f"Expected {expected_trip_balances} but got {actual_trip_balances}"

    print("Balance verification for additional case: Passed")
