import os
import re

frontend_dir = r"c:\Users\AneeshMathew\HRMS V2\frontend\src"

for root, _, files in os.walk(frontend_dir):
    for file in files:
        if file.endswith('.jsx') or file.endswith('.js'):
            path = os.path.join(root, file)
            with open(path, 'r', encoding='utf-8') as f:
                content = f.read()

            new_content = content
            
            # Remove toUpperCase() from role normalization
            new_content = re.sub(
                r"const normalizedRole = user\?\.role\?\.toUpperCase\(\) \|\| '';",
                "const normalizedRole = user?.role || '';",
                new_content
            )
            new_content = re.sub(
                r"const userRole = user\?\.role\?\.toUpperCase\(\);",
                "const userRole = user?.role;",
                new_content
            )

            # Replace comparisons
            new_content = new_content.replace("normalizedRole === 'EMPLOYEE'", "normalizedRole === 'Employee'")
            new_content = new_content.replace("normalizedRole === 'SUPERADMIN'", "normalizedRole === 'SuperAdmin'")
            new_content = new_content.replace("normalizedRole === 'SUPER_ADMIN'", "normalizedRole === 'SuperAdmin'")
            new_content = new_content.replace("normalizedRole === 'ADMIN'", "normalizedRole === 'Admin'")
            new_content = new_content.replace("normalizedRole === 'HRMANAGER'", "normalizedRole === 'HRManager'")
            new_content = new_content.replace("normalizedRole === 'COUNTRYMANAGER'", "normalizedRole === 'CountryManager'")
            new_content = new_content.replace("normalizedRole === 'HR_MANAGER'", "normalizedRole === 'HRManager'")
            new_content = new_content.replace("normalizedRole === 'COUNTRY_MANAGER'", "normalizedRole === 'CountryManager'")

            new_content = new_content.replace("userRole === 'EMPLOYEE'", "userRole === 'Employee'")
            new_content = new_content.replace("userRole === 'SUPERADMIN'", "userRole === 'SuperAdmin'")
            new_content = new_content.replace("userRole === 'SUPER_ADMIN'", "userRole === 'SuperAdmin'")
            new_content = new_content.replace("userRole === 'ADMIN'", "userRole === 'Admin'")
            new_content = new_content.replace("userRole === 'HRMANAGER'", "userRole === 'HRManager'")
            new_content = new_content.replace("userRole === 'COUNTRYMANAGER'", "userRole === 'CountryManager'")
            new_content = new_content.replace("userRole === 'HR_MANAGER'", "userRole === 'HRManager'")
            new_content = new_content.replace("userRole === 'COUNTRY_MANAGER'", "userRole === 'CountryManager'")
            
            new_content = new_content.replace("['ADMIN', 'SUPERADMIN', 'COUNTRYMANAGER'].includes(user?.role?.toUpperCase())", "['Admin', 'SuperAdmin', 'CountryManager'].includes(user?.role)")

            if new_content != content:
                with open(path, 'w', encoding='utf-8') as f:
                    f.write(new_content)
                print("Refactored:", path)
