# Release Notes
**Version**: v1.8.6 (STABLE)
**Date**: 2026-03-28
**Build**: 1806
**Baseline**: v1.8.5

## Changes (v1.8.5 → v1.8.6):

### Database Migration (RUN FIRST)
- Run `migration_patch_v1.8.6.sql` via phpMyAdmin BEFORE deploying backend.
- **Notifications**: Adds `notifications` table for persistent in-app alerts.
- **Appraisal**: Adds `appraisal_approvals` table and cycle deadline columns.
- **Company**: Adds `contact_phone` and `contact_email` to `companies`.

### Backend (Feature Updates)
- **Notification System**: Full integration of in-app alerting with persistent storage.
- **Export Engine**: Enhanced ZIP handling and native CSV streaming fallback.
- **Appraisal Refinements**: New approval workflow steps and cycle-level office filtering.

### Frontend (UI Improvements)
- **Notifications Hub**: New global header component with real-time unread counts and a dedicated notifications screen.
- **Export UI**: Improved error handling and progress feedback in the Data Export modal.
- **Navigation**: Updated routing for new notification views.

### Security
- Zero-trust RBAC enforcement confirmed for all new Notification and Export endpoints.
