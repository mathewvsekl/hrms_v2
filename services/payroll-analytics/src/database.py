import sqlite3
import pandas as pd

import logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

DB_PATH = 'hr_database.db'

def get_connection():
    """Returns a connection to the SQLite database."""
    conn = sqlite3.connect(DB_PATH)
    # Return rows as dictionary-like objects
    conn.row_factory = sqlite3.Row
    return conn

def init_db():
    """Initializes the SQLite database with the required schema."""
    conn = get_connection()
    c = conn.cursor()

    logger.info("Initializing database schema...")

    # Core_Personnel Table
    c.execute('''
        CREATE TABLE IF NOT EXISTS Core_Personnel (
            Employee_ID TEXT PRIMARY KEY,
            Full_Name TEXT NOT NULL,
            Designation TEXT,
            Email TEXT,
            Date_Of_Birth TEXT,
            Joining_Date TEXT,
            Basic_Salary REAL,
            Secondary_Employment_Flag BOOLEAN DEFAULT 0
        )
    ''')

    # Attendance_Ledger Table
    c.execute('''
        CREATE TABLE IF NOT EXISTS Attendance_Ledger (
            Log_ID INTEGER PRIMARY KEY AUTOINCREMENT,
            Employee_ID TEXT NOT NULL,
            Calendar_Date TEXT NOT NULL,
            Status_Code TEXT NOT NULL,
            FOREIGN KEY (Employee_ID) REFERENCES Core_Personnel (Employee_ID),
            UNIQUE(Employee_ID, Calendar_Date)
        )
    ''')
    
    # Leave_Transactions Table
    c.execute('''
        CREATE TABLE IF NOT EXISTS Leave_Transactions (
            Transaction_ID INTEGER PRIMARY KEY AUTOINCREMENT,
            Employee_ID TEXT NOT NULL,
            Leave_Category TEXT NOT NULL,
            Start_Date TEXT NOT NULL,
            End_Date TEXT NOT NULL,
            Approval_State TEXT NOT NULL,
            FOREIGN KEY (Employee_ID) REFERENCES Core_Personnel (Employee_ID)
        )
    ''')

    # Financial_Advances Table
    c.execute('''
        CREATE TABLE IF NOT EXISTS Financial_Advances (
            Advance_ID INTEGER PRIMARY KEY AUTOINCREMENT,
            Employee_ID TEXT NOT NULL,
            Principal_Amount REAL NOT NULL,
            Disbursement_Date TEXT NOT NULL,
            Monthly_Deduction_Rate REAL NOT NULL,
            Outstanding_Balance REAL NOT NULL,
            FOREIGN KEY (Employee_ID) REFERENCES Core_Personnel (Employee_ID)
        )
    ''')
    
    # Payroll_Executions Table
    c.execute('''
        CREATE TABLE IF NOT EXISTS Payroll_Executions (
            Run_ID INTEGER PRIMARY KEY AUTOINCREMENT,
            Employee_ID TEXT NOT NULL,
            Fiscal_Month TEXT NOT NULL,
            Fiscal_Year INTEGER NOT NULL,
            Gross_Earnings REAL NOT NULL,
            PAYE_Deduction REAL NOT NULL,
            NSSF_Deduction REAL NOT NULL,
            LST_Deduction REAL NOT NULL,
            Advance_Deduction REAL NOT NULL,
            Net_Disbursement REAL NOT NULL,
            FOREIGN KEY (Employee_ID) REFERENCES Core_Personnel (Employee_ID),
            UNIQUE(Employee_ID, Fiscal_Month, Fiscal_Year)
        )
    ''')

    conn.commit()
    conn.close()
    logger.info("Database schema initialized successfully.")

if __name__ == '__main__':
    # Initialize the database when the script is run directly
    init_db()
