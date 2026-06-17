<?php
$db = new PDO('mysql:host=localhost;dbname=hrms_v2;charset=utf8mb4', 'root', '');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmtLt = $db->query("SELECT id, code FROM leave_types");
$leaveTypes = [];
while ($row = $stmtLt->fetch(\PDO::FETCH_ASSOC)) {
    $leaveTypes[$row['code']] = $row['id'];
}
$inClause = implode(',', array_fill(0, count($leaveTypes), '?'));
$params = array_keys($leaveTypes);

$query = "
    SELECT al.id as log_id, al.employee_id, al.attendance_date, al.status, ec.company_id, ec.is_primary
    FROM attendance_logs al
    LEFT JOIN employee_companies ec ON al.employee_id = ec.employee_id AND al.company_id = ec.company_id
    WHERE al.status IN ($inClause)
        AND NOT EXISTS (
            SELECT 1 FROM leave_requests lr 
            WHERE lr.employee_id = al.employee_id 
            AND lr.status NOT IN ('rejected', 'cancelled')
            AND al.attendance_date BETWEEN lr.start_date AND lr.end_date
        )
";
$stmt = $db->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll(\PDO::FETCH_ASSOC);

print_r($logs);
