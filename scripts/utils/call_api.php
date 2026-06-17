<?php
$ch = curl_init('http://localhost:8000/api/auth/request-otp');
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['email' => 'mathew.vsekl@gmail.com']));
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$result = curl_exec($ch);
curl_close($ch);
echo $result;
