<?php
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator('C:\Users\AneeshMathew\HRMS V2\backend\app'));
foreach ($files as $file) {
    if ($file->getExtension() === 'php') {
        $content = file_get_contents($file->getPathname());
        preg_match_all('/function\s+([a-zA-Z0-9_]+)\s*\([^)]*\)\s*\{([^}]*)\}/s', $content, $matches);
        foreach ($matches[1] as $idx => $funcName) {
            $body = $matches[2][$idx];
            if (strpos($body, '$db->') !== false) {
                if (strpos($body, '$db =') === false && strpos($body, 'global $db') === false) {
                    // Check if it's passed as param
                    preg_match('/function\s+' . $funcName . '\s*\(([^)]*)\)/', $content, $paramMatches);
                    if (strpos($paramMatches[1] ?? '', '$db') === false) {
                        echo "Suspicious \$db usage in " . $file->getFilename() . " -> function $funcName\n";
                    }
                }
            }
            if (strpos($body, '$stmt->') !== false) {
                if (strpos($body, '$stmt =') === false) {
                    echo "Suspicious \$stmt usage in " . $file->getFilename() . " -> function $funcName\n";
                }
            }
        }
    }
}
