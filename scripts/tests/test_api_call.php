<?php
$payload = [
    'component_id' => null,
    'company_id' => null,
    'min_amount' => 0,
    'max_amount' => null,
    'percentage' => 10
];

$options = [
    'http' => [
        'header'  => "Content-type: application/json\r\n",
        'method'  => 'POST',
        'content' => json_encode($payload)
    ]
];
$context  = stream_context_create($options);
$result = file_get_contents('http://localhost:8000/api/tax-slabs', false, $context);
if ($result === FALSE) {
    echo "Error making request";
} else {
    echo "Result: " . $result;
}
