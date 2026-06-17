<?php

/**
 * HRMS V2 Database Singleton
 */
class Database
{
    private static $instance = null;
    private $pdo;

    private function __construct()
    {
        $configPath = __DIR__ . '/config.php';
        if (file_exists($configPath)) {
            require_once $configPath;
        }

        $host = defined('DB_HOST') ? DB_HOST : '127.0.0.1';
        
        if ($host === 'proxy') {
            require_once __DIR__ . '/ProxyPDO.php';
            // Use the Hevista server URL
            $this->pdo = new ProxyPDO('https://hrms.anedins.com/db_proxy.php', 'HRMS_LOCAL_DEV_SECURE_TOKEN_55');
            return;
        }

        $port = defined('DB_PORT') ? DB_PORT : '3306';
        $db = defined('DB_NAME') ? DB_NAME : 'hrms_v2';
        $user = defined('DB_USER') ? DB_USER : 'root';
        $pass = defined('DB_PASS') ? DB_PASS : '';
        $charset = defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4';

        $dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            $this->pdo = new PDO($dsn, $user, $pass, $options);
        } catch (\PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            die("Database Exception: Connection failed. Check config/config.php.");
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

    private function __clone() {}
    public function __wakeup() { throw new Exception("Cannot unserialize singleton"); }
}
