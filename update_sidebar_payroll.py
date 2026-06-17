import re

filepath = r'c:\Users\AneeshMathew\HRMS V2\frontend\src\components\layout\Sidebar.jsx'
with open(filepath, 'r') as f:
    content = f.read()

target1 = """import {
    LayoutDashboard,
    Users,
    Clock,
    Calendar,
    Wallet,
    BarChart,
    FileText,
    Settings,
    UserPlus,
    UserMinus,
    LogOut,
    ChevronRight,
    User,
    Package
} from 'lucide-react';"""
replacement1 = """import {
    LayoutDashboard,
    Users,
    Clock,
    Calendar,
    Wallet,
    BarChart,
    FileText,
    Settings,
    UserPlus,
    UserMinus,
    LogOut,
    ChevronRight,
    User,
    Package,
    Banknote
} from 'lucide-react';"""
content = content.replace(target1, replacement1)

target2 = """        {
            title: 'Finance',
            items: [
                { name: 'Payroll', path: '/payroll', icon: <Wallet size={18} /> },
                { name: 'PaySlip', path: '/payslips', icon: <FileText size={18} /> },
            ]
        },"""
replacement2 = """        {
            title: 'Finance',
            items: [
                { name: 'Payroll', path: '/payroll', icon: <Wallet size={18} /> },
                { name: 'Salary Advances', path: '/salary-advances', icon: <Banknote size={18} /> },
                { name: 'PaySlip', path: '/payslips', icon: <FileText size={18} /> },
            ]
        },"""
content = content.replace(target2, replacement2)

with open(filepath, 'w') as f:
    f.write(content)
print("Updated Sidebar.jsx with SalaryAdvancesPage link.")

filepath_payroll = r'c:\Users\AneeshMathew\HRMS V2\frontend\src\pages\Payroll.jsx'
with open(filepath_payroll, 'r') as f:
    content_payroll = f.read()

target_p1 = """import PayrollConfig from '../components/PayrollConfig';
import SalaryAdvances from '../components/SalaryAdvances';
import { useReactToPrint } from 'react-to-print';"""
replacement_p1 = """import PayrollConfig from '../components/PayrollConfig';
import { useReactToPrint } from 'react-to-print';"""
content_payroll = content_payroll.replace(target_p1, replacement_p1)

target_p2 = """                    <button 
                        className={`tab-btn ${activeTab === 'config' ? 'active' : ''}`} 
                        onClick={() => setActiveTab('config')}
                        style={{ 
                            background: 'none', border: 'none', padding: '10px 4px', fontSize: '14px', fontWeight: '500', cursor: 'pointer',
                            color: activeTab === 'config' ? 'var(--color-rose-gold)' : 'var(--color-text-muted)',
                            borderBottom: activeTab === 'config' ? '2px solid var(--color-rose-gold)' : '2px solid transparent',
                            marginBottom: '-2px'
                        }}
                    >
                        Configuration
                    </button>
                    <button 
                        className={`tab-btn ${activeTab === 'advances' ? 'active' : ''}`} 
                        onClick={() => setActiveTab('advances')}
                        style={{ 
                            background: 'none', border: 'none', padding: '10px 4px', fontSize: '14px', fontWeight: '500', cursor: 'pointer',
                            color: activeTab === 'advances' ? 'var(--color-rose-gold)' : 'var(--color-text-muted)',
                            borderBottom: activeTab === 'advances' ? '2px solid var(--color-rose-gold)' : '2px solid transparent',
                            marginBottom: '-2px'
                        }}
                    >
                        Salary Advances
                    </button>
                </div>"""
replacement_p2 = """                    <button 
                        className={`tab-btn ${activeTab === 'config' ? 'active' : ''}`} 
                        onClick={() => setActiveTab('config')}
                        style={{ 
                            background: 'none', border: 'none', padding: '10px 4px', fontSize: '14px', fontWeight: '500', cursor: 'pointer',
                            color: activeTab === 'config' ? 'var(--color-rose-gold)' : 'var(--color-text-muted)',
                            borderBottom: activeTab === 'config' ? '2px solid var(--color-rose-gold)' : '2px solid transparent',
                            marginBottom: '-2px'
                        }}
                    >
                        Configuration
                    </button>
                </div>"""
content_payroll = content_payroll.replace(target_p2, replacement_p2)

target_p3 = """            {activeTab === 'config' ? (
                <PayrollConfig 
                    companies={companies} 
                    companyId={companyId} 
                    setCompanyId={setCompanyId} 
                />
            ) : activeTab === 'advances' ? (
                <SalaryAdvances companies={companies} />
            ) : ("""
replacement_p3 = """            {activeTab === 'config' ? (
                <PayrollConfig 
                    companies={companies} 
                    companyId={companyId} 
                    setCompanyId={setCompanyId} 
                />
            ) : ("""
content_payroll = content_payroll.replace(target_p3, replacement_p3)

with open(filepath_payroll, 'w') as f:
    f.write(content_payroll)
print("Reverted SalaryAdvances tab from Payroll.jsx.")
