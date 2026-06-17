<?php

require_once __DIR__ . '/../app/Helpers/CustomFieldValidator.php';

use App\Helpers\CustomFieldValidator;

echo "Running CustomFieldValidator Tests...\n";
echo "-------------------------------------\n\n";

// Mock Office Custom Fields Definition
$officeCustomFields = [
    [
        'field_key' => 'tshirt_size',
        'field_name' => 'T-Shirt Size',
        'field_type' => 'dropdown',
        'is_required' => true,
        'field_options' => json_encode(['S', 'M', 'L', 'XL'])
    ],
    [
        'field_key' => 'dietary_restrictions',
        'field_name' => 'Dietary Restrictions',
        'field_type' => 'text',
        'is_required' => false,
        'field_options' => json_encode(['Vegan']) // Testing graceful fallback, options shouldn't matter for text
    ],
    [
        'field_key' => 'join_date',
        'field_name' => 'Join Date in Office',
        'field_type' => 'date',
        'is_required' => true,
        'field_options' => null
    ],
    [
        'field_key' => 'has_laptop',
        'field_name' => 'Has Laptop',
        'field_type' => 'boolean',
        'is_required' => false,
        'field_options' => null
    ],
    [
        'field_key' => 'equipment_ids',
        'field_name' => 'Equipment IDs',
        'field_type' => 'json_array',
        'is_required' => false,
        'field_options' => null
    ]
];

// Test 1: Valid payload
$validPayload = [
    'tshirt_size' => 'M',
    'dietary_restrictions' => 'None',
    'join_date' => '2023-10-01',
    'has_laptop' => true,
    'equipment_ids' => ['LP-1234', 'MON-5678']
];

$result1 = CustomFieldValidator::validatePayload($validPayload, $officeCustomFields);
echo "Test 1 (Valid Payload): ";
if ($result1['is_valid']) {
    echo "PASS\n";
} else {
    echo "FAIL\n";
    print_r($result1['errors']);
}

// Test 2: Invalid dropdown option & Missing required field
$invalidPayload = [
    'tshirt_size' => 'XXL', // Invalid option
    // Missing join_date which is required
];

$result2 = CustomFieldValidator::validatePayload($invalidPayload, $officeCustomFields);
echo "Test 2 (Invalid Dropdown & Missing Required): ";
if (!$result2['is_valid'] && count($result2['errors']) === 2) {
    echo "PASS\n";
} else {
    echo "FAIL\n";
    print_r($result2['errors']);
}

// Test 3: Invalid Date Format and JSON Array format
$badPayload = [
    'tshirt_size' => 'S',
    'join_date' => '10-01-2023', // Wrong format
    'equipment_ids' => 'Not an array string' // Expected array
];

$result3 = CustomFieldValidator::validatePayload($badPayload, $officeCustomFields);
echo "Test 3 (Invalid Formats): ";
if (!$result3['is_valid'] && isset($result3['errors']['join_date']) && isset($result3['errors']['equipment_ids'])) {
    echo "PASS\n";
} else {
    echo "FAIL\n";
    print_r($result3['errors']);
}

echo "\n-------------------------------------\n";
echo "Testing Complete.\n";
