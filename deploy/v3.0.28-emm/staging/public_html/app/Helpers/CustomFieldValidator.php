<?php

namespace App\Helpers;

/**
 * CustomFieldValidator
 * 
 * Engine to validate employee custom fields dynamically against the 
 * defined office_custom_fields rules.
 */
class CustomFieldValidator
{
    /**
     * Dynamically validate given fields against configured rules
     * 
     * @param array $customFieldData Associative array of [field_key => value]
     * @param array $officeCustomFields Array of configured field definitions
     * @return array ['is_valid' => bool, 'errors' => []]
     */
    public static function validatePayload(array $customFieldData, array $officeCustomFields)
    {
        $errors = [];

        foreach ($officeCustomFields as $fieldDef) {
            $fieldKey = $fieldDef['field_key'];
            $fieldType = $fieldDef['field_type'];
            $isRequired = $fieldDef['is_required'];

            $fieldOptions = null;
            if (isset($fieldDef['field_options']) && is_string($fieldDef['field_options'])) {
                $fieldOptions = json_decode($fieldDef['field_options'], true);
            } elseif (isset($fieldDef['field_options']) && is_array($fieldDef['field_options'])) {
                $fieldOptions = $fieldDef['field_options'];
            }

            $value = $customFieldData[$fieldKey] ?? null;

            // Check required constraint
            if ($isRequired && ($value === null || $value === '')) {
                $errors[$fieldKey] = "Field {$fieldDef['field_name']} is required.";
                continue;
            }

            if ($value !== null && $value !== '') {
                // Type Checking
                switch ($fieldType) {
                    case 'number':
                        if (!is_numeric($value)) {
                            $errors[$fieldKey] = "Field {$fieldDef['field_name']} must be a number.";
                        }
                        break;

                    case 'date':
                        $d = \DateTime::createFromFormat('Y-m-d', $value);
                        if (!$d || $d->format('Y-m-d') !== $value) {
                            $errors[$fieldKey] = "Field {$fieldDef['field_name']} must be a valid date (YYYY-MM-DD).";
                        }
                        break;

                    case 'boolean':
                        if (!in_array($value, [true, false, 1, 0, '1', '0', 'true', 'false'], true)) {
                            $errors[$fieldKey] = "Field {$fieldDef['field_name']} must be a boolean.";
                        }
                        break;

                    case 'dropdown':
                        // Fallback securely: If options is not an array, validation should fail predictably
                        if (!is_array($fieldOptions)) {
                            $errors[$fieldKey] = "Configuration error: Missing options for dropdown field {$fieldDef['field_name']}.";
                        } elseif (!in_array($value, $fieldOptions, true)) {
                            $errors[$fieldKey] = "Invalid option selected for {$fieldDef['field_name']}.";
                        }
                        break;

                    case 'json_array':
                        if (!is_array($value)) {
                            $errors[$fieldKey] = "Field {$fieldDef['field_name']} must be a JSON array.";
                        }
                        break;
                }
            }
        }

        return [
            'is_valid' => empty($errors),
            'errors' => $errors
        ];
    }
}
