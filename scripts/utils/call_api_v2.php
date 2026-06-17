<?php
$data = json_encode(['email' => 'mathew.vsekl@gmail.com']);
$options = [
    'http' => [
        'method'  => 'POST',
        'header'  => "Content-Type: application/json\r\n" .
                     "Content-Length: " . strlen($data) . "\r\n",
        'content' => $data,
        'ignore_errors' => true
    ]
];
$context  = stream_context_create($options);
$result = file_get_contents('http://localhost:8000/api/auth/request-otp', false, $context);
echo $result;
