/**
 * Date Utility Functions
 */

import useAuthStore from '../store/useAuthStore';

/**
 * Returns the user's office timezone, falling back to local system timezone
 */
export const getTimeZone = () => {
    return useAuthStore.getState()?.user?.timezone || Intl.DateTimeFormat().resolvedOptions().timeZone;
};

/**
 * Parses date strings safely, ensuring MySQL UTC timestamps are treated as UTC
 */
const parseToUTC = (date) => {
    if (!date) return null;
    if (date instanceof Date) return date;
    if (typeof date === 'string') {
        // If it looks like a MySQL timestamp "YYYY-MM-DD HH:mm:ss" missing Z
        if (/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(date) || /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/.test(date)) {
            date = date.replace(' ', 'T') + 'Z';
        }
    }
    return new Date(date);
};

/**
 * Formats a date string (ISO or other) to "DD/MM/YYYY" (e.g., 25/01/2001)
 * @param {string|Date} date 
 * @returns {string}
 */
export const formatDate = (date) => {
    if (!date) return '';
    const d = parseToUTC(date);
    if (!d || isNaN(d.getTime())) return date; // Return original if invalid

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        timeZone: getTimeZone()
    }).format(d);
};

/**
 * Formats a date string to "DD/MM/YYYY"
 * @param {string|Date} date 
 * @returns {string}
 */
export const formatNumericDate = (date) => {
    return formatDate(date);
};

/**
 * Formats a date string to "DD/MM/YYYY HH:mm"
 * @param {string|Date} date 
 * @returns {string}
 */
export const formatDateTime = (date) => {
    if (!date) return '';
    const d = parseToUTC(date);
    if (!d || isNaN(d.getTime())) return date;

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
        timeZone: getTimeZone()
    }).format(d).replace(',', '');
};

/**
 * Formats a date string to "HH:mm"
 * @param {string|Date} date 
 * @returns {string}
 */
export const formatTime = (date) => {
    if (!date) return '';
    const d = parseToUTC(date);
    if (!d || isNaN(d.getTime())) return date;

    return new Intl.DateTimeFormat('en-GB', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
        timeZone: getTimeZone()
    }).format(d);
};

/**
 * Parses a date string and returns a Date object
 * Useful for handling various input formats if needed
 */
export const parseDate = (dateStr) => {
    if (!dateStr) return null;
    return parseToUTC(dateStr);
};
