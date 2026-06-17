import re

filepath = r'c:\Users\AneeshMathew\HRMS V2\frontend\src\App.jsx'
with open(filepath, 'r') as f:
    content = f.read()

target1 = """import Payslips from './pages/Payslips';"""
replacement1 = """import Payslips from './pages/Payslips';
import SalaryAdvancesPage from './pages/SalaryAdvancesPage';"""
content = content.replace(target1, replacement1)

target2 = """            <Route path="/payslips" element={isAdmin ? <Payslips /> : <Navigate to="/dashboard" replace />} />"""
replacement2 = """            <Route path="/payslips" element={isAdmin ? <Payslips /> : <Navigate to="/dashboard" replace />} />
            <Route path="/salary-advances" element={isAdmin ? <SalaryAdvancesPage /> : <Navigate to="/dashboard" replace />} />"""
content = content.replace(target2, replacement2)

with open(filepath, 'w') as f:
    f.write(content)
print("Updated App.jsx with SalaryAdvancesPage route.")
