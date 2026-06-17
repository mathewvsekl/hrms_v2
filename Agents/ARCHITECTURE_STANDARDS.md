# HRMS V2 Architectural Naming & Casing Standards

To prevent architectural debt, all future development (both human and AI agent-driven) must strictly adhere to the following normalization standards across the entire application stack.

## 1. Role & Module Identifiers
Never use mixed casing for system roles (e.g., avoid `SuperAdmin`, `HRManager`, `hrmanager`).

*   **Database Identifiers & Enums**: Strictly `snake_case` (e.g., `super_admin`, `hr_manager`, `employee`).
*   **Code Variables & Properties**: Strictly `camelCase` (e.g., `superAdmin`, `hrManager`).
*   **UI Display Text (Frontend)**: Strictly `Title Case` with spaces (e.g., `Super Admin`, `HR Manager`).

## 2. Organization Spelling
*   Always use the American English spelling `Organization`.
*   Never use `Organisation` anywhere in the UI, variables, or database schemas.

## 3. String Normalization in Backend
If you are building an endpoint that accepts roles or modules from the frontend, you MUST pass the incoming strings through the normalizer to guarantee backend safety:
```php
$normalizedRole = \App\Helpers\StringNormalizer::normalizeRole($requestData['role']);
```

## 4. Database Queries
All database queries MUST be wrapped safely. Do not leave raw `try/catch` blocks in controllers without the `safeCall` wrapper.
```php
return $this->safeCall(fn() => // your database logic);
```

**MANDATORY ENFORCEMENT:** Any PR, script, or Agent-generated code that violates these rules must be rejected during the code review phase.
