<?php
$_SERVER['REQUEST_URI'] = '/api/appraisals/draft';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [];
$payload = json_encode([
    'appraisal_id' => 153,
    'ratings' => [
        ['kra_name' => '1', 'manager_rating' => 4, 'employee_rating' => 5],
        ['question_id' => 157, 'manager_rating' => 8]
    ]
]);

$db = new PDO('mysql:host=localhost;dbname=hrms_v2;charset=utf8', 'root', '');
define('BASE_PATH', realpath(__DIR__ . '/..'));
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Core/Autoloader.php';
\App\Core\Autoloader::register();

$service = new \App\Services\AppraisalService();
// Aneesh Mathew is manager
$userData = ['id' => 17, 'employee_id' => 1, 'role' => 'ADMIN']; 
$data = json_decode($payload, true);

try {
    $service->saveDraft($data, $userData);
    echo "Saved!\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

$stmt = $db->query("SELECT * FROM appraisal_ratings WHERE appraisal_id = 153 AND (kra_name = '1' OR question_id = 157)");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
