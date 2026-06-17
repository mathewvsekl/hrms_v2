<?php
require_once __DIR__ . '/index.php';
$fileName = "payslip_1_2026_4_1779537795.pdf";
$filePath = ROOT_PATH . '/public/uploads/payslips/' . $fileName;
$legacyPath = STORAGE_PATH . '/documents/payslips/' . $fileName;
echo "ROOT_PATH: " . ROOT_PATH . "\n";
echo "STORAGE_PATH: " . STORAGE_PATH . "\n";
echo "New Path: " . $filePath . "\n";
echo "Legacy Path: " . $legacyPath . "\n";
