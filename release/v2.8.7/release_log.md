# Release Log: HRMS Production v2.8.7
**Release Date:** 2026-05-20
**Baseline:** v2.8.6
**Metadata:** International country code phone number selection across all employee and company contact fields.

## Core Enhancements (v2.8.7)
- **Country Code Phone Input Component:** Introduced a new reusable `PhoneInput` React component (`src/components/ui/PhoneInput.jsx`) featuring a visual flag dropdown for selecting international country dial codes combined with a local number text input. The component automatically parses and reconstructs stored phone strings in `+[code] [number]` format.
- **Automatic Default Country Detection:** The phone input pre-selects the dial code based on context — the company's registered country for Company Settings, the employee's primary company country for Employee Profile edits, and the logged-in HR user's country for the Onboarding wizard.
- **Company Details Integration:** Replaced the plain `Contact Phone` text input in `CompanyDetail.jsx` with the new `PhoneInput` component, defaulting to the company's registered country code.
- **Employee Profile Integration:** Replaced both `Work Phone` and `Personal Contact Number` plain text inputs in `EmployeeProfile.jsx` edit mode with `PhoneInput`, defaulting to the employee's primary company country.
- **Onboarding Wizard Integration:** Replaced both phone fields in Step 1 (`Personal Data`) of `Onboarding.jsx` with `PhoneInput`, defaulting to the logged-in HR user's country resolved from the `/organization/countries` API.
- **No Database Schema Changes:** Phone numbers are stored as consolidated E.164-like strings (e.g. `+971 501234567`) in the existing `VARCHAR(30)` `phone`, `personal_phone`, and `contact_phone` columns. No schema migration required.
