<?php

namespace App\Helpers;

/**
 * DataSanitizer
 * Recursively scrubs sensitive data from arrays before logging.
 */
class DataSanitizer
{
    private static $sensitiveKeys = [
        'password',
        'password_hash',
        'bank_account',
        'routing_number',
        'national_id',
        'ssn',
        'credit_card',
        'cvv',
        'token',
        'secret',
        'salary',
        'base_pay',
        'net_pay',
        'tax_id',
        'pan_number',
        'tin_number',
        'passport_number',
        'medical_record',
        'disability_status'
    ];

    /**
     * Recursively scrub sensitive keys from an array.
     */
    public static function sanitize(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = self::sanitize($value);
            } else {
                foreach (self::$sensitiveKeys as $sensitiveKey) {
                    if (stripos($key, $sensitiveKey) !== false) {
                        $data[$key] = '[REDACTED]';
                        break;
                    }
                }
            }
        }
        return $data;
    }
}
