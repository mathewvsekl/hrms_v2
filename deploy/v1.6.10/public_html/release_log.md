# Release Notes
**Version**: $Version (SECURITY_HARDENED)
**Date**: $((Get-Date -Format "yyyy-MM-dd"))

## 🛠 Fixes & Features (v1.6.11):
- **Security Hardening**: Removed all hardcoded emergency backdoors and bypass tokens as per Noah Logic Audit.
- **Multi-Tenancy Isolation**: Enforced mandatory company_id scoping in Employee and Leave controllers to prevent cross-company data visibility.
- **Cumulative Features**: Includes all v1.6.10 features (Dynamic Dashboard, Regional Profile Fields, Multi-Company Assets, Flag Mapping).
- **Stability**: Robust RBAC session validation and improved error handling in Auth middleware.
