<?php
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator('C:\Users\AneeshMathew\HRMS V2\backend\app'));
foreach ($files as $file) {
    if ($file->getExtension() === 'php') {
        $content = file_get_contents($file->getPathname());
        $tokens = token_get_all($content);
        
        $currentFunction = '';
        $inFunction = false;
        $braceCount = 0;
        $functionTokens = [];
        
        for ($i = 0; $i < count($tokens); $i++) {
            $token = $tokens[$i];
            
            if (is_array($token) && $token[0] === T_FUNCTION) {
                // Find function name
                $j = $i + 1;
                while (isset($tokens[$j]) && (!is_array($tokens[$j]) || $tokens[$j][0] !== T_STRING)) {
                    $j++;
                }
                $currentFunction = $tokens[$j][1] ?? 'anonymous';
                $inFunction = true;
                $braceCount = 0;
                $functionTokens = [];
                $i = $j;
            } else if ($inFunction) {
                if ($token === '{') {
                    $braceCount++;
                } else if ($token === '}') {
                    $braceCount--;
                    if ($braceCount === 0) {
                        $inFunction = false;
                        analyze_function($file->getFilename(), $currentFunction, $functionTokens);
                    }
                }
                $functionTokens[] = $token;
            }
        }
    }
}

function analyze_function($filename, $funcName, $tokens) {
    $code = '';
    foreach ($tokens as $t) {
        $code .= is_array($t) ? $t[1] : $t;
    }
    
    // Check for $db-> without initialization
    if (strpos($code, '$db->') !== false) {
        if (!preg_match('/\$db\s*=\s*/', $code) && !preg_match('/global\s+\$db/', $code) && !preg_match('/function[^\(]*\([^\)]*\$db[^\)]*\)/', $code)) {
            echo "Missing \$db initialization in $filename :: $funcName\n";
        }
    }

    // Check for $stmt-> without initialization
    if (strpos($code, '$stmt->') !== false) {
        if (!preg_match('/\$stmt\s*=\s*/', $code)) {
            echo "Missing \$stmt initialization in $filename :: $funcName\n";
        }
    }
}
