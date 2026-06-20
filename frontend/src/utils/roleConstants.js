/**
 * roleConstants.js — Single Source of Truth for Role IDs (Frontend)
 * 
 * Must stay in sync with backend App\Helpers\RoleConstants.
 * All role-based UI decisions should use `user.role_id` with these constants.
 */

export const ROLE_IDS = Object.freeze({
  SUPER_ADMIN:     1,
  ADMIN:           2,
  HR_MANAGER:      3,
  COUNTRY_MANAGER: 4,
  HR_ASSISTANT:    5,
  EMPLOYEE:        6,
});

// ─── Role Groups ───

/** SuperAdmin + Admin — unrestricted global access */
export const GLOBAL_ADMINS = [ROLE_IDS.SUPER_ADMIN, ROLE_IDS.ADMIN];

/** SuperAdmin + Admin + HR Manager — management-tier access */
export const MANAGEMENT = [ROLE_IDS.SUPER_ADMIN, ROLE_IDS.ADMIN, ROLE_IDS.HR_MANAGER];

/** All roles that can see across multiple offices/companies */
export const MULTI_OFFICE = [
  ROLE_IDS.SUPER_ADMIN, ROLE_IDS.ADMIN, ROLE_IDS.HR_MANAGER,
  ROLE_IDS.HR_ASSISTANT, ROLE_IDS.COUNTRY_MANAGER,
];

/** All administrative roles (everything except Employee) */
export const ALL_ADMIN = [
  ROLE_IDS.SUPER_ADMIN, ROLE_IDS.ADMIN, ROLE_IDS.HR_MANAGER,
  ROLE_IDS.COUNTRY_MANAGER, ROLE_IDS.HR_ASSISTANT,
];

// ─── Helper Functions ───

/** Check if role ID belongs to any administrative role */
export const isAdmin       = (roleId) => ALL_ADMIN.includes(roleId);

/** Check if role ID is a global admin (SuperAdmin, Admin, HR Manager) */
export const isGlobalAdmin = (roleId) => MANAGEMENT.includes(roleId);

/** Check if role ID is SuperAdmin */
export const isSuperAdmin  = (roleId) => roleId === ROLE_IDS.SUPER_ADMIN;

/** Check if role ID is Employee */
export const isEmployee    = (roleId) => roleId === ROLE_IDS.EMPLOYEE;

/** Check if role ID is exempt from session timeout */
export const isSessionExempt = (roleId) => GLOBAL_ADMINS.includes(roleId);

/** Check if role ID has multi-office visibility */
export const isMultiOffice = (roleId) => MULTI_OFFICE.includes(roleId);

// ─── View Mode Constants (replacing magic strings) ───
export const VIEW_MODE = Object.freeze({
  ADMIN: 'admin',
  EMPLOYEE: 'employee',
});
