import subprocess
result = subprocess.run(['php', '-l', 'app/Controllers/AppraisalController.php'], capture_output=True, text=True)
print(result.stdout)
print(result.stderr)
