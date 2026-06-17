import sqlite3
import json

db_path = r'c:\Users\AneeshMathew\HRMS V2\backend\database.sqlite'
conn = sqlite3.connect(db_path)
conn.row_factory = sqlite3.Row

# Find Aneesh
emp = conn.execute("SELECT * FROM employees WHERE first_name LIKE '%Aneesh%' OR last_name LIKE '%Aneesh%'").fetchone()
if not emp:
    print("Employee Aneesh not found")
    exit()

emp_id = emp['id']
print("Employee:", dict(emp))

# Attendance logs
logs = conn.execute("SELECT * FROM attendance_logs WHERE employee_id=? ORDER BY attendance_date DESC LIMIT 10", (emp_id,)).fetchall()
print("\nAttendance Logs:")
for l in logs:
    print(dict(l))

# Leave requests
requests = conn.execute("SELECT * FROM leave_requests WHERE employee_id=? ORDER BY id DESC LIMIT 5", (emp_id,)).fetchall()
print("\nLeave Requests:")
for r in requests:
    print(dict(r))

# Let's also check if he has returned to work
has_returned = conn.execute("SELECT 1 FROM attendance_logs WHERE employee_id = ? AND status IN ('present', 'work_from_home') LIMIT 1", (emp_id,)).fetchone()
print("\nHas Present log:", bool(has_returned))
