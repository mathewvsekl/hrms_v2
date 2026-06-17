import pandas as pd
import sqlite3
import datetime
import warnings

warnings.filterwarnings('ignore', category=UserWarning, module='openpyxl')

DB_PATH = 'hr_database.db'
EXCEL_PATH = r'c:\Users\AneeshMathew\.antigravity\HRP\Employee attendance UG 2026.xlsx'

def get_db():
    return sqlite3.connect(DB_PATH)

def seed_personnel():
    conn = get_db()
    c = conn.cursor()
    # Baseline personnel data from the Plan.md for accuracy
    personnel_data = [
        ("VUEMP001", "Nyinimuntu Angela", "Sales Manager", "angela.nyinimuntu@visionscientificafrica.com", "1988-10-24", "2018-11-01", 1500000, 0),
        ("VUEMP002", "Joseph Opolot", "Finance Manager", "joseph.opolot@visionscientificafrica.com", "1977-04-26", "2016-04-01", 3000000, 0),
        ("VUEMP004", "Moses Ikwara", "Ware House Executive", "moses.ikwara@visionscientificafrica.com", "1977-09-23", "2015-11-01", 800000, 0),
        ("VUEMP005", "Hawa Sirikye", "Front Office Executive", "hawa.sirikye@visionscientificafrica.com", "1991-04-30", "2018-02-01", 800000, 0),
        ("VUEMP008", "Aneesh Mathew", "Country Manager", "aneesh.mathew@visionscientificafrica.com", "1984-08-07", "2021-05-01", 5000000, 0),
        ("VUEMP009", "Atim Bernadatte", "Office Assistant", "atim.bernadatte@visionscientificafrica.com", "1996-04-01", "2021-06-01", 400000, 0),
        ("VUEMP011", "Kawunde Paul", "Business Development Executive", "kawunde.paul@visionscientificafrica.com", "1994-12-29", "2020-02-01", 1000000, 0),
        ("VUEMP015", "Lubega Joel Brian", "Business Development Executive", "lubega.joel@visionscientificafrica.com", "1988-12-04", "2023-07-01", 1000000, 0),
        ("VUEMP017", "Joseph Wetaka Wandega", "Business Development Executive", "joseph.wetaka@visionscientificafrica.com", "1993-02-19", "2024-09-09", 1000000, 0),
        ("VUEMP018", "Solomon Kiwunda", "Business Development Executive", "solomon.kiwunda@visionscientificafrica.com", "1996-07-26", "2025-01-06", 1000000, 0),
    ]

    c.execute("DELETE FROM Core_Personnel")
    c.executemany('''
        INSERT INTO Core_Personnel 
        (Employee_ID, Full_Name, Designation, Email, Date_Of_Birth, Joining_Date, Basic_Salary, Secondary_Employment_Flag)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ''', personnel_data)
    conn.commit()
    conn.close()
    print(f"Seeded {len(personnel_data)} employees.")

def parse_attendance():
    """Parses attendance structure: rows are employees, columns are days 1-31"""
    months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']
    conn = get_db()
    c = conn.cursor()
    c.execute("DELETE FROM Attendance_Ledger")
    
    # Get personnel map to map Name -> Employee_ID
    c.execute("SELECT Employee_ID, Full_Name FROM Core_Personnel")
    emp_map = {row[1].strip().lower(): row[0] for row in c.fetchall()}

    xls = pd.ExcelFile(EXCEL_PATH)

    total_records = 0
    for month_idx, month_name in enumerate(months):
        sheet_name = f'UG {month_name} 2026'
        if sheet_name not in xls.sheet_names:
            print(f"Skipping missing sheet: {sheet_name}")
            continue
            
        df = pd.read_excel(xls, sheet_name=sheet_name, skiprows=1)
        # Find the row that contains "Employee Name" to use as true header
        header_row_idx = df[df.eq('Employee Name').any(axis=1)].index
        if len(header_row_idx) > 0:
            df = pd.read_excel(xls, sheet_name=sheet_name, skiprows=header_row_idx[0] + 2)
            
        # Clean dataframe structure (Employee Name is usually at column 'Unnamed: 1')
        if 'Unnamed: 1' in df.columns:
            employee_col = 'Unnamed: 1'
        else:
            employee_col = df.columns[1]

        # Iterate through rows
        for _, row in df.iterrows():
            emp_name = str(row.get(employee_col, '')).strip()
            if not emp_name or emp_name.lower() == 'nan' or 'total' in emp_name.lower():
                continue
            
            emp_id = emp_map.get(emp_name.lower())
            if not emp_id:
                # Fuzzy matching due to spacing or middle names, or Employee ID being used instead of Name
                for name, id_ in emp_map.items():
                    if name in emp_name.lower() or emp_name.lower() in name or id_.lower() == emp_name.lower():
                        emp_id = id_
                        break
            
            if not emp_id:
                print(f"Warning: Could not map employee name '{emp_name}' in {month_name}")
                continue

            # Days are typically columns index 2 to 32
            for day in range(1, 32):
                date_str = f"2026-{month_idx+1:02d}-{day:02d}"
                # Handle invalid dates like Feb 30th
                try:
                    datetime.date(2026, month_idx+1, day)
                except ValueError:
                    continue # Skip invalid dates
                
                # Try to find the column for the day
                col_name = str(day)
                if col_name in df.columns:
                    status = str(row[col_name]).strip().upper()
                else: # Sometimes it reads as unnamed columns
                    try:
                        status = str(row.iloc[day + 1]).strip().upper()
                    except IndexError:
                        continue
                        
                if status == 'NAN' or not status:
                    continue
                
                try:
                    c.execute('''
                        INSERT INTO Attendance_Ledger 
                        (Employee_ID, Calendar_Date, Status_Code)
                        VALUES (?, ?, ?)
                    ''', (emp_id, date_str, status))
                    total_records += 1
                except sqlite3.IntegrityError:
                    pass # Ignore duplicate entries if re-run

    conn.commit()
    conn.close()
    print(f"Seeded {total_records} attendance records.")

def parse_advances():
    conn = get_db()
    c = conn.cursor()
    c.execute("DELETE FROM Financial_Advances")
    
    # Based on anomalies in January 2026 data
    advances = [
        ("VUEMP011", 1250000.0, "2026-01-20", 250000.0, 1250000.0), # Kawunde Paul
        ("VUEMP015", 3600000.0, "2026-01-20", 300000.0, 3600000.0), # Lubega Joel Brian
        ("VUEMP017", 200000.0, "2026-01-20", 200000.0, 200000.0), # Joseph Wetaka
    ]
    
    c.executemany('''
        INSERT INTO Financial_Advances 
        (Employee_ID, Principal_Amount, Disbursement_Date, Monthly_Deduction_Rate, Outstanding_Balance)
        VALUES (?, ?, ?, ?, ?)
    ''', advances)
    
    conn.commit()
    conn.close()
    print(f"Seeded {len(advances)} financial advance records.")

if __name__ == '__main__':
    seed_personnel()
    parse_attendance()
    parse_advances()
    print("Ingestion complete.")
