@echo off
cd /d "%~dp0..\.."
echo Starting HRMS V2 PHP Backend API on Port 8000...
echo Enabling Multi-threaded mode (5 workers)...
set PHP_CLI_SERVER_WORKERS=5
"C:\xampp\php.exe" -S 127.0.0.1:8000 -t backend\public
pause
