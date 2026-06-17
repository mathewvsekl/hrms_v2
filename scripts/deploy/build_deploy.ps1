param (
    [Parameter(Mandatory=$false)]
    [string]$Version = "v3.0.4"
)

$DeployDir = "C:\Users\AneeshMathew\HRMS V2\deploy"
$ReleaseDir = "C:\Users\AneeshMathew\HRMS V2\release"
$VersionDeployDir = Join-Path $DeployDir $Version
$VersionReleaseDir = Join-Path $ReleaseDir $Version

$FullZipCheck = Join-Path $VersionDeployDir "HRMS_V2_$Version`_FULL.zip"
if (Test-Path $FullZipCheck) {
    Write-Error "Build artifact already exists for version $Version. NEVER overwrite a build. Please increment the version number or use a build suffix."
    exit 1
}

# Final Package Staging Area
$StagingDir = Join-Path $VersionDeployDir "staging"
if (Test-Path $StagingDir) { Remove-Item $StagingDir -Recurse -Force }
New-Item -ItemType Directory -Path $StagingDir | Out-Null

# 1. Create Folder Hierarchy
$PublicHtml = New-Item -ItemType Directory -Path (Join-Path $StagingDir "public_html")
$PrivateDir = New-Item -ItemType Directory -Path (Join-Path $StagingDir "private")
$DeployArtifacts = New-Item -ItemType Directory -Path (Join-Path $StagingDir "deployment_artifacts")

$DatabaseDir = New-Item -ItemType Directory -Path (Join-Path $DeployArtifacts "database")
$ScriptsDir = New-Item -ItemType Directory -Path (Join-Path $DeployArtifacts "scripts")

$StorageDir = New-Item -ItemType Directory -Path (Join-Path $PrivateDir "storage")
New-Item -ItemType Directory -Path (Join-Path $StorageDir "logs") | Out-Null
New-Item -ItemType Directory -Path (Join-Path $StorageDir "cache") | Out-Null
New-Item -ItemType Directory -Path (Join-Path $StorageDir "uploads") | Out-Null
$TmpDir = New-Item -ItemType Directory -Path (Join-Path $PrivateDir "tmp")
$PublicDir = New-Item -ItemType Directory -Path (Join-Path $PrivateDir "public")
$ConfigDir = New-Item -ItemType Directory -Path (Join-Path $PrivateDir "config")

# 2. Copy Web Root (public_html)
Write-Host "Packaging Web Root..."
Copy-Item "C:\Users\AneeshMathew\HRMS V2\backend\public\index.php" -Destination $PublicHtml -Force



if (Test-Path "C:\Users\AneeshMathew\HRMS V2\backend\public\.htaccess") { Copy-Item "C:\Users\AneeshMathew\HRMS V2\backend\public\.htaccess" -Destination $PublicHtml -Force }
Copy-Item "C:\Users\AneeshMathew\HRMS V2\backend\app" -Destination $PublicHtml -Recurse -Force
if (Test-Path "C:\Users\AneeshMathew\HRMS V2\vendor") {
   Copy-Item "C:\Users\AneeshMathew\HRMS V2\vendor" -Destination $PublicHtml -Recurse -Force
}
# Config (Externalized config)
$DestConfigDir = $ConfigDir
if (!(Test-Path $DestConfigDir)) { New-Item -ItemType Directory -Path $DestConfigDir | Out-Null }
if (Test-Path "C:\Users\AneeshMathew\HRMS V2\backend\storage\tmp") { Copy-Item "C:\Users\AneeshMathew\HRMS V2\backend\storage\tmp\*" -Destination $TmpDir -Recurse -Force }

# Copy all config files (ProxyPDO, environments, database, config.template)
Get-ChildItem -Path "C:\Users\AneeshMathew\HRMS V2\backend\config\*" -Exclude "config.php" | Copy-Item -Destination $DestConfigDir -Force
Copy-Item "C:\Users\AneeshMathew\HRMS V2\backend\config\config.php" -Destination "$DestConfigDir\config.template.php" -Force

$EnvSuffix = "hevista"
if ($Version -match "-emm") { $EnvSuffix = "emm" }
elseif ($Version -match "-hrms") { $EnvSuffix = "hevista" }

Write-Host "Patching Config for Environment: $EnvSuffix..."
$LocalConfigPath = "C:\Users\AneeshMathew\HRMS V2\backend\config\config.php"
$TargetConfigPath = Join-Path $DestConfigDir "config.template.php"
$ConfigContent = Get-Content $LocalConfigPath
$ConfigContent = $ConfigContent -replace "define\('ACTIVE_ENVIRONMENT', .*\);", "define('ACTIVE_ENVIRONMENT', '$EnvSuffix');"
Set-Content -Path $TargetConfigPath -Value $ConfigContent -Force

# 3. Frontend Build (Vite)
Write-Host "Patching Frontend Environment..."
$FrontendEnvPath = "C:\Users\AneeshMathew\HRMS V2\frontend\.env.production"
$ApiBaseUrl = "https://hrms.anedins.com/api"
if ($Version -match "-emm") { $ApiBaseUrl = "https://emm.anedins.com/api" }

Set-Content -Path $FrontendEnvPath -Value "VITE_API_BASE_URL=$ApiBaseUrl" -Force

Write-Host "Updating Frontend Version in package.json..."
$FrontendPackagePath = "C:\Users\AneeshMathew\HRMS V2\frontend\package.json"
$PkgJson = Get-Content $FrontendPackagePath | ConvertFrom-Json
$PkgJson.version = $Version.Replace('v', '')
$PkgJson | ConvertTo-Json -Depth 10 | Set-Content $FrontendPackagePath -Force

Write-Host "Rebuilding Frontend..."
if (Test-Path "C:\Users\AneeshMathew\HRMS V2\backend\public\assets") {
    Remove-Item -Path "C:\Users\AneeshMathew\HRMS V2\backend\public\assets\*" -Recurse -Force
}
Set-Location "C:\Users\AneeshMathew\HRMS V2\frontend"
npm run build
Set-Location "C:\Users\AneeshMathew\HRMS V2"
if (Test-Path "C:\Users\AneeshMathew\HRMS V2\backend\public") {
   Get-ChildItem -Path "C:\Users\AneeshMathew\HRMS V2\backend\public" -Exclude "uploads" | Copy-Item -Destination $PublicHtml -Recurse -Force
}

# Patch index.php for production public_html layout (Must run AFTER public is copied above!)
$StagedIndex = Join-Path $PublicHtml "index.php"
$IndexContent = Get-Content $StagedIndex -Raw
$IndexContent = $IndexContent.Replace("dirname(__DIR__)", "__DIR__")
Set-Content -Path $StagedIndex -Value $IndexContent -Force

# 4. Database Artifacts
Write-Host "Dumping latest local database schema..."
Set-Location "C:\Users\AneeshMathew\HRMS V2"
php dump_schema.php

Write-Host "Packaging Database..."
if (Test-Path "C:\Users\AneeshMathew\HRMS V2\database\migrations\DATABASE_SCHEMA.sql") {
   Copy-Item "C:\Users\AneeshMathew\HRMS V2\database\migrations\DATABASE_SCHEMA.sql" -Destination (Join-Path $DatabaseDir "schema.sql") -Force
}
if (Test-Path "C:\Users\AneeshMathew\HRMS V2\database\seeds\database_seed.sql") {
   Copy-Item "C:\Users\AneeshMathew\HRMS V2\database\seeds\database_seed.sql" -Destination (Join-Path $DatabaseDir "seed.sql") -Force
}
# Include cumulative migration patches
$MigrationPatchFile = "patch_${Version}.sql"
$MigrationPatchLocalPath = "C:\Users\AneeshMathew\HRMS V2\database\migrations\patches\$MigrationPatchFile"
if (Test-Path $MigrationPatchLocalPath) {
   Copy-Item $MigrationPatchLocalPath -Destination (Join-Path $DatabaseDir $MigrationPatchFile) -Force
   Write-Host "[$Version] Database migration patch included: $MigrationPatchFile"
}


# 5. Metadata and Guides
Write-Host "Generating Release Guides..."

$VersionText = @"
HRMS_VERSION: $Version-release
BUILD_DATE: $((Get-Date -Format "yyyy-MM-dd"))
ENVIRONMENT: production
CORE_MODULES: organization, employee, rbac, attendance, leave_management, appraisal, assets, holidays, notifications, email_system, global_search.
ARCHITECTURE: Decoupled API (V2.2.0 Full Integration)
"@
Set-Content -Path (Join-Path $DeployArtifacts "VERSION") -Value $VersionText -Force

$EnvExample = @"
# HRMS $Version Production Environment Config
APP_ENV=production
APP_DEBUG=false
APP_URL=https://hrms.anedins.com

DB_HOST=localhost
DB_NAME=your_db_name
DB_USER=your_db_user
DB_PASS=your_db_password

# Mail Configuration (SMTP / PHPMailer)
MAIL_DRIVER=smtp
MAIL_HOST=mail.anedins.com
MAIL_PORT=587
MAIL_ENCRYPTION=tls
MAIL_USER=agi-hrms@anedins.com
MAIL_PASS=your_email_password
MAIL_FROM_ADDRESS=agi-hrms@anedins.com
MAIL_FROM_NAME=Avantgarde HRMS
MAIL_LOG_PATH=/domains/hrms.anedins.com/public_html/tmp/mail_log.txt
"@
Set-Content -Path (Join-Path $DeployArtifacts ".env.example") -Value $EnvExample -Force

$ManualConfig = @"
# HRMS $Version Manual Configuration Checklist

1. DATABASE SETUP
   - Create a new UTF-8 database (utf8mb4_unicode_ci).
   - Create a user with full privileges.

2. PHP REQUIREMENTS
   - PHP 8.1+
   - Extensions: PDO_MYSQL, MBSTRING, JSON, OPENSSL, GD, ZIP.

3. SMTP / EMAIL SETUP
   - The system is pre-configured for mail.anedins.com:587.
   - Ensure you update MAIL_PASS in your production config.php.
   - IMPORTANT: The web user MUST have write access to the /tmp folder for mail logging.

4. FILE PERMISSIONS
   - storage/logs: 775/777
   - storage/uploads: 775/777
   - public_html/tmp: 775/777 (for mail_log.txt)
"@
Set-Content -Path (Join-Path $ScriptsDir "manual_config.txt") -Value $ManualConfig -Force

$PostUpload = @"
# HRMS $Version Post-Upload Instructions (DirectAdmin)

1. UPLOAD
   - Upload 'HRMS_V2_$Version`_FULL.zip' directly to your domain root: /domains/hrms.anedins.com/

2. EXTRACT
   - Extract the ZIP. This will perfectly merge without overwriting your uploads or configs:
     - public_html/         (Main web source: app, vendor, frontend assets)
     - private/             (Internal codebase: config, public, storage, tmp)
     - deployment_artifacts/ (Database patches, scripts, versions)

3. INITIALIZE DATABASE
   - Via phpMyAdmin, import 'deployment_artifacts/database/$MigrationPatchFile'.

4. SETUP config/config.php
   - Check 'private/config/'. If it is a new installation, rename 'config.template.php' to 'config.php' and update credentials.
   - (If upgrading, your existing config.php is already safe).

5. VERIFY
   - Access your domain to log in.
"@
Set-Content -Path (Join-Path $ScriptsDir "post_upload_instructions.txt") -Value $PostUpload -Force

# 6. Zipping
Write-Host "Creating Full Package..."
$FullZip = Join-Path $VersionDeployDir "HRMS_V2_$Version`_FULL.zip"
Set-Location $StagingDir
tar.exe -a -c -f $FullZip *
Set-Location "C:\Users\AneeshMathew\HRMS V2"

Write-Host "Creating Standalone public_html.zip..."
$WebZip = Join-Path $VersionDeployDir "public_html.zip"
Set-Location $PublicHtml
tar.exe -a -c -f $WebZip *
Set-Location "C:\Users\AneeshMathew\HRMS V2"

# 7. Mirror Package to Root Release folders
Write-Host "Updating Release Artifacts..."
if (!(Test-Path $VersionReleaseDir)) { New-Item -ItemType Directory -Path $VersionReleaseDir | Out-Null }
Copy-Item (Join-Path $DatabaseDir "schema.sql") -Destination (Join-Path $VersionReleaseDir "database_schema_$Version.sql") -Force
Copy-Item (Join-Path $DatabaseDir "schema.sql") -Destination (Join-Path $VersionReleaseDir "database_schema.sql") -Force

if (Test-Path (Join-Path $DatabaseDir $MigrationPatchFile)) {
   Copy-Item (Join-Path $DatabaseDir $MigrationPatchFile) -Destination (Join-Path $VersionReleaseDir $MigrationPatchFile) -Force
}
# Populate version.json
$VJson = @{
   version    = $Version
   build_date = (Get-Date -Format "yyyy-MM-dd")
   package    = "HRMS_V2_$Version`_FULL.zip"
} | ConvertTo-Json
Set-Content -Path (Join-Path $VersionReleaseDir "version.json") -Value $VJson -Force

# Populate changelog.json
Set-Content -Path (Join-Path $VersionReleaseDir "changelog.json") -Value "[]" -Force

Write-Host ""
Write-Host "=============================================="
Write-Host "  Full Package $Version complete!"
Write-Host "  FULL Package: $FullZip"
Write-Host "  Web-Only ZIP: $WebZip"
Write-Host "=============================================="
