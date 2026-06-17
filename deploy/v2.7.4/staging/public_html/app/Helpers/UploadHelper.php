<?php

namespace App\Helpers;

/**
 * UploadHelper
 * 
 * Centralized utility for handling file uploads securely and consistently.
 */
class UploadHelper
{
    /**
     * Upload a file and return the stored relative path.
     * 
     * @param array $file The $_FILES element
     * @param string $context 'avatars' or 'documents'
     * @param array $options custom options (allowed_types, max_size)
     * @return array [success => bool, data => string|error_message]
     */
    public static function upload($file, $context = 'documents', $options = [])
    {
        if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'File upload error: ' . self::getErrorMessage($file['error'] ?? UPLOAD_ERR_NO_FILE)];
        }

        // Configuration
        $maxSize = $options['max_size'] ?? 5 * 1024 * 1024; // Default 5MB
        $allowedExtensions = $options['allowed_extensions'] ?? [];
        
        if ($context === 'avatars') {
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
            $uploadSubDir = '/public/uploads/avatars/';
        } else {
            $allowedExtensions = $allowedExtensions ?: ['pdf', 'jpg', 'jpeg', 'png', 'docx', 'xlsx', 'txt', 'csv', 'pptx'];
            $uploadSubDir = '/public/uploads/documents/';
        }

        // 1. Validation: Size
        if ($file['size'] > $maxSize) {
            return ['success' => false, 'message' => 'File too large. Maximum allowed size is ' . ($maxSize / 1024 / 1024) . 'MB.'];
        }

        // 2. Validation: Extension
        $fileInfo = pathinfo($file['name']);
        $extension = strtolower($fileInfo['extension']);
        if (!in_array($extension, $allowedExtensions)) {
            return ['success' => false, 'message' => 'Security Alert: File type [.' . $extension . '] is strictly prohibited.'];
        }

        // 3. Validation: MIME type (Secondary check)
        $mime = null;
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
        } elseif (function_exists('mime_content_type')) {
            $mime = mime_content_type($file['tmp_name']);
        }

        if ($mime) {
            $forbiddenMimes = [
                'application/x-php', 
                'text/x-php', 
                'application/x-executable', 
                'application/x-shellscript',
                'application/javascript',
                'text/javascript'
            ];
            if (in_array($mime, $forbiddenMimes)) {
                return ['success' => false, 'message' => 'Security Alert: Malicious file content detected.'];
            }
        }

        // 4. Prepare Directory
        $uploadDir = BASE_PATH . $uploadSubDir;
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        // 5. Generate Unique Filename
        $prefix = $options['prefix'] ?? ($context === 'avatars' ? 'avatar_' : 'doc_');
        $fileName = $prefix . uniqid() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
        $destination = $uploadDir . $fileName;
        
        // Use a relative path for the DB (starts from project root)
        $dbFilePath = $uploadSubDir . $fileName;

        // 6. Move File
        if (move_uploaded_file($file['tmp_name'], $destination)) {
            return [
                'success' => true, 
                'file_name' => $fileName,
                'file_path' => $dbFilePath,
                'absolute_path' => $destination,
                'extension' => $extension,
                'size' => $file['size'],
                'mime' => $mime
            ];
        }

        return ['success' => false, 'message' => 'Failed to move uploaded file to destination.'];
    }

    /**
     * Delete a file given its DB path
     */
    public static function delete($dbPath)
    {
        if (!$dbPath) return false;
        
        // Convert DB path back to absolute path, handling legacy prefixes
        $cleanPath = str_replace(['/HRMS%20V2/', '/HRMS V2/'], '/', $dbPath);
        $absPath = BASE_PATH . $cleanPath;
        
        if (file_exists($absPath) && is_file($absPath)) {
            return unlink($absPath);
        }
        return false;
    }

    /**
     * Map UPLOAD_ERR codes to human-readable messages
     */
    private static function getErrorMessage($errorCode)
    {
        switch ($errorCode) {
            case UPLOAD_ERR_INI_SIZE:   return 'The uploaded file exceeds the upload_max_filesize directive in php.ini';
            case UPLOAD_ERR_FORM_SIZE:  return 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form';
            case UPLOAD_ERR_PARTIAL:    return 'The uploaded file was only partially uploaded';
            case UPLOAD_ERR_NO_FILE:     return 'No file was uploaded';
            case UPLOAD_ERR_NO_TMP_DIR: return 'Missing a temporary folder';
            case UPLOAD_ERR_CANT_WRITE: return 'Failed to write file to disk';
            case UPLOAD_ERR_EXTENSION:  return 'A PHP extension stopped the file upload';
            default:                    return 'Unknown upload error';
        }
    }
}
