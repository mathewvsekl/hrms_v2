<?php

/**
 * HRMS V2 ProxyPDO Client (The Web Bridge)
 * Mimics a standard PDO connection but sends queries over HTTP to a remote proxy.
 */
class ProxyPDO
{
    private $url;
    private $token;
    private $lastInsertId = null;

    public function __construct($url, $token)
    {
        $this->url = $url;
        $this->token = $token;
    }

    public function prepare($sql)
    {
        return new ProxyPDOStatement($this->url, $this->token, $sql, $this);
    }

    public function query($sql)
    {
        $stmt = $this->prepare($sql);
        $stmt->execute();
        return $stmt;
    }

    public function lastInsertId()
    {
        return $this->lastInsertId;
    }

    public function setLastInsertId($id)
    {
        $this->lastInsertId = $id;
    }

    // Transaction stubs for compatibility with controllers
    public function beginTransaction() { return true; }
    public function commit() { return true; }
    public function rollBack() { return true; }
    public function inTransaction() { return false; }
}

/**
 * Mimics a standard PDOStatement
 */
class ProxyPDOStatement
{
    private $url;
    private $token;
    private $sql;
    private $parent;
    private $results = [];
    private $rowCount = 0;
    private $iterator = 0;
    private $boundParams = [];

    public function __construct($url, $token, $sql, $parent)
    {
        $this->url = $url;
        $this->token = $token;
        $this->sql = $sql;
        $this->parent = $parent;
    }

    public function bindValue($parameter, $value, $data_type = null)
    {
        $this->boundParams[$parameter] = $value;
        return true;
    }

    public function bindParam($parameter, &$variable, $data_type = null, $length = null, $driver_options = null)
    {
        // For a proxy bridge, we'll store the current value of the variable
        $this->boundParams[$parameter] = $variable;
        return true;
    }

    public function execute($params = [])
    {
        // Silence all warnings to ensure a clean JSON response for the frontend
        $oldErrorLevel = error_reporting(0);
        
        try {
            // Merge passed params with bound params
            $finalParams = array_merge($this->boundParams, $params);

            $payload = json_encode([
                'sql' => $this->sql,
                'params' => $finalParams
            ]);

            $options = [
                'http' => [
                    'method'  => 'POST',
                    'header'  => "Content-Type: application/json\r\n" .
                                 "Accept: application/json\r\n" .
                                 "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36\r\n" .
                                 "Referer: " . APP_BASE_URL . "/\r\n" .
                                 "Origin: " . APP_BASE_URL . "\r\n" .
                                 "X-HRMS-Proxy-Token: " . $this->token . "\r\n",
                    'content' => $payload,
                    'timeout' => 60,
                    'ignore_errors' => true 
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ]
            ];

            $context = stream_context_create($options);
            $response = @file_get_contents($this->url, false, $context);

            if ($response === false) {
                $error = error_get_last();
                throw new \Exception("Proxy Connection Failed: " . ($error['message'] ?? 'Unknown Error'));
            }

            // Cleanup response (ensure valid UTF-8 to prevent json_decode failures)
            if (function_exists('mb_convert_encoding')) {
                $response = mb_convert_encoding($response, 'UTF-8', 'UTF-8');
            }

            // Read headers safely
            $headers = isset($http_response_header) ? $http_response_header : [];
            $httpStatus = 0;
            if (!empty($headers) && isset($headers[0])) {
                preg_match('{HTTP\/\S+\s+(\d+)}', $headers[0], $matches);
                if (isset($matches[1])) {
                    $httpStatus = (int)$matches[1];
                }
            }

            if ($httpStatus !== 200 && $httpStatus !== 0) {
                $errData = json_decode($response, true);
                $errMsg = isset($errData['error']) ? $errData['error'] : "HTTP $httpStatus";
                throw new \Exception("Proxy Server Error: " . $errMsg);
            }

            $data = json_decode($response, true);
            
            // Debug: Log all bridge responses to help track lastInsertId issues
            file_put_contents(BASE_PATH . '/tmp/proxy_debug.log', "SQL: " . substr($this->sql, 0, 100) . " | Status: " . $httpStatus . " | Response: " . $response . "\n", FILE_APPEND);

            if (!$data || !isset($data['success'])) {
                $rawSnippet = substr($response, 0, 500);
                throw new \Exception("Proxy Format Error: Invalid JSON response or bridge unreachable. Status: {$httpStatus}. Body: {$rawSnippet}");
            }

            if (!$data['success']) {
                throw new \Exception("Proxy Query Error: " . ($data['error'] ?? 'Unknown Error'));
            }

            $this->results = isset($data['data']) ? $data['data'] : [];
            $this->rowCount = isset($data['rowCount']) ? $data['rowCount'] : 0;
            
            if (isset($data['lastInsertId'])) {
                $this->parent->setLastInsertId($data['lastInsertId']);
            }

            $this->iterator = 0;
            error_reporting($oldErrorLevel);
            return true;

        } catch (\Throwable $e) {
            error_reporting($oldErrorLevel);
            // Re-throw so the Controller can find it, but ensure it's a clean Error
            throw $e;
        }
    }

    public function fetch($mode = null)
    {
        if ($this->iterator < count($this->results)) {
            $row = $this->results[$this->iterator++];
            if ($mode === \PDO::FETCH_COLUMN) {
                return array_values($row)[0] ?? false;
            }
            return $row;
        }
        return false;
    }

    public function fetchColumn($column_number = 0)
    {
        return $this->fetch(\PDO::FETCH_COLUMN);
    }

    public function fetchAll($mode = null, ...$args)
    {
        if ($mode === \PDO::FETCH_COLUMN) {
            $columnData = [];
            foreach ($this->results as $row) {
                $values = array_values($row);
                $columnData[] = $values[0] ?? null;
            }
            return $columnData;
        }

        if ($mode === \PDO::FETCH_KEY_PAIR) {
            $keyPairData = [];
            foreach ($this->results as $row) {
                $values = array_values($row);
                if (count($values) >= 2) {
                    $keyPairData[$values[0]] = $values[1];
                }
            }
            return $keyPairData;
        }

        return $this->results;
    }

    public function rowCount()
    {
        return $this->rowCount;
    }

    public function setFetchMode($mode, ...$args)
    {
        // Not implemented but added as a stub to prevent crashes
        return true;
    }

    public function closeCursor()
    {
        // Not needed for HTTP results but added as a stub
        return true;
    }
}
