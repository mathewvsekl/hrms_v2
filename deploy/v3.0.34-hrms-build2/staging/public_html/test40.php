<?php
$ch = curl_init('http://localhost:8000/api/appraisal-templates/14');
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['name'=>'test']));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);
$result = curl_exec($ch);
echo "Result without token:\n$result\n";

// With fake token
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer FAKETOKEN'
]);
$result2 = curl_exec($ch);
echo "Result with fake token:\n$result2\n";
