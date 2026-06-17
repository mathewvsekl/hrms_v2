import os
import re

backend_dir = r"c:\Users\AneeshMathew\HRMS V2\backend"

for root, _, files in os.walk(backend_dir):
    for file in files:
        if file.endswith(".php"):
            path = os.path.join(root, file)
            with open(path, "r", encoding="utf-8") as f:
                content = f.read()

            new_content = content
            
            # Map specific hasAnyRole checks to the new scope helpers
            
            # Global Admin Checks
            new_content = re.sub(
                r"\$this->hasAnyRole\(\s*\[\s*['\"](?:ADMIN|SUPERADMIN|SUPER_ADMIN|HRMANAGER|HR_MANAGER|HR MANAGER)['\"](?:,\s*['\"](?:ADMIN|SUPERADMIN|SUPER_ADMIN|HRMANAGER|HR_MANAGER|HR MANAGER)['\"])*\s*\]\s*\)",
                "$this->isGlobalAdmin()",
                new_content,
                flags=re.IGNORECASE
            )

            # Global or Regional Admin Checks
            new_content = re.sub(
                r"\$this->hasAnyRole\(\s*\[\s*['\"](?:ADMIN|SUPERADMIN|SUPER_ADMIN|HRMANAGER|HR_MANAGER|COUNTRYMANAGER|COUNTRY_MANAGER|COUNTRY MANAGER)['\"](?:,\s*['\"](?:ADMIN|SUPERADMIN|SUPER_ADMIN|HRMANAGER|HR_MANAGER|COUNTRYMANAGER|COUNTRY_MANAGER|COUNTRY MANAGER)['\"])*\s*\]\s*\)",
                "$this->hasGlobalOrRegionalScope()",
                new_content,
                flags=re.IGNORECASE
            )

            # Entity Admin Checks (Multi-Office)
            new_content = re.sub(
                r"\$this->hasAnyRole\(\s*\[\s*['\"](?:Admin|HRManager|HR_MANAGER|HR MANAGER|HRAssistant|HR_ASSISTANT|HR ASSISTANT|CountryManager|COUNTRY MANAGER|COUNTRY_MANAGER|COUNTRYMANAGER)['\"](?:,\s*['\"](?:Admin|HRManager|HR_MANAGER|HR MANAGER|HRAssistant|HR_ASSISTANT|HR ASSISTANT|CountryManager|COUNTRY MANAGER|COUNTRY_MANAGER|COUNTRYMANAGER)['\"])*\s*\]\s*\)",
                "$this->hasEntityScope()",
                new_content,
                flags=re.IGNORECASE
            )

            if new_content != content:
                with open(path, "w", encoding="utf-8") as f:
                    f.write(new_content)
                print(f"Refactored: {path}")
