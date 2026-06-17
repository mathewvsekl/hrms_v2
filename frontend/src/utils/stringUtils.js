/**
 * stringUtils.js
 * Standardizes display strings across the frontend application.
 */

/**
 * Standardizes a role string to Title Case for UI display.
 * E.g., 'super_admin' -> 'Super Admin', 'hr_manager' -> 'HR Manager'
 */
export const formatRoleForDisplay = (role) => {
  if (!role) return '';
  
  // Normalize variations into a standard snake_case format first
  let normalized = role.toLowerCase().replace(/[\s\-]+/g, '_');
  
  if (normalized === 'superadmin') normalized = 'super_admin';
  if (normalized === 'hrmanager') normalized = 'hr_manager';
  if (normalized === 'hrassistant') normalized = 'hr_assistant';
  if (normalized === 'countrymanager') normalized = 'country_manager';
  
  // Format to Title Case
  return normalized
    .split('_')
    .map(word => {
        if (!word) return '';
        // Special case for HR
        if (word === 'hr') return 'HR';
        return word.charAt(0).toUpperCase() + word.slice(1);
    })
    .join(' ');
};

/**
 * Standardizes 'Organization' to 'Organization' for UI display.
 */
export const normalizeOrgString = (str) => {
    if (!str) return '';
    return str.replace(/organization/gi, match => {
        return match.charAt(0) === 'O' ? 'Organization' : 'organization';
    });
};
