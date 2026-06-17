import os

files = [
    r'c:\Users\AneeshMathew\HRMS V2\frontend\src\pages\Appraisal\AppraisalCycleList.jsx',
    r'c:\Users\AneeshMathew\HRMS V2\frontend\src\pages\Appraisal\AppraisalForm.jsx',
    r'c:\Users\AneeshMathew\HRMS V2\frontend\src\pages\Appraisal\AppraisalSettings.jsx'
]

for filepath in files:
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()

    # Clean up escaped quotes created by the previous Python script
    content = content.replace(r"\'", "'")
    content = content.replace(r'\"', '"')
    
    with open(filepath, 'w', encoding='utf-8') as f:
        f.write(content)

print('Success')
