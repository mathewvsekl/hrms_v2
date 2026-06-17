import os
import re

frontend_dir = r"c:\Users\AneeshMathew\HRMS V2\frontend\src"

def process_file(path):
    with open(path, 'r', encoding='utf-8') as f:
        content = f.read()
        
    new_content = content

    # Standardize 'Organisation' to 'Organization' in UI text
    new_content = re.sub(r'Organisation', 'Organization', new_content)
    new_content = re.sub(r'organisation', 'organization', new_content)

    # Standardize specific role display strings in JSX text (not variables/logic yet)
    # This regex is a bit risky but mostly catches standard text inside tags or attributes
    new_content = new_content.replace(">SuperAdmin<", ">Super Admin<")
    new_content = new_content.replace(">HRManager<", ">HR Manager<")
    new_content = new_content.replace("'SuperAdmin'", "'Super Admin'")
    new_content = new_content.replace("'HRManager'", "'HR Manager'")
    
    # But wait, logic might use user.role === 'SuperAdmin'.
    # Because of the backend StringNormalizer, if we send 'Super Admin', it will normalize to 'super_admin'.
    # So we can safely convert logic strings to Title Case too!
    new_content = new_content.replace("userRole === 'SuperAdmin'", "userRole === 'Super Admin'")
    new_content = new_content.replace("role === 'SuperAdmin'", "role === 'Super Admin'")
    new_content = new_content.replace("userRole === 'HRManager'", "userRole === 'HR Manager'")
    new_content = new_content.replace("role === 'HRManager'", "role === 'HR Manager'")
    
    # Also fix arrays used in includes()
    new_content = new_content.replace("['Admin', 'SuperAdmin', 'CountryManager']", "['Admin', 'Super Admin', 'Country Manager']")
    new_content = new_content.replace("['SuperAdmin', 'Admin']", "['Super Admin', 'Admin']")
    
    if new_content != content:
        with open(path, 'w', encoding='utf-8') as f:
            f.write(new_content)
        print("Standardized:", path)

for root, _, files in os.walk(frontend_dir):
    for file in files:
        if file.endswith('.jsx') or file.endswith('.js'):
            process_file(os.path.join(root, file))
