<?php
define('PUBLIC_DIR_PATH', 'c:/Users/AneeshMathew/HRMS V2/backend/public');
$normalizedUri = '/public/uploads/logos/test.png';
$subPath = substr($normalizedUri, 7);
$filePath = PUBLIC_DIR_PATH . $subPath;
echo "filePath=" . $filePath . "\n";
$resolvedPath = realpath($filePath);
echo "resolvedPath=" . $resolvedPath . "\n";
var_dump(is_file($resolvedPath));
