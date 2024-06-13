import requests
import time

BASE_URL = "http://localhost:8001"

def test_create_group(name, creator_email, members):
    url = f"{BASE_URL}/groups/create"
    payload = {
        "name": name,
        "creator_email": creator_email,
        "members": members
    }
    headers = {
        "Content-Type": "application/json"
    }

    response = requests.post(url, json=payload, headers=headers)
    assert response.status_code == 200
    assert response.json().get("message") == "Group creation is successful."
    print(f"Group {name} creation: Passed")
    time.sleep(2)

def test_add_expense(name, email, expense):
    url = f"{BASE_URL}/groups/add_expense"
    payload = {
        "name": name,
        "email": email,
        "expense": expense
    }
    headers = {
        "Content-Type": "application/json"
    }

    response = requests.post(url, json=payload, headers=headers)
    assert response.status_code == 200
    assert response.json().get("message") == "Success"
    print(f"Expense addition to {name} by {email}: Passed")
    time.sleep(2)

def test_add_payment(name, email, payment):
    url = f"{BASE_URL}/groups/add_payment"
    payload = {
        "name": name,
        "email": email,
        "payment": payment
    }
    headers = {
        "Content-Type": "application/json"
    }

    response = requests.post(url, json=payload, headers=headers)
    assert response.status_code == 200
    assert response.json().get("message") == "Success"
    print(f"Payment addition to {name} by {email}: Passed")
    time.sleep(2)

def compare_entries(actual_entries, expected_entries):
    for actual, expected in zip(actual_entries, expected_entries):
        for key, value in expected.items():
            assert key in actual
            if key != 'date':
                assert actual[key] == value
    return True

def test_get_group_details(name, email, expected_details):
    url = f"{BASE_URL}/groups/get_group_details"
    payload = {
        "name": name,
        "email": email
    }
    headers = {
        "Content-Type": "application/json"
    }

    response = requests.post(url, json=payload, headers=headers)
    assert response.status_code == 200
    details = response.json()
    assert details["name"] == expected_details["name"]
    assert details["creator_email"] == expected_details["creator_email"]
    assert details["members"] == expected_details["members"]
    assert compare_entries(details["entries"], expected_details["entries"])
    print(f"Group details for {name} fetched: Passed")
    return details["id"]

def test_get_groups(email):
    url = f"{BASE_URL}/groups/get_groups"
    payload = {
        "email": email
    }
    headers = {
        "Content-Type": "application/json"
    }

    response = requests.post(url, json=payload, headers=headers)
    assert response.status_code == 200
    group_names = response.json()
    print(f"Groups for {email} fetched: Passed")
    return group_names

def test_get_balances(group_id, expected_balances):
    url = f"{BASE_URL}/groups/get_balances"
    payload = {
        "group_id": group_id
    }
    headers = {
        "Content-Type": "application/json"
    }

    response = requests.post(url, json=payload, headers=headers)
    assert response.status_code == 200
    balances = response.json()
    assert sorted(balances) == sorted(expected_balances), f"Expected {expected_balances} but got {balances}"
    print(f"Balances for group {group_id} fetched and verified: Passed")
    return balances

if __name__ == "__main__":
    # Create groups
    test_create_group("Trip", "ali@example.com", ["bobi@example.com"])
    test_create_group("Dinner", "charl@example.com", ["dav@example.com"])

    # Add expenses
    test_add_expense("Trip", "ali@example.com", {
        "description": "Hotel",
        "amount": 200,
        "paid_by": "ali@example.com",
        "shares": {
            "ali@example.com": 100,
            "bobi@example.com": 100
        }
    })

    test_add_expense("Trip", "bobi@example.com", {
        "description": "Food",
        "amount": 100,
        "paid_by": "bobi@example.com",
        "shares": {
            "ali@example.com": 50,
            "bobi@example.com": 50
        }
    })

    test_add_expense("Dinner", "charl@example.com", {
        "description": "Pizza",
        "amount": 50,
        "paid_by": "charl@example.com",
        "shares": {
            "charl@example.com": 25,
            "dav@example.com": 25
        }
    })
    test_add_expense("Dinner", "dav@example.com", {
        "description": "Drinks",
        "amount": 30,
        "paid_by": "dav@example.com",
        "shares": {
            "charl@example.com": 15,
            "dav@example.com": 15
        }
    })

    # Add payments
    test_add_payment("Trip", "bobi@example.com", {
        "description": "Payment to Alice",
        "amount": 50,
        "paid_by": "bobi@example.com",
        "paid_to": "ali@example.com"
    })
    test_add_payment("Dinner", "dav@example.com", {
        "description": "Payment to Charlie",
        "amount": 20,
        "paid_by": "dav@example.com",
        "paid_to": "charl@example.com"
    })

    # Get groups and fetch group details
    trip_group_id = None
    dinner_group_id = None
    alice_groups = test_get_groups("ali@example.com")
    charlie_groups = test_get_groups("charl@example.com")

    trip_details = {
        "name": "Trip",
        "creator_email": "ali@example.com",
        "members": ["ali@example.com", "bobi@example.com"],
        "entries": [
            {
                "type": "expense",
                "description": "Hotel",
                "amount": 200.0,
                "paid_by": "ali@example.com",
                "shares": {
                    "ali@example.com": 100.0,
                    "bobi@example.com": 100.0
                }
            },
            {
                "type": "expense",
                "description": "Food",
                "amount": 100.0,
                "paid_by": "bobi@example.com",
                "shares": {
                    "ali@example.com": 50.0,
                    "bobi@example.com": 50.0
                }
            },
            {
                "type": "settlement",
                "description": "Payment to Alice",
                "amount": 50.0,
                "paid_by": "bobi@example.com",
                "paid_to": "ali@example.com"
            }
        ]
    }
    dinner_details = {
        "name": "Dinner",
        "creator_email": "charl@example.com",
        "members": ["charl@example.com", "dav@example.com"],
        "entries": [
            {
                "type": "expense",
                "description": "Pizza",
                "amount": 50.0,
                "paid_by": "charl@example.com",
                "shares": {
                    "charl@example.com": 25.0,
                    "dav@example.com": 25.0
                }
            },
            {
                "type": "expense",
                "description": "Drinks",
                "amount": 30.0,
                "paid_by": "dav@example.com",
                "shares": {
                    "charl@example.com": 15.0,
                    "dav@example.com": 15.0
                }
            },
            {
                "type": "settlement",
                "description": "Payment to Charlie",
                "amount": 20.0,
                "paid_by": "dav@example.com",
                "paid_to": "charl@example.com"
            }
        ]
    }
    trip_group_id = test_get_group_details("Trip", "ali@example.com", trip_details)
    dinner_group_id = test_get_group_details("Dinner", "charl@example.com", dinner_details)

    # Expected balances
    expected_trip_balances = [
    ]
    expected_dinner_balances = [
        ["charl@example.com", "dav@example.com", 10.0]
    ]

    # Get balances and verify
    test_get_balances(trip_group_id, expected_trip_balances)
    test_get_balances(dinner_group_id, expected_dinner_balances)

    test_create_group("Trip", "alice@example.com", ["bob@example.com", "charlie@example.com", "dave@example.com", "eve@example.com"])

    # Add expenses
    test_add_expense("Trip", "alice@example.com", {
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

    test_add_expense("Trip", "bob@example.com", {
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

    test_add_expense("Trip", "charlie@example.com", {
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

    test_add_expense("Trip", "dave@example.com", {
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

    test_add_expense("Trip", "eve@example.com", {
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

    # Add payments
    test_add_payment("Trip", "bob@example.com", {
        "description": "Payment to Alice",
        "amount": 60,
        "paid_by": "bob@example.com",
        "paid_to": "alice@example.com"
    })
    test_add_payment("Trip", "charlie@example.com", {
        "description": "Payment to Dave",
        "amount": 50,
        "paid_by": "charlie@example.com",
        "paid_to": "dave@example.com"
    })
    test_add_payment("Trip", "dave@example.com", {
        "description": "Payment to Eve",
        "amount": 70,
        "paid_by": "dave@example.com",
        "paid_to": "eve@example.com"
    })
    test_add_payment("Trip", "eve@example.com", {
        "description": "Payment to Bob",
        "amount": 50,
        "paid_by": "eve@example.com",
        "paid_to": "bob@example.com"
    })
    test_add_payment("Trip", "alice@example.com", {
        "description": "Payment to Charlie",
        "amount": 30,
        "paid_by": "alice@example.com",
        "paid_to": "charlie@example.com"
    })

    # Get groups and fetch group details
    trip_group_id = None
    alice_groups = test_get_groups("alice@example.com")

    trip_details = {
        "name": "Trip",
        "creator_email": "alice@example.com",
        "members": ["alice@example.com", "bob@example.com", "charlie@example.com", "dave@example.com", "eve@example.com"],
        "entries": [
            {
                "type": "expense",
                "description": "Hotel",
                "amount": 500.0,
                "paid_by": "alice@example.com",
                "shares": {
                    "alice@example.com": 100.0,
                    "bob@example.com": 100.0,
                    "charlie@example.com": 100.0,
                    "dave@example.com": 100.0,
                    "eve@example.com": 100.0
                }
            },
            {
                "type": "expense",
                "description": "Food",
                "amount": 200.0,
                "paid_by": "bob@example.com",
                "shares": {
                    "alice@example.com": 40.0,
                    "bob@example.com": 40.0,
                    "charlie@example.com": 40.0,
                    "dave@example.com": 40.0,
                    "eve@example.com": 40.0
                }
            },
            {
                "type": "expense",
                "description": "Transport",
                "amount": 300.0,
                "paid_by": "charlie@example.com",
                "shares": {
                    "alice@example.com": 60.0,
                    "bob@example.com": 60.0,
                    "charlie@example.com": 60.0,
                    "dave@example.com": 60.0,
                    "eve@example.com": 60.0
                }
            },
            {
                "type": "expense",
                "description": "Activities",
                "amount": 250.0,
                "paid_by": "dave@example.com",
                "shares": {
                    "alice@example.com": 50.0,
                    "bob@example.com": 50.0,
                    "charlie@example.com": 50.0,
                    "dave@example.com": 50.0,
                    "eve@example.com": 50.0
                }
            },
            {
                "type": "expense",
                "description": "Miscellaneous",
                "amount": 150.0,
                "paid_by": "eve@example.com",
                "shares": {
                    "alice@example.com": 30.0,
                    "bob@example.com": 30.0,
                    "charlie@example.com": 30.0,
                    "dave@example.com": 30.0,
                    "eve@example.com": 30.0
                }
            },
            {
                "type": "settlement",
                "description": "Payment to Alice",
                "amount": 60.0,
                "paid_by": "bob@example.com",
                "paid_to": "alice@example.com"
            },
            {
                "type": "settlement",
                "description": "Payment to Dave",
                "amount": 50.0,
                "paid_by": "charlie@example.com",
                "paid_to": "dave@example.com"
            },
            {
                "type": "settlement",
                "description": "Payment to Eve",
                "amount": 70.0,
                "paid_by": "dave@example.com",
                "paid_to": "eve@example.com"
            },
            {
                "type": "settlement",
                "description": "Payment to Bob",
                "amount": 50.0,
                "paid_by": "eve@example.com",
                "paid_to": "bob@example.com"
            },
            {
                "type": "settlement",
                "description": "Payment to Charlie",
                "amount": 30.0,
                "paid_by": "alice@example.com",
                "paid_to": "charlie@example.com"
            }
        ]
    }

    trip_group_id = test_get_group_details("Trip", "alice@example.com", trip_details)

    # Expected balances
    expected_trip_balances = [
    ["eve@example.com", "alice@example.com", 150.0],
    ["bob@example.com", "alice@example.com", 40.0],
    ["bob@example.com", "charlie@example.com", 30.0],
    ["dave@example.com", "charlie@example.com", 10.0]
]

    # Get balances and verify
    test_get_balances(trip_group_id, expected_trip_balances)