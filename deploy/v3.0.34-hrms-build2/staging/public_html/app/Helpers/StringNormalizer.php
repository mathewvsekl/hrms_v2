<?php

namespace App\Helpers;

class StringNormalizer {
    /**
     * Converts a role string from the UI into a standardized snake_case format.
     * Examples:
     * "HR Manager" -> "hr_manager"
     * "SUPERADMIN" -> "super_admin"
     * "Admin" -> "admin"
     */
    public static function normalizeRole(string $role): string {
        $role = strtolower(trim($role));
        
        // Edge cases that lack a space
        if ($role === 'superadmin') return 'super_admin';
        if ($role === 'hrmanager') return 'hr_manager';
        if ($role === 'hrassistant') return 'hr_assistant';
        if ($role === 'countrymanager') return 'country_manager';

        // General conversion: spaces and hyphens to underscore
        $role = preg_replace('/[\s\-]+/', '_', $role);
        
        return $role;
    }
}
