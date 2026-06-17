<?php
try {
    $db = new PDO('mysql:host=localhost;dbname=hrms_v2', 'root', '');
    $stmt = $db->query("SELECT u.id, u.username, e.first_name, e.last_name FROM users u JOIN employees e ON u.employee_id = e.id");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "ID: " . $row['id'] . " | Username: " . $row['username'] . " | Name: " . $row['first_name'] . " " . $row['last_name'] . "\n";
    }
} catch (Exception $e) {
    echo "hrms_v2 not accessible or empty: " . $e->getMessage() . "\n";
}

try {
    $db = new PDO('mysql:host=localhost;dbname=glowlady_avantgarde', 'root', '');
    $stmt = $db->query("SELECT u.id, u.username, e.first_name, e.last_name FROM users u JOIN employees e ON u.employee_id = e.id");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "ID: " . $row['id'] . " | Username: " . $row['username'] . " | Name: " . $row['first_name'] . " " . $row['last_name'] . "\n";
    }
} catch (Exception $e) {
    echo "glowlady_avantgarde not accessible or empty: " . $e->getMessage() . "\n";
}
?>
