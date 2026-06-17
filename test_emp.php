<?php
require_once __DIR__ . '/backend/vendor/autoload.php';

// Mock session and Database connection
session_start();
$_SESSION['user_id'] = 17;
$_SESSION['user_role'] = 'OFFICE HRASSISTANT';
$_SESSION['associated_company_ids'] = [1];
$_SESSION['scope_company_id'] = 1;

// Define self::$userRoles for Controller
$_SESSION['user_roles'] = [
    ['name' => 'OFFICE HRASSISTANT']
];

class Database {
    private static $instance = null;
    private $pdo;
    private function __construct() {
        $this->pdo = new PDO('mysql:host=localhost;dbname=hrms_v2;charset=utf8', 'root', '');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }
    public function getConnection() { return $this->pdo; }
}

require_once __DIR__ . '/backend/app/Core/Controller.php';
require_once __DIR__ . '/backend/app/Controllers/EmployeeController.php';

$controller = new \App\Controllers\EmployeeController();
$result = $controller->getDashboardStats();
print_r($result);
