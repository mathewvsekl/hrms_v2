import sqlite3
from src.database import get_connection

def calculate_nssf(gross_salary: float) -> dict:
    return {
        "employee_deduction": gross_salary * 0.05,
        "employer_contribution": gross_salary * 0.10,
        "total_contribution": gross_salary * 0.15
    }

def calculate_paye(taxable_pay: float, is_secondary_employment: bool = False) -> float:
    if taxable_pay <= 0: return 0.0
    if is_secondary_employment:
        if taxable_pay <= 10000000: return taxable_pay * 0.30
        else: return (10000000 * 0.30) + ((taxable_pay - 10000000) * 0.40)
    if taxable_pay <= 235000: return 0.0
    elif taxable_pay <= 335000: return (taxable_pay - 235000) * 0.10
    elif taxable_pay <= 410000: return 10000 + ((taxable_pay - 335000) * 0.20)
    elif taxable_pay <= 10000000: return 25000 + ((taxable_pay - 410000) * 0.30)
    else: return 2902000 + ((taxable_pay - 10000000) * 0.40)

def calculate_lst(take_home_salary: float) -> float:
    if take_home_salary <= 100000: return 0.0
    elif take_home_salary <= 200000: return 5000.0
    elif take_home_salary <= 300000: return 10000.0
    elif take_home_salary <= 400000: return 20000.0
    elif take_home_salary <= 500000: return 30000.0
    elif take_home_salary <= 600000: return 40000.0
    elif take_home_salary <= 700000: return 60000.0
    elif take_home_salary <= 800000: return 70000.0
    elif take_home_salary <= 900000: return 80000.0
    elif take_home_salary <= 1000000: return 90000.0
    else: return 100000.0

def calculate_payroll_for_month(
    basic_salary: float, absence_days: int = 0, is_secondary: bool = False, month: int = 1, advance_deduction: float = 0.0
) -> dict:
    daily_rate = basic_salary / 30.0
    lop_deduction = absence_days * daily_rate
    gross_earnings = max(0, basic_salary - lop_deduction)
    nssf = calculate_nssf(gross_earnings)
    employee_nssf = nssf['employee_deduction']
    taxable_pay = max(0, gross_earnings - employee_nssf)
    paye = calculate_paye(taxable_pay, is_secondary)
    provisional_net = gross_earnings - employee_nssf - paye
    
    lst_deduction = 0.0
    if month in [7, 8, 9, 10]:
        annual_lst = calculate_lst(provisional_net)
        lst_deduction = annual_lst / 4.0
        
    net_disbursement = provisional_net - lst_deduction - advance_deduction
    
    return {
        "basic_salary": basic_salary,
        "absence_days": absence_days,
        "lop_deduction": round(lop_deduction, 2),
        "gross_earnings": round(gross_earnings, 2),
        "nssf_employee": round(employee_nssf, 2),
        "nssf_employer": round(nssf['employer_contribution'], 2),
        "taxable_pay": round(taxable_pay, 2),
        "paye_deduction": round(paye, 2),
        "lst_deduction": round(lst_deduction, 2),
        "advance_deduction": round(advance_deduction, 2),
        "net_disbursement": round(net_disbursement, 2)
    }

def run_monthly_payroll(month_str: str, month_int: int, year: int = 2026):
    """Executes the payroll for all employees, updating Payroll_Executions and Financial_Advances."""
    conn = get_connection()
    c = conn.cursor()
    
    c.execute("SELECT * FROM Core_Personnel")
    employees = c.fetchall()
    
    results = []
    
    for emp in employees:
        emp_id = emp['Employee_ID']
        basic_salary = emp['Basic_Salary']
        is_secondary = emp['Secondary_Employment_Flag']
        
        # Count Absences
        date_pattern = f"{year}-{month_int:02d}-%"
        c.execute("SELECT COUNT(*) as absences FROM Attendance_Ledger WHERE Employee_ID=? AND Calendar_Date LIKE ? AND Status_Code='AB'", (emp_id, date_pattern))
        absence_days = c.fetchone()['absences']
        
        # Check Advances
        c.execute("SELECT Advance_ID, Monthly_Deduction_Rate, Outstanding_Balance FROM Financial_Advances WHERE Employee_ID=? AND Outstanding_Balance > 0", (emp_id,))
        advances = c.fetchall()
        
        total_advance_deduction = 0.0
        advances_to_update = []
        for adv in advances:
            deduction = min(adv['Monthly_Deduction_Rate'], adv['Outstanding_Balance'])
            total_advance_deduction += deduction
            advances_to_update.append((adv['Advance_ID'], adv['Outstanding_Balance'] - deduction))

        # Run Core Logic
        pay_result = calculate_payroll_for_month(
            basic_salary=basic_salary, 
            absence_days=absence_days, 
            is_secondary=is_secondary, 
            month=month_int, 
            advance_deduction=total_advance_deduction
        )
        
        # Save to DB
        try:
            c.execute('''
                INSERT INTO Payroll_Executions (Employee_ID, Fiscal_Month, Fiscal_Year, Gross_Earnings, PAYE_Deduction, NSSF_Deduction, LST_Deduction, Advance_Deduction, Net_Disbursement)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ''', (emp_id, month_str, year, pay_result['gross_earnings'], pay_result['paye_deduction'], pay_result['nssf_employee'], pay_result['lst_deduction'], pay_result['advance_deduction'], pay_result['net_disbursement']))
            
            # Update Outstanding Balances
            for adv_id, new_bal in advances_to_update:
                c.execute("UPDATE Financial_Advances SET Outstanding_Balance=? WHERE Advance_ID=?", (new_bal, adv_id))
                
            results.append((emp['Full_Name'], pay_result['net_disbursement']))
        except sqlite3.IntegrityError:
            print(f"Payroll already run for {emp['Full_Name']} in {month_str} {year}")

    conn.commit()
    conn.close()
    return results

if __name__ == "__main__":
    print(run_monthly_payroll("January", 1, 2026))
