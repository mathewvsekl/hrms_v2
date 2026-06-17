# HRMS V2: Automated Standard Operating Procedure (SOP) for Deployments

> [!CAUTION]
> **CRITICAL AI AGENT DIRECTIVE:** 
> **NEVER** run the deployment script (`build_deploy.ps1`) or initiate any form of deployment automatically. 
> Fixing a bug, completing a feature, or resolving an issue does **NOT** mean you should deploy. 
> You MUST ONLY execute deployment commands if the USER explicitly says "Deploy this to production" or clearly requests a deployment in their current prompt. If the user only asks to fix a bug, fix it locally and STOP.

This Standard Operating Procedure outlines the correct workflow for deploying updates to the following environments:
- **Production Live:** `https://hrms.anedins.com/` (Requires `-hrms` suffix)
- **Production Test:** `https://emm.anedins.com/` (Requires `-emm` suffix)

It strictly relies on the internal `build_deploy.ps1` automation script which correctly handles building, versioning, staging, and archiving. **DO NOT attempt manual builds or zipping.**

---

## 0. Pre-Deployment: Version Check & Plan Approval

Before running the deployment script, you must explicitly verify the version and get approval.

### 1. Compare Environments & Compile Changes (CRITICAL RULE)
- You **MUST** explicitly cross-check the absolute latest deployed version folder in `C:\Users\AneeshMathew\HRMS V2\release` (regardless of whether it ends in `-emm` or `-hrms`) to establish the current base version. 
- **Do NOT use Git** to determine the production state; rely entirely on the `release` folder history.
- Compile a list of ALL changes. You MUST verify that every single database column, table, or configuration change has a corresponding `ALTER TABLE` or `INSERT` in your patch file. DO NOT rely on memory.

### 2. Generate and Approve Deployment Plan
- Draft a Deployment Plan document for the target version.
- **Stop and Present:** You must present this plan to the relevant stakeholder/lead for explicit approval before executing any further steps.

### 3. Verify Database Patch Location
- If there are database changes, they MUST be placed in: 
  `database/migrations/patches/patch_v[VERSION].sql` (e.g., `patch_v3.0.29-hrms.sql`)
- The automation script strictly looks for this filename pattern.

### 4. Versioning & Repair Build Rules (CRITICAL)
- **New Version Deployment:** Determine the latest base version in the `release` folder. Increment the version number and append the requested production type suffix (e.g., `v3.0.29-emm` or `v3.0.29-hrms`).
- **Repair Builds:** If fixing a broken deployment, you must **NEVER change the base version number**. Instead, append or increment a build suffix on the target type (e.g., `v3.0.29-hrms-build1` becomes `v3.0.29-hrms-build2`).
- You must NEVER overwrite an existing build folder. The script will abort if a folder already exists.

---

## 1. Execute the Build & Deploy Automation

Once approved, you will use the provided PowerShell deployment script. This script automatically updates the `package.json` version, builds the frontend, patches production configurations, dumps the absolute latest database schema, and generates the final archives.

### 1. Run the Script
- Open PowerShell.
- Navigate to the scripts directory:
  ```powershell
  cd "C:\Users\AneeshMathew\HRMS V2\scripts\deploy"
  ```
- Run the script with the new version number:
  ```powershell
  .\build_deploy.ps1 -Version "v3.0.29-hrms"
  ```
*(Note: If you run it without a version, it may default to an older version. Always specify `-Version`)*

### 2. Verify Outputs
The script will generate output in two places:
- `C:\Users\AneeshMathew\HRMS V2\deploy\v[VERSION]\`: Contains the staging files, the Web-Only ZIP (`public_html.zip`), and the Full Package (`HRMS_V2_v[VERSION]_FULL.zip`).
- `C:\Users\AneeshMathew\HRMS V2\release\v[VERSION]\`: Contains the specific release artifacts for your records. Ensure the following 5 files are present:
  - `changelog.json` **(Action Required: Open this file and manually enter your release notes)**
  - `database_schema.sql`
  - `database_schema_v[VERSION].sql`
  - `patch_v[VERSION].sql`
  - `version.json`

---

## 2. Production Server Upload

HRMS V2 uses a secure, split-directory architecture.

### 1. Upload the Full Package
- Upload the generated `HRMS_V2_v[VERSION]_FULL.zip` directly to your domain root (e.g., `/domains/hrms.anedins.com/`).

### 2. Extract
- Extract the ZIP on the server. The script is designed to perfectly merge the `public_html/` and `private/` folders without overwriting critical uploads or server configurations.

---

## 3. Database Migration (Execute on Live)

**IMPORTANT:** The database schema must be updated on the live server via the patch generated in the deployment artifacts.

### 1. Locate the Patch
- Find the generated patch file in the local release folder:
  `C:\Users\AneeshMathew\HRMS V2\release\v[VERSION]\patch_v[VERSION].sql`
  *(Example: `C:\Users\AneeshMathew\HRMS V2\release\v3.0.29-hrms\patch_v3.0.29-hrms.sql`)*

### 2. Execute via phpMyAdmin
- Log into the production server's phpMyAdmin.
- Go to the SQL tab and execute the patch file directly against the `hrms_v2` database.

---

## 4. Post-Deployment Verification

1. **Clear Caches:** Purge the Cloudflare/CDN cache to ensure the new React bundles are served.
2. **Health Check:** Load the application, verify there are no console errors, and test a core API route to confirm backend communication with the updated database schema.
