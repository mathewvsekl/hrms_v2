@echo off
set MYSQL_BIN=C:\Program Files\MySQL\bin
echo Syncing: Remote (3307) -> Local (3306)...
"%MYSQL_BIN%\mysqldump" -h 127.0.0.1 -P 3307 -u Admin_admin_anedins_hrms_agi -pHRMS_anedins_2026 Admin_anedins_hrms_agi --no-tablespaces --column-statistics=0 | "%MYSQL_BIN%\mysql" -u root hrms_v2
echo Done.
pause
