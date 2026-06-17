<?php

namespace App\Controllers;

use App\Core\Controller;
use PDO;
use ZipArchive;

/**
 * ExportController
 * 
 * Handles data extraction and export in various formats (CSV inside ZIP).
 */
class ExportController extends Controller
{
    /**
     * Main export entry point
     * GET /api/export/data?company_id=X&start_date=Y&end_date=Z&modules[]=employees&modules[]=attendance...
     */
    public function exportData()
    {
        // 1. Inputs & Validation
        $companyId = $_GET['company_id'] ?? null;
        $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-1 year'));
        $endDate = $_GET['end_date'] ?? date('Y-m-d');
        $requestedModules = $_GET['modules'] ?? ['employees', 'attendance', 'leave', 'payroll', 'appraisal'];

        if (!$companyId) {
            return $this->jsonResponse(null, 400, "Company ID is required for export.");
        }

        // 2. Security & Scope Check
        $this->verifyDataScope($companyId);
        if (!$this->isSuperAdmin() && !in_array('ADMIN', $this->getUserRoles()) && !in_array('HRMANAGER', $this->getUserRoles())) {
            return $this->jsonResponse(null, 403, "Access Denied: Insufficient permissions for data export.");
        }

        try {
            $db = \Database::getInstance()->getConnection();
            
            // 3. Check for ZipArchive support
            if (!class_exists('ZipArchive')) {
                throw new \Exception("The 'zip' PHP extension is not enabled on this server. Please enable it or contact your administrator.");
            }

            // Create temporary filename
            $tempZipFile = tempnam(sys_get_temp_dir(), 'export_') . '.zip';
            $zip = new ZipArchive();
            
            if ($zip->open($tempZipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
                throw new \Exception("Could not create temporary ZIP file.");
            }

            $hasData = false;

            // 3. Process Modules
            if (in_array('employees', $requestedModules)) {
                $csv = $this->fetchEmployeesCsv($db, (int)$companyId);
                if ($csv) {
                    $zip->addFromString('employees.csv', $csv);
                    $hasData = true;
                }
            }

            if (in_array('attendance', $requestedModules)) {
                $csv = $this->fetchAttendanceCsv($db, (int)$companyId, $startDate, $endDate);
                if ($csv) {
                    $zip->addFromString('attendance.csv', $csv);
                    $hasData = true;
                }
            }

            if (in_array('leave', $requestedModules)) {
                $csv = $this->fetchLeaveCsv($db, (int)$companyId, $startDate, $endDate);
                if ($csv) {
                    $zip->addFromString('leave_requests.csv', $csv);
                    $hasData = true;
                }
            }

            if (in_array('payroll', $requestedModules)) {
                $csv = $this->fetchPayrollCsv($db, (int)$companyId, $startDate, $endDate);
                if ($csv) {
                    $zip->addFromString('payroll_records.csv', $csv);
                    $hasData = true;
                }
            }

            if (in_array('appraisal', $requestedModules)) {
                $csv = $this->fetchAppraisalCsv($db, (int)$companyId, $startDate, $endDate);
                if ($csv) {
                    $zip->addFromString('appraisals.csv', $csv);
                    $hasData = true;
                }
            }

            $zip->close();

            if (!$hasData) {
                @unlink($tempZipFile);
                return $this->jsonResponse(null, 404, "No data found for the selected filters.");
            }

            // 4. Stream ZIP to Browser
            $this->streamFile($tempZipFile, "HRMS_Export_" . date('Ymd_His') . ".zip", 'application/zip');
            @unlink($tempZipFile);
            exit();

        } catch (\Throwable $e) {
            // Check if we can stream a single CSV directly if only one module was requested
            if (count($requestedModules) === 1 && isset($db)) {
                return $this->streamSingleModuleCsv($requestedModules[0], (int)$companyId, $startDate, $endDate);
            }

            // Check if we can fallback to TarGz if Zip is missing
            if (isset($db) && strpos($e->getMessage(), 'ZipArchive') !== false && class_exists('PharData') && !ini_get('phar.readonly')) {
                return $this->exportDataAsTar($companyId, $startDate, $endDate, $requestedModules);
            }
            
            @ob_end_clean();
            return $this->jsonResponse(null, 500, "Export failed: " . $e->getMessage());
        }
    }

    /**
     * Streams a single module's data as a raw CSV (no archiving required)
     */
    private function streamSingleModuleCsv($module, $companyId, $start, $end)
    {
        try {
            @ob_end_clean();
            $db = \Database::getInstance()->getConnection();
            $method = [
                'employees' => 'fetchEmployeesCsv',
                'attendance' => 'fetchAttendanceCsv',
                'leave' => 'fetchLeaveCsv',
                'payroll' => 'fetchPayrollCsv',
                'appraisal' => 'fetchAppraisalCsv'
            ][$module] ?? null;

            if (!$method) throw new \Exception("Invalid module: $module");

            $csv = ($module === 'employees') 
                ? $this->$method($db, $companyId) 
                : $this->$method($db, $companyId, $start, $end);

            if (!$csv) {
                return $this->jsonResponse(null, 404, "No data found for $module.");
            }

            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Expose-Headers: Content-Disposition, Content-Type');
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $module . '_' . date('Ymd') . '.csv"');
            header('Pragma: no-cache');
            header('Expires: 0');
            
            echo $csv;
            exit();
        } catch (\Throwable $e) {
            return $this->jsonResponse(null, 500, "Single CSV Export Failed: " . $e->getMessage());
        }
    }

    /**
     * Fallback for systems without ZipArchive (uses PharData + zlib)
     */
    private function exportDataAsTar($companyId, $startDate, $endDate, $requestedModules)
    {
        try {
            $db = \Database::getInstance()->getConnection();
            $tempTarFile = tempnam(sys_get_temp_dir(), 'export_') . '.tar';
            $tar = new \PharData($tempTarFile);
            $hasData = false;

            $modulesToFetch = [
                'employees' => 'fetchEmployeesCsv',
                'attendance' => 'fetchAttendanceCsv',
                'leave' => 'fetchLeaveCsv',
                'payroll' => 'fetchPayrollCsv',
                'appraisal' => 'fetchAppraisalCsv'
            ];

            foreach ($modulesToFetch as $mod => $method) {
                if (in_array($mod, $requestedModules)) {
                    $csv = ($mod === 'employees') 
                        ? $this->$method($db, (int)$companyId) 
                        : $this->$method($db, (int)$companyId, $startDate, $endDate);
                    
                    if ($csv) {
                        $tar->addFromString("$mod.csv", $csv);
                        $hasData = true;
                    }
                }
            }

            if (!$hasData) {
                @unlink($tempTarFile);
                return $this->jsonResponse(null, 404, "No data found for the selected filters (Tar Fallback).");
            }

            // Compress to .tar.gz
            $compressedTar = $tar->compress(\Phar::GZ);
            $tempGzFile = $compressedTar->getPath();
            
            @unlink($tempTarFile); // Cleanup original tar
            
            $this->streamFile($tempGzFile, "HRMS_Export_" . date('Ymd_His') . ".tar.gz", 'application/x-gzip');
            @unlink($tempGzFile);
            exit();

        } catch (\Throwable $e) {
            @ob_end_clean();
            return $this->jsonResponse(null, 500, "Export Fallback Failed: " . $e->getMessage());
        }
    }

    /**
     * Cleanly stream a file to the browser
     */
    private function streamFile($filePath, $downloadName, $contentType)
    {
        @ob_end_clean();
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Expose-Headers: Content-Disposition, Content-Type');
        header('Content-Description: File Transfer');
        header('Content-Type: ' . $contentType);
        header('Content-Disposition: attachment; filename="' . $downloadName . '"');
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));
        
        flush();
        readfile($filePath);
    }

    /**
     * Helper to get user roles from session
     */
    private function getUserRoles()
    {
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) return [];
        $db = \Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT r.name FROM user_roles ur JOIN roles r ON ur.role_id = r.id WHERE ur.user_id = ?");
        $stmt->execute([$userId]);
        return array_map('strtoupper', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Convert array to CSV string
     */
    private function arrayToCsv(array $data)
    {
        if (empty($data)) return "";
        $output = fopen('php://temp', 'r+');
        // Add Header
        fputcsv($output, array_keys($data[0]));
        // Add Rows
        foreach ($data as $row) {
            // Use standard parameters for PHP 7+ compatibility, avoiding deprecated escape in 8.4
            fputcsv($output, $row, ",", "\"", "\\");
        }
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        return $csv;
    }

    private function fetchEmployeesCsv($db, $companyId)
    {
        $stmt = $db->prepare("
            SELECT e.employee_code, e.first_name, e.last_name, e.email, e.phone, 
                   e.hire_date, e.employment_type, e.status, 
                   d.name as department, dg.title as designation
            FROM employees e
            JOIN employee_companies ec ON e.id = ec.employee_id
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN designations dg ON e.designation_id = dg.id
            WHERE ec.company_id = ? AND ec.is_active = 1
        ");
        $stmt->execute([$companyId]);
        return $this->arrayToCsv($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    private function fetchAttendanceCsv($db, $companyId, $start, $end)
    {
        $stmt = $db->prepare("
            SELECT al.attendance_date, e.employee_code, e.first_name, e.last_name, 
                   al.status, al.check_out_utc, al.source, al.remarks
            FROM attendance_logs al
            JOIN employees e ON al.employee_id = e.id
            JOIN employee_companies ec ON e.id = ec.employee_id
            WHERE ec.company_id = ? AND al.attendance_date BETWEEN ? AND ?
            ORDER BY al.attendance_date DESC, e.last_name ASC
        ");
        $stmt->execute([$companyId, $start, $end]);
        return $this->arrayToCsv($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    private function fetchLeaveCsv($db, $companyId, $start, $end)
    {
        $stmt = $db->prepare("
            SELECT lr.start_date, lr.end_date, lr.total_days, lr.status as request_status,
                   lt.name as leave_type, e.employee_code, e.first_name, e.last_name
            FROM leave_requests lr
            JOIN leave_types lt ON lr.leave_type_id = lt.id
            JOIN employees e ON lr.employee_id = e.id
            JOIN employee_companies ec ON e.id = ec.employee_id
            WHERE ec.company_id = ? AND (lr.start_date BETWEEN ? AND ? OR lr.end_date BETWEEN ? AND ?)
            ORDER BY lr.start_date DESC
        ");
        $stmt->execute([$companyId, $start, $end, $start, $end]);
        return $this->arrayToCsv($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    private function fetchPayrollCsv($db, $companyId, $start, $end)
    {
        // Payroll is monthly, so we convert dates to Y-m
        $startYear = date('Y', strtotime($start));
        $startMonth = date('m', strtotime($start));
        $endYear = date('Y', strtotime($end));
        $endMonth = date('m', strtotime($end));

        $stmt = $db->prepare("
            SELECT pr.year, pr.month, e.employee_code, e.first_name, e.last_name,
                   rec.currency_code, rec.gross_amount, rec.net_amount, pr.status as run_status
            FROM payroll_records rec
            JOIN payroll_runs pr ON rec.payroll_run_id = pr.id
            JOIN employees e ON rec.employee_id = e.id
            JOIN employee_companies ec ON e.id = ec.employee_id
            WHERE ec.company_id = ? 
              AND (pr.year > ? OR (pr.year = ? AND pr.month >= ?))
              AND (pr.year < ? OR (pr.year = ? AND pr.month <= ?))
            ORDER BY pr.year DESC, pr.month DESC
        ");
        $stmt->execute([$companyId, $startYear, $startYear, $startMonth, $endYear, $endYear, $endMonth]);
        return $this->arrayToCsv($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    private function fetchAppraisalCsv($db, $companyId, $start, $end)
    {
        $stmt = $db->prepare("
            SELECT ac.name as cycle_name, ac.start_date, ac.end_date,
                   e.employee_code, e.first_name, e.last_name,
                   ea.status as appraisal_status, ea.final_rating, ea.eligible_for_increment
            FROM employee_appraisals ea
            JOIN appraisal_cycles ac ON ea.cycle_id = ac.id
            JOIN employees e ON ea.employee_id = e.id
            JOIN employee_companies ec ON e.id = ec.employee_id
            WHERE ec.company_id = ? AND (ac.start_date BETWEEN ? AND ? OR ac.end_date BETWEEN ? AND ?)
            ORDER BY ac.end_date DESC
        ");
        $stmt->execute([$companyId, $start, $end, $start, $end]);
        return $this->arrayToCsv($stmt->fetchAll(PDO::FETCH_ASSOC));
    }
}
