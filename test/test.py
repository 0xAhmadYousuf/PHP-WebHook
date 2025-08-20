import random
import requests
import string
import json

# Define the base URL
BASE_URL = "http://localhost/webhook/"

# Define HTTP methods and content types
HTTP_METHODS = ["GET", "POST", "PUT", "DELETE", "PATCH"]
CONTENT_TYPES = [
    "application/json",
    "application/x-www-form-urlencoded", 
    "multipart/form-data",
    "application/xml",
    "text/plain",
    "application/octet-stream"
]

# Generate random string
def random_string(length=10):
    return ''.join(random.choices(string.ascii_letters + string.digits, k=length))

# Generate random JSON payload
def random_json():
    return {random_string(): random_string() for _ in range(random.randint(1, 5))}

# Generate random form data
def random_form_data():
    return {random_string(): random_string() for _ in range(random.randint(1, 5))}

# Generate random plain text
def random_text():
    return random_string(random.randint(20, 100))

# Generate random binary data
def random_binary():
    return bytes(random_string(50), 'utf-8')

# Generate random XML
def random_xml():
    return f"<root><{random_string()}>{random_string()}</{random_string()}></root>"

# Generate random path
def random_path():
    return random_string(random.randint(5, 15))

# Send 1000 random requests
for i in range(1000):
    method = random.choice(HTTP_METHODS)
    content_type = random.choice(CONTENT_TYPES)
    path = random_path()
    url = BASE_URL + path

    # Add webhook headers
    headers = {
        "Content-Type": content_type,
        "WH_Event": random_string(8),
        "WH_Source": "test_script",
        "User-Agent": f"TestBot/{random.randint(1,5)}.0"
    }
    
    data = None
    files = None
    json_data = None

    if content_type == "application/json":
        json_data = random_json()
        data = json.dumps(json_data)
    elif content_type == "application/x-www-form-urlencoded":
        data = random_form_data()
    elif content_type == "multipart/form-data":
        files = {random_string(): (random_string() + ".txt", random_binary())}
        # Remove content-type header for multipart, requests will set it
        del headers["Content-Type"]
    elif content_type == "application/xml":
        data = random_xml()
    elif content_type == "text/plain":
        data = random_text()
    elif content_type == "application/octet-stream":
        data = random_binary()

    try:
        response = None
        if method == "GET":
            response = requests.get(url, headers=headers, params=data if isinstance(data, dict) else None)
        elif method == "POST":
            if files:
                response = requests.post(url, headers=headers, files=files)
            elif json_data:
                response = requests.post(url, headers=headers, json=json_data)
            else:
                response = requests.post(url, headers=headers, data=data)
        elif method == "PUT":
            if json_data:
                response = requests.put(url, headers=headers, json=json_data)
            else:
                response = requests.put(url, headers=headers, data=data)
        elif method == "DELETE":
            response = requests.delete(url, headers=headers, data=data if not isinstance(data, dict) else None)
        elif method == "PATCH":
            if json_data:
                response = requests.patch(url, headers=headers, json=json_data)
            else:
                response = requests.patch(url, headers=headers, data=data)

        print(f"Request {i+1}: {method} {url} - Status Code: {response.status_code}")
    except Exception as e:
        print(f"Request {i+1}: {method} {url} - Failed with error: {e}")

print("Test completed! Check your webhook dashboard for the logged requests.")
