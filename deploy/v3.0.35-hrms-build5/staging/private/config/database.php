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
            PDO::ATTR_PERSISTENT => false,
        ];

        try {
            if (class_exists('\App\Core\AuditPDO')) {
                $this->pdo = new \App\Core\AuditPDO($dsn, $user, $pass, $options);
                error_log("Database DEBUG: Created AuditPDO connection.");
            } else {
                $this->pdo = new PDO($dsn, $user, $pass, $options);
                error_log("Database DEBUG: WARNING - Created standard PDO connection because \App\Core\AuditPDO was not found!");
            }
        } catch (\PDOException $e) {
            if (!defined('ACTIVE_ENVIRONMENT') || ACTIVE_ENVIRONMENT === 'local') {
                $msg = "Database Exception: " . $e->getMessage();
            } else {
                error_log("Database Connection Error: " . $e->getMessage());
                $msg = "Database Exception: Connection failed. Please check credentials.";
            }
            
            if (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') === 0) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => $msg]);
                exit;
            } else {
                die($msg);
            }
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
