$ErrorActionPreference = "Stop"
$Version = "v3.0.4"
$ProjectRoot = "c:\Users\AneeshMathew\HRMS V2"
$ReleaseDir = "$ProjectRoot\release\$Version"
$ZipFile = "$ProjectRoot\hrms_$Version`_release_package.zip"

Write-Host "Creating release directory at $ReleaseDir..."
if (Test-Path $ReleaseDir) {
    Remove-Item -Recurse -Force $ReleaseDir
}
New-Item -ItemType Directory -Path $ReleaseDir | Out-Null

Write-Host "Copying frontend build..."
if (Test-Path "$ProjectRoot\public_react_dist") {
    Copy-Item -Recurse -Force "$ProjectRoot\public_react_dist" "$ReleaseDir\public_react_dist"
} else {
    Write-Warning "Frontend dist folder not found!"
}

Write-Host "Copying backend files..."
$FoldersToCopy = @("api", "app", "config", "database", "patches", "scripts", "vendor", "storage", "public")
foreach ($folder in $FoldersToCopy) {
    if (Test-Path "$ProjectRoot\$folder") {
        Copy-Item -Recurse -Force "$ProjectRoot\$folder" "$ReleaseDir\$folder"
    }
}

$FilesToCopy = @("index.php", "router.php", ".htaccess", "version.json", "version.txt", "apply_patch_$Version.php")
foreach ($file in $FilesToCopy) {
    if (Test-Path "$ProjectRoot\$file") {
        Copy-Item -Force "$ProjectRoot\$file" "$ReleaseDir\$file"
    }
}

Write-Host "Creating zip archive..."
if (Test-Path $ZipFile) {
    Remove-Item -Force $ZipFile
}
Set-Location $ReleaseDir
tar.exe -a -c -f $ZipFile *
Set-Location $ProjectRoot

Write-Host "Release package created successfully at $ZipFile"
