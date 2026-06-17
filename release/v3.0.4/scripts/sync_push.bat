@echo off
set MYSQL_BIN="C:\Program Files\MySQL\bin"
echo ============================================================
echo WARNING: THIS WILL OVERWRITE THE PRODUCTION DATABASE
echo WITH YOUR LOCAL DATA.
echo ============================================================
echo.
echo Ensure your SSH Tunnel (3307) is active.
set /p confirm="Are you sure you want to proceed? (Y/N): "
if /i "%confirm%" neq "Y" goto cancel

echo Pushing: Local (3306) -> Production (3307)...
%MYSQL_BIN%\mysqldump -u root hrms_v2 --no-tablespaces --column-statistics=0 | %MYSQL_BIN%\mysql -h 127.0.0.1 -P 3307 -u Admin_admin_anedins_hrms_agi -pHRMS_anedins_2026 Admin_anedins_hrms_agi

echo.
echo Push Complete.
pause
exit

:cancel
echo Push cancelled.
pause
