# Avantgarde HRMS V2 - Enterprise Edition

## Project Overview
HRMS V2 is a decoupled PHP/React application designed for multi-company attendance, payroll, and employee management. It features a robust orchestration layer powered by the **Orion Project Orchestrator**.

---

## Architecture & Version Control
This project does **not** use Git for local version control. Instead, it utilizes a proprietary **Snapshotting System** managed by Orion.

- **Snapshot Directory:** `./_snapshots_/` (Contains timestamped backups)
- **Workspace Directory:** `./_workspace_/` (Isolated areas for agent tasks)
- **State File:** `PROJECT_STATE.json` (The source of truth for the project version and active modules)

### Core Components
- **Backend:** PHP 8.1+ (API-driven)
- **Frontend:** React 19 + Vite (located in `/frontend`)
- **Orchestration:** YAML-based agent manifests (located in `/Agents`)

---

## Deployment Workflow
The deployment process is governed by the `DEPLOYMENT_SOP.md` and tracked in `DEPLOYMENT_REFER.md`.

### 1. Build and Package
Run the PowerShell build script to generate versioned artifacts:
```powershell
.\build_deploy.ps1
```

### 2. Synchronization
Use the batch scripts to synchronize the local environment with the production server:
- `scripts/sync_push.bat`: Pushes local changes to production.
- `scripts/sync_pull.bat`: Pulls production data to local.

### 3. Release Management
Versions are archived in the `/release` and `/deploy` folders. **Never delete previous versions.**

---

## Agent Roles
- **Orion:** Project Orchestrator & Snapshot Manager.
- **David:** Deployment Executor (manages DirectAdmin releases).
- **Noah:** Logic Auditor (ensures business rule compliance).
- **Aria:** Security & RBAC Guard.

---

## Contact
**Project Lead:** Aneesh Mathew
**Agent Intelligence:** Antigravity (Advanced Coding Agent)
