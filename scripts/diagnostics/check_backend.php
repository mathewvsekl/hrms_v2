<?php
$url = 'http://127.0.0.1:8000/';
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 3);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response !== false) {
    echo "Backend is RUNNING on port 8000! HTTP Code: $http_code\n";
} else {
    echo "Backend is NOT running on port 8000.\n";
}
