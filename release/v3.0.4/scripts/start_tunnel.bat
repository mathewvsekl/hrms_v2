@echo off
echo Starting SSH Tunnel to DigitalOcean Database...
echo Connecting as: root@159.89.7.240
echo Mapping: 127.0.0.1:3307 (Local) -> 127.0.0.1:3306 (Remote)
echo -------------------------------------------------------
echo [INFO] Using port 3307 to avoid conflict with local MySQL.
echo [INFO] Using SSH key: C:\Users\AneeshMathew\.ssh\id_new_key
echo -------------------------------------------------------
ssh -p 2222 -i "C:\Users\AneeshMathew\.ssh\id_new_key" -L 3307:127.0.0.1:3306 root@159.89.7.240
pause
