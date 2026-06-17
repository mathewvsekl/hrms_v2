<?php

namespace App\Helpers;

/**
 * DateHelper
 * 
 * Standardizes date formatting across all HRMS modules.
 */
class DateHelper
{
    /**
     * Convert frontend input (DD/MM/YYYY) to SQL format (Y-m-d)
     */
    public static function toSql(?string $date): ?string
    {
        if (empty($date)) return null;

        // If it's already in Y-m-d format, just return it
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $date;
        }

        // Try to parse DD/MM/YYYY
        if (strpos($date, '/') !== false) {
            $parts = explode('/', $date);
            if (count($parts) === 3) {
                // Determine if it is DD/MM/YYYY or YYYY/MM/DD
                if (strlen($parts[0]) === 4) {
                    return "{$parts[0]}-{$parts[1]}-{$parts[2]}";
                }
                return "{$parts[2]}-{$parts[1]}-{$parts[0]}";
            }
        }

        // Generic fallback using PHP DateTime
        try {
            $dt = new \DateTime($date);
            return $dt->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Convert SQL format (Y-m-d) to Frontend display format (DD/MM/YYYY)
     */
    public static function toFrontend(?string $date): ?string
    {
        if (empty($date)) return null;
        
        try {
            $dt = new \DateTime($date);
            return $dt->format('d/m/Y');
        } catch (\Exception $e) {
            return $date;
        }
    }
}
