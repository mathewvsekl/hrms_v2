import urllib.request
try:
    req = urllib.request.Request("http://localhost:8000/test_dash.php")
    with urllib.request.urlopen(req) as response:
        print("Status:", response.status)
        print(response.read().decode('utf-8'))
except Exception as e:
    print("Error:", e)
    if hasattr(e, 'read'):
        print(e.read().decode('utf-8'))
