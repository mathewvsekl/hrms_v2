# Release Log: HRMS Production v2.8.5
**Release Date:** 2026-05-18
**Baseline:** v2.8.4
**Metadata:** Personal contact options, dynamic attendance report colors, and concurrent performance stress audit.

## Core Enhancements (v2.8.5)
- **Personal Contact Details:** Added dedicated options to store, edit, and view employee personal emails and personal contact numbers on profiles and during the onboarding lifecycle.
- **Dynamic Attendance Colors:** Refactored the Monthly Attendance Report grid and its legend to dynamically render status highlights using real-time custom `color_hex` settings returned from the backend.
- **50-User Concurrency Audit:** Executed serial baseline and parallel multi-threaded load tests for 50 concurrent logged-in users under active RBAC virtualization, compiling extensive latency metrics and database optimizations.
