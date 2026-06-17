import sys
import os
sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), '..')))
import streamlit as st
import pandas as pd
import sqlite3
import datetime
import calendar
from src.database import get_connection
from src.payroll_engine import run_monthly_payroll

# Optional Page Config
st.set_page_config(page_title="Uganda HR & Payroll 2026", page_icon="🇺🇬", layout="wide")

# Basic Custom Styling
st.markdown("""
    <style>
    .main {background-color: #f7f9fc;}
    h1 {color: #1f3b73;}
    .metric-card {background: white; padding: 20px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); margin-bottom: 20px;}
    </style>
""", unsafe_allow_html=True)

# Application Navigation
st.sidebar.title("🇺🇬 HR System 2026")
page = st.sidebar.radio("Navigation", ["🏢 Employee Directory", "📅 Leave Request", "💼 HR Administration", "💰 Payroll Generator"])

st.sidebar.markdown("---")
st.sidebar.info("As per Uganda Employment Act & URA 2026 Tax Regulations.")

def get_personnel_df():
    conn = get_connection()
    df = pd.read_sql_query("SELECT * FROM Core_Personnel", conn)
    conn.close()
    return df

def get_leave_transactions():
    conn = get_connection()
    df = pd.read_sql_query('''
        SELECT L.Transaction_ID, P.Full_Name, L.Leave_Category, L.Start_Date, L.End_Date, L.Approval_State 
        FROM Leave_Transactions L
        JOIN Core_Personnel P ON L.Employee_ID = P.Employee_ID
        ORDER BY L.Transaction_ID DESC
    ''', conn)
    conn.close()
    return df

if page == "🏢 Employee Directory":
    st.title("Enterprise Directory")
    df = get_personnel_df()
    
    col1, col2, col3 = st.columns(3)
    with col1:
        st.markdown(f"<div class='metric-card'><h3>Total Headcount</h3><h1>{len(df)}</h1></div>", unsafe_allow_html=True)
    with col2:
        monthly_wage_bill = df['Basic_Salary'].sum()
        st.markdown(f"<div class='metric-card'><h3>Base Wage Bill</h3><h1>UGX {monthly_wage_bill:,.0f}</h1></div>", unsafe_allow_html=True)
        
    st.subheader("Personnel Master List")
    st.dataframe(df[['Employee_ID', 'Full_Name', 'Designation', 'Email', 'Joining_Date', 'Basic_Salary']], use_container_width=True)

elif page == "📅 Leave Request":
    st.title("Leave Application Portal")
    
    df = get_personnel_df()
    employees = df['Full_Name'].tolist()
    emp_map = dict(zip(df['Full_Name'], df['Employee_ID']))
    
    st.markdown("### Submit New Request")
    with st.form("leave_form"):
        col1, col2 = st.columns(2)
        with col1:
            selected_emp = st.selectbox("Select Employee", employees)
            leave_cat = st.selectbox("Leave Category", ["Annual Leave (AL)", "Sick Leave (SL)", "Maternity Leave (ML)", "Paternity Leave (PL)"])
        with col2:
            start_date = st.date_input("Start Date", datetime.date(2026, 4, 7))
            end_date = st.date_input("End Date", datetime.date(2026, 4, 30))
            
        submitted = st.form_submit_button("Submit Application")
        
        if submitted:
            if start_date > end_date:
                st.error("End Date must be after Start Date")
            else:
                conn = get_connection()
                c = conn.cursor()
                c.execute('''
                    INSERT INTO Leave_Transactions (Employee_ID, Leave_Category, Start_Date, End_Date, Approval_State)
                    VALUES (?, ?, ?, ?, ?)
                ''', (emp_map[selected_emp], leave_cat, str(start_date), str(end_date), "Pending"))
                conn.commit()
                conn.close()
                st.success(f"Leave application successfully submitted. Status: Pending HR Approval.")

elif page == "💼 HR Administration":
    st.title("HR Administration Dashboard")
    st.subheader("Leave Approvals")
    
    leaves_df = get_leave_transactions()
    
    if leaves_df.empty:
        st.info("No leave requests found.")
    else:
        pending = leaves_df[leaves_df['Approval_State'] == 'Pending']
        if not pending.empty:
            st.warning(f"You have {len(pending)} pending leave requests.")
            for _, row in pending.iterrows():
                with st.expander(f"{row['Full_Name']} - {row['Leave_Category']} ({row['Start_Date']} to {row['End_Date']})"):
                    st.write(f"**Transaction ID:** {row['Transaction_ID']}")
                    col1, col2 = st.columns(2)
                    with col1:
                        if st.button("Approve", key=f"approve_{row['Transaction_ID']}"):
                            conn = get_connection()
                            conn.cursor().execute("UPDATE Leave_Transactions SET Approval_State='Approved' WHERE Transaction_ID=?", (row['Transaction_ID'],))
                            conn.commit(); conn.close()
                            st.experimental_rerun()
                    with col2:
                        if st.button("Reject", key=f"reject_{row['Transaction_ID']}"):
                            conn = get_connection()
                            conn.cursor().execute("UPDATE Leave_Transactions SET Approval_State='Rejected' WHERE Transaction_ID=?", (row['Transaction_ID'],))
                            conn.commit(); conn.close()
                            st.experimental_rerun()
        
        st.subheader("Leave History")
        st.dataframe(leaves_df, use_container_width=True)

elif page == "💰 Payroll Generator":
    st.title("Automated Payroll Engine (URA 2026)")
    st.info("Ensure all attendance for the target month has been ingested before executing the payroll engine. The system automatically calculates PAYE, NSSF (15%), and Local Service Tax (LST).")
    
    col1, col2 = st.columns(2)
    with col1:
        months_list = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"]
        selected_month = st.selectbox("Target Fiscal Month", months_list)
    with col2:
        selected_year = st.selectbox("Fiscal Year", [2026, 2027], index=0)
        
    if st.button("Execute Enterprise Payroll", type="primary"):
        with st.spinner("Calculating gross earnings, NSSF, progressive PAYE, assessing LST bands, and amortizing loan advances..."):
            month_int = months_list.index(selected_month) + 1
            results = run_monthly_payroll(selected_month, month_int, selected_year)
            
            if not results:
                st.warning("Payroll may have already been run for this month, or database is empty.")
            else:
                st.success(f"Successfully processed payroll for {len(results)} employees.")
    
    st.markdown("---")
    st.subheader("Historical Executions")
    conn = get_connection()
    df_payroll = pd.read_sql_query('''
        SELECT P.Full_Name, R.Fiscal_Month, R.Fiscal_Year, R.Gross_Earnings, R.PAYE_Deduction, R.NSSF_Deduction, R.LST_Deduction, R.Advance_Deduction, R.Net_Disbursement
        FROM Payroll_Executions R
        JOIN Core_Personnel P ON R.Employee_ID = P.Employee_ID
        ORDER BY R.Run_ID DESC
    ''', conn)
    conn.close()
    
    if not df_payroll.empty:
        st.dataframe(df_payroll, use_container_width=True)
    else:
        st.write("No historical runs found.")
