# Deployment Reference: HRMS Production Release (v2.6.6)

## Mission Objective
Execute a **ZERO-DOWNTIME, ATOMIC** deployment using the release-based architecture. 
**Agent:** `david_deployment_executor` (via `Agents/Despatcher.mf`)
**Orchestrator:** `Orion` (via `Agents/orion.mf`)

---

## Release Details
- **Current Version:** v2.6.6
- **Release ID:** {{AUTO_GENERATE_TIMESTAMP}}
- **Environment Profile:** `hevista` (Anedins - Last Working)
- **Previous Profile:** `production` (Glow Lady)
- **Deployment Strategy:** Atomic Release (v2.0)
- **Baseline Version:** v2.6.5

---

## Artifact Requirements
The following artifacts must be prepared by `build_deploy.ps1` before deployment:
1. **Code Package:** `/deploy/v2.6.6/HRMS_V2_v2.6.6_FULL.zip`
2. **Version File:** `/release/v2.6.6/version.json`
3. **Database Migration:** `/release/v2.6.6/database_schema_v2.6.6.sql`
4. **Staging Directory:** `/deploy/v2.6.6/staging/`

---

## Phase 1: Pre-Deploy Validation (MANDATORY)
- [ ] Verify all required artifacts exist in the versioned directory.
- [ ] Validate ZIP integrity (`tar.exe` or `checksum.txt`).
- [ ] Ensure no forbidden paths are included in the package (`config/config.php` should be preserved on server).
- [ ] Validate `version.json` build date corresponds to today.

---

## Phase 2: Execution Rules
1. **Never** deploy into `public_html` directly.
2. **Never** overwrite server-side `config/` or `.env` files.
3. **Never** run destructive SQL without a backup.
4. **Always** use atomic release switching (symlink or isolated folder).
5. **Always** log every step to `deployment.log`.

---

## Phase 3: Failure & Rollback
If any failure occurs during extraction or validation:
1. **Immediately** rollback to the previous stable release ID.
2. **Log** the exact point of failure in `error.log`.
3. **Notify** the administrator (Aneesh Mathew).
4. **Escalate** to `Nova_System_Architect` for root cause analysis.

---

## Report Back to ORION
Upon completion, the deployment agent must report:
- Final deployment status (Success/Failure).
- Active Release ID.
- Migration status.
- Any performance warnings or risks.
