$ErrorActionPreference = "Stop"
$Version = "v3.0.1"
$ProjectRoot = "c:\Users\AneeshMathew\HRMS V2"
$DeployDir = "$ProjectRoot\deploy\$Version"
$StagingDir = "$DeployDir\staging"

Write-Host "Cleaning up old deploy dir..."
if (Test-Path $DeployDir) {
    Remove-Item -Recurse -Force $DeployDir
}
New-Item -ItemType Directory -Path $StagingDir -Force | Out-Null

Write-Host "Copying backend folders to staging..."
$FoldersToCopy = @("config", "database", "public", "scripts", "storage", "tmp", "app", "api", "vendor")
# Wait, I noticed 'app', 'api', 'vendor' were not in list_dir output for staging?
# Let's check exactly what the user had in v3.0.0/staging
