# HRMS V2.0.0 Release Log - 2026-03-29

## Overview
This is a major production release (V2.0.0) of the HRMS system, featuring a refactored API core, professional folder structure, and complete fresh database schema.

## Major Changes
- **API Decoupling**: Extracted all routing logic into `routes/api.php` for better performance and maintainability.
- **Production Entry Point**: Updated `index.php` to serve React frontend from `public_html` root and handle `/api/` requests via the new router.
- **Enhanced Security**: Added production `.htaccess` with security headers and forced-HTTPS capabilities.
- **Storage Separation**: Moved `storage/` out of `public_html/` to prevent sensitive log/cache access.
- **Fresh Install Package**: Included a complete `database/schema.sql` (47 tables) and consolidated `seed.sql`.

## Build Environment
- **Node.js**: Built with `npm run build`
- **PHP**: Optimized for Apache/DirectAdmin
- **Architecture**: Separated Web Root (`public_html`) from App Storage (`storage`).

## Verification
- Verified index.php handles both API and Frontend routing.
- Verified manual configuration guides are accurate.
