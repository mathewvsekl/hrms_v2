import pandas as pd
import warnings
warnings.filterwarnings('ignore', category=UserWarning, module='openpyxl')

att_path = r'c:\Users\AneeshMathew\.antigravity\HRP\Employee attendance UG 2026.xlsx'
pay_path = r'c:\Users\AneeshMathew\.antigravity\HRP\Pay Slip Jan 2026.xlsm'

print("--- Attendance File ---")
try:
    att_xls = pd.ExcelFile(att_path)
    print("Sheets in Attendance:", att_xls.sheet_names)
    for sheet in att_xls.sheet_names[:2]:
        df = pd.read_excel(att_xls, sheet_name=sheet, nrows=5)
        print(f"\nSheet: {sheet}")
        print(df.head())
except Exception as e:
    print(f"Error reading {att_path}: {e}")

print("\n--- Payslip File ---")
try:
    pay_xls = pd.ExcelFile(pay_path)
    print("Sheets in Payslip:", pay_xls.sheet_names)
    for sheet in pay_xls.sheet_names[:2]:
        df = pd.read_excel(pay_xls, sheet_name=sheet, nrows=5)
        print(f"\nSheet: {sheet}")
        print(df.head())
except Exception as e:
    print(f"Error reading {pay_path}: {e}")
