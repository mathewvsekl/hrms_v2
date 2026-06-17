Set WshShell = CreateObject("WScript.Shell")
' The 0 at the end tells Windows to run the batch file in hidden mode
WshShell.Run "cmd /c ""C:\Users\AneeshMathew\HRMS V2\scripts\deploy\start_backend.bat""", 0, False