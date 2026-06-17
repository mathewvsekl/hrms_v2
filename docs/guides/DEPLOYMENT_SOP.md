# Deployment Standard Operating Procedure (SOP) - HRMS V2

## Overview
This document outlines the procedure for building and releasing new versions of the HRMS V2 application. To maintain historical integrity, the deployment process follows a "Strict Version Retention" policy.

## Version Retention Policy
> [!IMPORTANT]
> **NEVER delete or overwrite previous versions from the `deploy` or `release` directories.**
> Artifacts must be stored in version-specific subdirectories to allow for rapid rollback and audit tracking. If a rebuild is required, increment the version number or use a distinct build suffix (e.g., `-build2`).
> 
> **CRITICAL RULE**: A deployment plan MUST be presented and approved before any build is executed.

## Configuration Baseline
> [!NOTE]
> **Reference Version: v3.0.0**
> Version 3.0.0 established the stable reference point for externalized deployment on HevistaCP. To bypass strict `open_basedir` restrictions, sensitive folders (`config`, `storage`, `tmp`, `public`) MUST be placed inside the allowed `private/` directory (e.g. `/home/Admin/web/domain.com/private/`). `index.php` automatically detects this secure structure.

## Deployment Steps

### 1. Planning and Approval (CRITICAL)
- **A deployment plan MUST be presented and approved before any build or deployment is executed.**
- Determine the new version number (e.g., `v3.0.2` or `v3.0.1-build2`). Ensure it does not conflict with any existing builds in the `deploy/` or `release/` directories.

### 2. Verification
Before building, ensure the following checks pass:
- All PHP files pass syntax check: `php -l <filename>`
- Frontend builds successfully: `npm run build`
- `DATABASE_SCHEMA.sql` is up to date with the latest table structures.

### 3. Execution
1. The script now accepts a parameter. You do not need to edit `build_deploy.ps1`.
2. If you have database changes, place the SQL patch in the `release/<version>` folder and ensure the script targets it.
3. Run the automated build script from a PowerShell terminal, specifying the version:
```powershell
.\build_deploy.ps1 -Version "vX.X.X"
```

### 4. Artifact Hierarchy
The script generates artifacts in the following exact structure:
- `deploy/<version>/staging/` - The unzipped backend and frontend files ready for deployment.
- `deploy/<version>/HRMS_V2_<version>_FULL.zip` - The full production-ready package.
- `deploy/<version>/public_html.zip` - Standalone web root (frontend) zip.
- `release/<version>/database_schema_<version>.sql` - Full database schema.
- `release/<version>/migration_patch_<previous_version>.sql` - Incremental SQL patch from the previous version.
- `release/<version>/version.json` - Metadata for the update service.

### 5. Production Deployment
1. Upload the `HRMS_V2_<version>.zip` to the production server.
2. Extract the `public_html` contents to the server's `public_html` directory.
3. Extract and move the `config`, `storage`, `public`, and `tmp` directories to the server's `private` directory (one level above `public_html`) to bypass `open_basedir` securely, as standardized in **v3.0.0**.
4. Compare the `database_schema_<version>.sql` with the production database and apply necessary migrations.
5. Verify the `version.json` endpoint reflects the new version.

## Rollback Procedure
To rollback to a previous version:
1. Locate the desired version in `deploy/releases/<previous_version>/`.
2. Extract the `.zip` file of the previous version to `public_html`.
3. Revert any database changes if necessary using the schema files in `release/<previous_version>/`.
