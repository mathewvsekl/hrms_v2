import urllib.request
import urllib.parse
import json

base_url = 'http://127.0.0.1:8000'

# 1. Login to get cookie
login_data = urllib.parse.urlencode({'email': 'atim@test.com', 'password': 'password123'}).encode('ascii')
req = urllib.request.Request(f'{base_url}/api/auth/login', data=login_data, method='POST')
req.add_header('Content-Type', 'application/x-www-form-urlencoded')

try:
    with urllib.request.urlopen(req) as response:
        cookie = response.info().get('Set-Cookie')
        print("Login Success. Cookie:", cookie)
        
        # 2. Fetch dashboard stats
        req2 = urllib.request.Request(f'{base_url}/api/dashboard/summary')
        req2.add_header('Cookie', cookie)
        
        with urllib.request.urlopen(req2) as resp2:
            data = json.loads(resp2.read().decode('utf-8'))
            print("Dashboard Stats:")
            print(json.dumps(data, indent=2))
except Exception as e:
    print("Error:", e)
    if hasattr(e, 'read'):
        print(e.read().decode('utf-8'))
