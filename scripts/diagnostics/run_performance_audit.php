<?php
/**
 * HRMS V2 - 50-User Concurrent Performance Audit Suite
 * Fired sequentially (baseline) and concurrently (simulated load) for 50 users.
 */

require_once 'config/database.php';
$db = \Database::getInstance()->getConnection();

// 1. Fetch 50 unique user API tokens
$stmt = $db->query("SELECT u.id, u.username, u.api_token, r.name as role FROM users u JOIN user_roles ur ON u.id = ur.user_id JOIN roles r ON ur.role_id = r.id WHERE u.api_token IS NOT NULL AND u.api_token != '' LIMIT 50");
$testUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($testUsers) < 50) {
    echo "ERROR: Only found " . count($testUsers) . " users with API tokens. Please run seed_perf_users.php first!\n";
    exit(1);
}

echo "=========================================================\n";
echo "   HRMS V2 COMPREHENSIVE PERFORMANCE AUDIT (50 USERS)    \n";
echo "=========================================================\n";
echo "Loaded " . count($testUsers) . " unique authenticated users for load testing.\n";

$targetHost = 'http://127.0.0.1:8000';
$endpoints = [
    'dashboard'  => '/api/dashboard/summary',
    'employees'  => '/api/employees',
    'balances'   => '/api/leave/balances',
    'attendance' => '/api/attendance/statuses?company_id=1'
];

/**
 * Helper to calculate percentile
 */
function calculate_percentile($array, $percentile) {
    if (empty($array)) return 0;
    sort($array);
    $index = ($percentile / 100) * (count($array) - 1);
    if (floor($index) == $index) {
        $result = $array[$index];
    } else {
        $lower = $array[floor($index)];
        $upper = $array[ceil($index)];
        $result = $lower + ($index - floor($index)) * ($upper - $lower);
    }
    return $result;
}

/**
 * Helper to execute serial performance test
 */
function run_serial_audit($targetUrl, $users) {
    echo "\nRunning Serial Baseline Test (50 sequential requests to " . parse_url($targetUrl, PHP_URL_PATH) . ")...";
    
    $latencies = [];
    $successCount = 0;
    $errorCount = 0;
    $totalBytes = 0;
    
    $startTime = microtime(true);
    
    foreach ($users as $user) {
        $token = $user['api_token'];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $targetUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $token",
            "Accept: application/json"
        ]);
        
        $reqStart = microtime(true);
        $response = curl_exec($ch);
        $reqEnd = microtime(true);
        
        $latency = ($reqEnd - $reqStart) * 1000; // ms
        $latencies[] = $latency;
        
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($httpCode >= 200 && $httpCode < 300) {
            $successCount++;
            $totalBytes += strlen($response ?: '');
        } else {
            $errorCount++;
        }
        
        curl_close($ch);
    }
    
    $endTime = microtime(true);
    $totalDuration = ($endTime - $startTime) * 1000; // ms
    
    return [
        'mode' => 'Serial Baseline',
        'latencies' => $latencies,
        'duration' => $totalDuration,
        'success' => $successCount,
        'errors' => $errorCount,
        'total_bytes' => $totalBytes
    ];
}

/**
 * Helper to execute concurrent performance test
 */
function run_concurrent_audit($targetUrl, $users) {
    echo "\nRunning Concurrent Load Test (50 simultaneous requests to " . parse_url($targetUrl, PHP_URL_PATH) . ")...";
    
    $mh = curl_multi_init();
    $curlHandles = [];
    
    $startTime = microtime(true);
    
    foreach ($users as $index => $user) {
        $token = $user['api_token'];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $targetUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $token",
            "Accept: application/json"
        ]);
        
        curl_multi_add_handle($mh, $ch);
        $curlHandles[$index] = [
            'ch' => $ch,
            'start_time' => microtime(true),
            'user' => $user['username']
        ];
    }
    
    // Execute handles concurrently
    $active = null;
    do {
        $mrc = curl_multi_exec($mh, $active);
    } while ($mrc == CURLM_CALL_MULTI_PERFORM);
    
    while ($active && $mrc == CURLM_OK) {
        if (curl_multi_select($mh) != -1) {
            do {
                $mrc = curl_multi_exec($mh, $active);
            } while ($mrc == CURLM_CALL_MULTI_PERFORM);
        }
    }
    
    $endTime = microtime(true);
    $totalDuration = ($endTime - $startTime) * 1000; // ms
    
    $latencies = [];
    $successCount = 0;
    $errorCount = 0;
    $totalBytes = 0;
    
    foreach ($curlHandles as $info) {
        $ch = $info['ch'];
        $response = curl_multi_getcontent($ch);
        
        // Total transfer time in seconds -> ms
        $latency = curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000;
        $latencies[] = $latency;
        
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($httpCode >= 200 && $httpCode < 300) {
            $successCount++;
            $totalBytes += strlen($response ?: '');
        } else {
            $errorCount++;
            // Log error sample
            if ($errorCount <= 2) {
                file_put_contents('tmp/perf_errors.log', "ERR: User: " . $info['user'] . " | HTTP: " . $httpCode . " | Resp: " . substr($response ?: '', 0, 200) . "\n", FILE_APPEND);
            }
        }
        
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }
    
    curl_multi_close($mh);
    
    return [
        'mode' => 'Concurrent Load',
        'latencies' => $latencies,
        'duration' => $totalDuration,
        'success' => $successCount,
        'errors' => $errorCount,
        'total_bytes' => $totalBytes
    ];
}

/**
 * Report formatter
 */
function compile_report_metrics($result) {
    $latencies = $result['latencies'];
    $count = count($latencies);
    
    $min = min($latencies);
    $max = max($latencies);
    $avg = array_sum($latencies) / $count;
    
    $median = calculate_percentile($latencies, 50);
    $p90 = calculate_percentile($latencies, 90);
    $p95 = calculate_percentile($latencies, 95);
    $p99 = calculate_percentile($latencies, 99);
    
    $throughput = $count / ($result['duration'] / 1000); // Req/sec
    $totalKB = $result['total_bytes'] / 1024;
    
    return [
        'mode'        => $result['mode'],
        'duration'    => $result['duration'],
        'success'     => $result['success'],
        'errors'      => $result['errors'],
        'throughput'  => $throughput,
        'total_kb'    => $totalKB,
        'min'         => $min,
        'max'         => $max,
        'avg'         => $avg,
        'median'      => $median,
        'p90'         => $p90,
        'p95'         => $p95,
        'p99'         => $p99
    ];
}

function print_report_section($name, $serial, $concurrent) {
    echo "\n\n=========================================================\n";
    echo "  ENDPOINT: $name\n";
    echo "=========================================================\n";
    
    printf("  %-25s | %-18s | %-18s\n", "Metric", "Serial Baseline", "Concurrent (50 Users)");
    echo "  " . str_repeat("-", 68) . "\n";
    printf("  %-25s | %-18s | %-18s\n", "Status Codes (OK/ERR)", $serial['success'] . "/" . $serial['errors'], $concurrent['success'] . "/" . $concurrent['errors']);
    printf("  %-25s | %-18.2f ms | %-18.2f ms\n", "Total Test Duration", $serial['duration'], $concurrent['duration']);
    printf("  %-25s | %-18.2f rps| %-18.2f rps\n", "Throughput (Req/Sec)", $serial['throughput'], $concurrent['throughput']);
    printf("  %-25s | %-18.2f KB | %-18.2f KB\n", "Total Data Transferred", $serial['total_kb'], $concurrent['total_kb']);
    printf("  %-25s | %-18.2f ms | %-18.2f ms\n", "Min Latency", $serial['min'], $concurrent['min']);
    printf("  %-25s | %-18.2f ms | %-18.2f ms\n", "Average Latency", $serial['avg'], $concurrent['avg']);
    printf("  %-25s | %-18.2f ms | %-18.2f ms\n", "Median Latency (p50)", $serial['median'], $concurrent['median']);
    printf("  %-25s | %-18.2f ms | %-18.2f ms\n", "90th Percentile (p90)", $serial['p90'], $concurrent['p90']);
    printf("  %-25s | %-18.2f ms | %-18.2f ms\n", "95th Percentile (p95)", $serial['p95'], $concurrent['p95']);
    printf("  %-25s | %-18.2f ms | %-18.2f ms\n", "Max Latency (p100)", $serial['max'], $concurrent['max']);
}

// 2. RUN AUDITS
$reportResults = [];

foreach ($endpoints as $name => $path) {
    $targetUrl = $targetHost . $path;
    
    // Serial test
    $serialRaw = run_serial_audit($targetUrl, $testUsers);
    $serialMetrics = compile_report_metrics($serialRaw);
    
    // Concurrent test
    $concurrentRaw = run_concurrent_audit($targetUrl, $testUsers);
    $concurrentMetrics = compile_report_metrics($concurrentRaw);
    
    print_report_section(strtoupper($name) . " ($path)", $serialMetrics, $concurrentMetrics);
    
    $reportResults[$name] = [
        'path' => $path,
        'serial' => $serialMetrics,
        'concurrent' => $concurrentMetrics
    ];
}

// Dump structured JSON data for analysis
file_put_contents('tmp/perf_audit_data.json', json_encode($reportResults, JSON_PRETTY_PRINT));
echo "\n\nAll tests completed. Raw performance data exported to tmp/perf_audit_data.json\n";
