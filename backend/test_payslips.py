import sqlite3

db_path = r'c:\Users\AneeshMathew\HRMS V2\backend\database.sqlite'
try:
    conn = sqlite3.connect(db_path)
    conn.row_factory = sqlite3.Row

    # Print payslips schema
    cur = conn.execute("PRAGMA table_info(payslips)")
    print("Payslips Columns:", [row['name'] for row in cur.fetchall()])

    # Print employee_companies schema
    cur = conn.execute("PRAGMA table_info(employee_companies)")
    print("Employee Companies Columns:", [row['name'] for row in cur.fetchall()])

except Exception as e:
    print("Error:", e)
