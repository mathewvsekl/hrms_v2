import os
import re

frontend_dir = r"c:\Users\AneeshMathew\HRMS V2\frontend\src"

for root, _, files in os.walk(frontend_dir):
    for file in files:
        if file.endswith('.jsx') or file.endswith('.js'):
            path = os.path.join(root, file)
            with open(path, 'r', encoding='utf-8') as f:
                content = f.read()
            
            new_content = re.sub(
                r"user(?:Role|\?\.role(?:\?\.toUpperCase\(\))?) === ['\"]SUPERADMIN['\"] \|\| user(?:Role|\?\.role(?:\?\.toUpperCase\(\))?) === ['\"]SUPER_ADMIN['\"]",
                "user?.role === 'SuperAdmin'",
                content
            )
            new_content = re.sub(
                r"normalizedRole === ['\"]SUPERADMIN['\"] \|\| normalizedRole === ['\"]SUPER_ADMIN['\"]",
                "user?.role === 'SuperAdmin'",
                new_content
            )
            
            if new_content != content:
                with open(path, 'w', encoding='utf-8') as f:
                    f.write(new_content)
                print("Refactored:", path)
