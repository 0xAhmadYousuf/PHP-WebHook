import requests
import json

# Simple test to verify webhook is working
url = "http://localhost/webhook/test-path"

# Test 1: Simple GET request
print("Test 1: GET request")
response = requests.get(url + "?param1=value1&param2=value2")
print(f"Status: {response.status_code}, Response: {response.text}")

# Test 2: POST with JSON
print("\nTest 2: POST with JSON")
headers = {"Content-Type": "application/json", "WH_Event": "test_event"}
data = {"test_key": "test_value", "number": 123}
response = requests.post(url, headers=headers, json=data)
print(f"Status: {response.status_code}, Response: {response.text}")

# Test 3: POST with form data
print("\nTest 3: POST with form data")
headers = {"WH_Source": "simple_test"}
data = {"form_field1": "value1", "form_field2": "value2"}
response = requests.post(url, headers=headers, data=data)
print(f"Status: {response.status_code}, Response: {response.text}")

print("\nSimple tests completed. Check your dashboard at: http://localhost/webhook/?view_logs")
