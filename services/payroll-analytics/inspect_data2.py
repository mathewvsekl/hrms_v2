import pandas as pd
import warnings
warnings.filterwarnings('ignore', category=UserWarning, module='openpyxl')

att_path = r'c:\Users\AneeshMathew\.antigravity\HRP\Employee attendance UG 2026.xlsx'
att_xls = pd.ExcelFile(att_path)

print("--- Employee List ---")
try:
    df_emp = pd.read_excel(att_xls, sheet_name='Employee List')
    print(df_emp.head(10))
    print("\nColumns:", df_emp.columns.tolist())
except Exception as e:
    print(f"Error: {e}")

print("\n--- UG January 2026 ---")
try:
    df_jan = pd.read_excel(att_xls, sheet_name='UG January 2026')
    print(df_jan.head(15))
    print("\nColumns:", df_jan.columns.tolist())
except Exception as e:
    print(f"Error: {e}")
