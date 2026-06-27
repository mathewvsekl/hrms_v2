/**
 * Converts a raw system asset name into an authenticated API endpoint pointer.
 * @param {string} filename - The asset identifier stored in database records.
 * @returns {string} The relative resource tracking path pointing to the API handler.
 */
export const getSecureMediaUrl = (filename) => {
    if (!filename) return '/assets/placeholders/avatar-default.png';
    const token = localStorage.getItem('hrms_auth_token') || '';
    
    // Check if the filename is already a full URL or blob
    if (filename.startsWith('http') || filename.startsWith('blob:')) {
        return filename;
    }
    
    // If it's already an API endpoint, just append the token
    if (filename.startsWith('/api/')) {
        const separator = filename.includes('?') ? '&' : '?';
        return `${filename}${separator}token=${encodeURIComponent(token)}`;
    }
    
    return `/api/media?file=${encodeURIComponent(filename)}&token=${encodeURIComponent(token)}`;
};
