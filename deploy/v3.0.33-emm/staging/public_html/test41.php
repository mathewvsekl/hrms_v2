<?php
$options = array(
    'http' => array(
        'header'  => "Content-type: application/json\r\n",
        'method'  => 'PUT',
        'content' => json_encode(['name'=>'test']),
        'ignore_errors' => true
    )
);
$context  = stream_context_create($options);
$result = file_get_contents('http://localhost:8000/api/appraisal-templates/14', false, $context);
echo "Result without token:\n$result\n";

$options['http']['header'] .= "Authorization: Bearer FAKETOKEN\r\n";
$context2  = stream_context_create($options);
$result2 = file_get_contents('http://localhost:8000/api/appraisal-templates/14', false, $context2);
echo "Result with fake token:\n$result2\n";
