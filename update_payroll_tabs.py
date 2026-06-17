import re

filepath = r'c:\Users\AneeshMathew\HRMS V2\frontend\src\pages\Payroll.jsx'
with open(filepath, 'r') as f:
    content = f.read()

target1 = """import PayrollConfig from '../components/PayrollConfig';
import { useReactToPrint } from 'react-to-print';"""
replacement1 = """import PayrollConfig from '../components/PayrollConfig';
import SalaryAdvances from '../components/SalaryAdvances';
import { useReactToPrint } from 'react-to-print';"""
content = content.replace(target1, replacement1)

target2 = """                        style={{ 
                            background: 'none', border: 'none', padding: '10px 4px', fontSize: '14px', fontWeight: '500', cursor: 'pointer',
                            color: activeTab === 'config' ? 'var(--color-rose-gold)' : 'var(--color-text-muted)',
                            borderBottom: activeTab === 'config' ? '2px solid var(--color-rose-gold)' : '2px solid transparent',
                            marginBottom: '-2px'
                        }}
                    >
                        Configuration
                    </button>
                </div>"""
replacement2 = """                        style={{ 
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
content = content.replace(target2, replacement2)

target3 = """            {activeTab === 'config' ? (
                <PayrollConfig 
                    companies={companies} 
                    companyId={companyId} 
                    setCompanyId={setCompanyId} 
                />
            ) : ("""
replacement3 = """            {activeTab === 'config' ? (
                <PayrollConfig 
                    companies={companies} 
                    companyId={companyId} 
                    setCompanyId={setCompanyId} 
                />
            ) : activeTab === 'advances' ? (
                <SalaryAdvances companies={companies} />
            ) : ("""
content = content.replace(target3, replacement3)

with open(filepath, 'w') as f:
    f.write(content)
print("Updated Payroll.jsx with SalaryAdvances tab.")
