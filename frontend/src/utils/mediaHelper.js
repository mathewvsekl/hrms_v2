/**
 * Converts a raw system asset name into an authenticated API endpoint pointer.
 * @param {string} filename - The asset identifier stored in database records.
 * @returns {string} The relative resource tracking path pointing to the API handler.
 */
export const getSecureMediaUrl = (filename) => {
    if (!filename) return '/assets/placeholders/avatar-default.png';
    const token = localStorage.getItem('hrms_auth_token') || '';
    
    // Check if the filename is already a full URL, blob, or already formatted
    if (filename.startsWith('http') || filename.startsWith('blob:') || filename.startsWith('/api/media')) {
        return filename;
    }
    
    return `/api/media?file=${encodeURIComponent(filename)}&token=${encodeURIComponent(token)}`;
};
