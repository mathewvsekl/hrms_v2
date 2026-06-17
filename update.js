const fs = require('fs');
const path = 'c:\\\\Users\\\\AneeshMathew\\\\HRMS V2\\\\frontend\\\\src\\\\pages\\\\Payroll.jsx';
let content = fs.readFileSync(path, 'utf8');

// 1. Add import
if (!content.includes('useAuthStore')) {
    content = content.replace(/import useNotificationStore [^;]+;/, "import useNotificationStore from '../store/useNotificationStore';\nimport useAuthStore from '../store/useAuthStore';");
}

// 2. Add isAdmin inside Payroll component
if (!content.includes('const isAdmin =')) {
    content = content.replace(/const \{ showAlert \} = useNotificationStore\(\);/, "const { showAlert } = useNotificationStore();\n    const { user } = useAuthStore();\n    const isAdmin = user?.role?.toUpperCase() === 'GLOBAL_ADMIN' || user?.role?.toUpperCase() === 'COMPANY_ADMIN';");
}

// 3. Update the table header checkbox
content = content.replace(/checked=\{selectedApprovalIds\.length > 0 && selectedApprovalIds\.length === records\.filter\(r => r\.status === 'Draft' \|\| r\.status === 'Pending Approval' \|\| r\.run_status === 'Draft'\)\.length\}/g, "checked={selectedApprovalIds.length > 0 && selectedApprovalIds.length === records.filter(r => r.status === 'Draft' || r.status === 'Pending Approval' || r.run_status === 'Draft' || isAdmin).length}");

content = content.replace(/setSelectedApprovalIds\(records\.filter\(r => r\.status === 'Draft' \|\| r\.status === 'Pending Approval' \|\| r\.run_status === 'Draft'\)\.map\(r => r\.id\)\);/g, "setSelectedApprovalIds(records.filter(r => r.status === 'Draft' || r.status === 'Pending Approval' || r.run_status === 'Draft' || isAdmin).map(r => r.id));");

// 4. Update the individual row checkbox
content = content.replace(/disabled=\{record\.status === 'Processed'\}/g, "disabled={record.status === 'Processed' && !isAdmin}");

// 5. Wrap the Edit button with status check
content = content.replace(/<button\s*onClick=\{\(e\) => \{ e\.stopPropagation\(\); handleEditClick\(record\); setActiveDropdown\(null\); \}\}[\s\S]*?<Settings size=\{14\} \/> Edit\s*<\/button>/g, `{record.status !== 'Processed' && (
                                                                    <button 
                                                                        onClick={(e) => { e.stopPropagation(); handleEditClick(record); setActiveDropdown(null); }}
                                                                        style={{ width: '100%', textAlign: 'left', background: 'none', border: 'none', padding: '8px 12px', borderRadius: '4px', color: '#64748b', cursor: 'pointer', display: 'flex', alignItems: 'center', gap: '8px', fontSize: '13px' }}
                                                                        onMouseEnter={(e) => e.target.style.background = '#f1f5f9'}
                                                                        onMouseLeave={(e) => e.target.style.background = 'none'}
                                                                    >
                                                                        <Settings size={14} /> Edit
                                                                    </button>
                                                                )}`);

fs.writeFileSync(path, content);
console.log('Script ran successfully');
