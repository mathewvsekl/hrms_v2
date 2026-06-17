<?php

/**
 * HRMS V2 Database Singleton
 * Implements a secure PDO wrapper to interface with DATABASE_SCHEMA.sql (MySQL)
 */
class Database
{
    private static $instance = null;
    private $pdo;

    private $host;
    private $port;
    private $db;
    private $user;
    private $pass;
    private $charset;

    private function __construct()
    {
        // Load external config if exists, otherwise fallback to local defaults
        $configPath = __DIR__ . '/config.php';
        if (file_exists($configPath)) {
            require_once $configPath;
        }

        $this->host = defined('DB_HOST') ? DB_HOST : '127.0.0.1';
        $this->port = defined('DB_PORT') ? DB_PORT : '3306';
        $this->db = defined('DB_NAME') ? DB_NAME : 'hrms_v2';
        $this->user = defined('DB_USER') ? DB_USER : 'root';
        $this->pass = defined('DB_PASS') ? DB_PASS : '';
        $this->charset = defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4';

        // PATH C: Web Bridge / Proxy Support
        if (defined('ACTIVE_ENVIRONMENT') && ACTIVE_ENVIRONMENT === 'remote') {
            require_once __DIR__ . '/ProxyPDO.php';
            $proxyUrl = defined('PROXY_URL') ? PROXY_URL : '';
            $proxyToken = defined('PROXY_TOKEN') ? PROXY_TOKEN : '';
            
            error_log("Database: Initializing Web Bridge via ProxyPDO (Path C)");
            $this->pdo = new ProxyPDO($proxyUrl, $proxyToken);
            return;
        }

        $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->db};charset={$this->charset}";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            $this->pdo = new PDO($dsn, $this->user, $this->pass, $options);
        } catch (\PDOException $e) {
            // Log error internally but provide a generic fault to the user
            error_log("Database Connection Error: " . $e->getMessage());
            die("Database Exception: Connection to HRMS cluster failed. Please verify config/config.php.");
        }
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection()
    {
        return $this->pdo;
    }

    /**
     * Prevents cloning of the singleton instance
     */
    private function __clone()
    {
    }

    /**
     * Prevents unserialization of the singleton instance
     */
    public function __wakeup()
    {
        throw new Exception("Cannot unserialize singleton");
    }
}
