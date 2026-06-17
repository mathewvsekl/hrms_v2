<?php

namespace App\Helpers;

/**
 * ValidationHelper
 * 
 * Centralized utility for input validation.
 */
class ValidationHelper
{
    /**
     * Validate an email address.
     * 
     * @param string $email
     * @return bool
     */
    public static function isEmail($email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Sanitize a string for output (prevent XSS).
     * 
     * @param string $string
     * @return string
     */
    public static function sanitize($string)
    {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Validate a required field.
     * 
     * @param mixed $value
     * @return bool
     */
    public static function isRequired($value)
    {
        return !empty($value) && trim((string)$value) !== '';
    }

    /**
     * Validate an integer.
     * 
     * @param mixed $value
     * @return bool
     */
    public static function isInt($value)
    {
        return filter_var($value, FILTER_VALIDATE_INT) !== false;
    }

    /**
     * Validate a date (YYYY-MM-DD).
     * 
     * @param string $date
     * @return bool
     */
    public static function isDate($date)
    {
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
    
    /**
     * Centralized validation for request data
     * 
     * @param array $data
     * @param array $rules e.g. ['email' => 'required|email', 'id' => 'required|int']
     * @return array [success => bool, errors => array]
     */
    public static function validate(array $data, array $rules)
    {
        $errors = [];
        foreach ($rules as $field => $ruleString) {
            $value = $data[$field] ?? null;
            $ruleList = explode('|', $ruleString);
            
            foreach ($ruleList as $rule) {
                if ($rule === 'required' && !self::isRequired($value)) {
                    $errors[$field][] = "Field is required.";
                } elseif ($rule === 'email' && !empty($value) && !self::isEmail($value)) {
                    $errors[$field][] = "Invalid email format.";
                } elseif ($rule === 'int' && !empty($value) && !self::isInt($value)) {
                    $errors[$field][] = "Must be an integer.";
                } elseif ($rule === 'date' && !empty($value) && !self::isDate($value)) {
                    $errors[$field][] = "Invalid date format (YYYY-MM-DD).";
                }
            }
        }
        
        return [
            'success' => empty($errors),
            'errors' => $errors
        ];
    }
}
