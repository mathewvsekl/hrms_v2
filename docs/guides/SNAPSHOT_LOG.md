# Orion Snapshot Log: HRMS V2 Version Control

This log tracks the "Proper Version Control" snapshots created by the Orion Orchestrator. 
Instead of Git, the system uses **Timestamped Snapshots** to maintain historical integrity and rapid rollback capability.

---

## Snapshot History

| Timestamp | Version | Trigger | Status | Snapshot Path |
| :--- | :--- | :--- | :--- | :--- |
| 2026-05-10_222555 | v2.6.5 | Manual Build | Stable | `/_snapshots_/20260510_222555` |
| 2026-05-12_184300 | v2.6.6 | Config Update | Active | `/_snapshots_/20260512_184300` |

---

## Recovery Procedure
To restore the system to a previous snapshot:
1. Locate the `Snapshot Path` from the table above.
2. Ensure the `_workspace_` is clear of conflicting tasks.
3. Run the Orion Recovery Trigger:
   ```powershell
   # Conceptual command (to be implemented in Orion logic)
   Trigger-Orion-Rollback -SnapshotID "20260510_222555"
   ```
4. Verify `PROJECT_STATE.json` has been reverted.

---

## Retention Policy
- The system maintains the last **10** snapshots automatically.
- Old snapshots are purged by Orion during the `on_init` cycle once the limit is reached.
