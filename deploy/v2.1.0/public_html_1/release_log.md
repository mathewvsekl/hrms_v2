# Release Notes
**Version**: v2.1.0 (STABLE)
**Date**: 2026-03-29
**Build**: 2100
**Baseline**: v2.0.1

## Changes (v2.0.1 → v2.1.0):

### Backend & Infrastructure
- **Email Notification System**: Production-ready SMTP delivery using PHPMailer.
- **DirectAdmin Integration**: Optimized for single-server DirectAdmin environments.
- **PHPMailer Manual Vendor**: Zero-dependency library included in /vendor/phpmailer.
- **MailHelper Rewrite**: Fully asynchronous-compatible logging and sending engine.

### Frontend
- **System Version Update**: Branding corrected to v2.1.0.
- **Notification Triggering**: Integrated real-time alerting with optional email dispatch.

### Configuration
- **SMTP Constants**: New MAIL_* definitions added to config/config.template.php.
- **Security**: Strict exclusion of config.php from build artifacts.

### Versioning
- Corrected production history to v2.1.0 (succeeding v2.0.1).
