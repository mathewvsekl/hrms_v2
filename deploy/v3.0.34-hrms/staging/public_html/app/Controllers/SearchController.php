<?php

namespace App\Controllers;

use App\Core\Controller;

/**
 * SearchController
 * 
 * Handles global search queries across the system (Employees, Departments, Assets, and Shortcuts).
 * Strictly enforces RBAC and Data Scope boundaries.
 */
class SearchController extends Controller
{
    /**
     * Perform a global search based on a query string
     * GET /api/search?q=...
     */
    public function search()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $query = $_GET['q'] ?? '';
        if (strlen($query) < 2) {
            return $this->jsonResponse(['results' => []], 200, "Query too short");
        }

        $searchTerm = "%$query%";
        $results = [
            'employees' => [],
            'departments' => [],
            'assets' => [],
            'shortcuts' => []
        ];

        try {
            $db = \Database::getInstance()->getConnection();
            $userId = $_SESSION['user_id'] ?? null;
            $myCompanyId = $_SESSION['scope_company_id'] ?? null;
            
            $isSuperAdmin = $this->isSuperAdmin();
            $hasEmployeeScope = $this->hasEntityScope('employees');
            $hasAssetScope = $this->hasEntityScope('assets');

            // 1. SEARCH EMPLOYEES
            $empWhere = "WHERE (e.first_name LIKE :q1 OR e.last_name LIKE :q2 OR e.employee_code LIKE :q3 OR e.email LIKE :q4)";
            $empParams = [
                'q1' => $searchTerm,
                'q2' => $searchTerm,
                'q3' => $searchTerm,
                'q4' => $searchTerm
            ];

            if (!$isSuperAdmin) {
                // Hierarchical Isolation: Non-SuperAdmins cannot see SuperAdmins in search results
                $empWhere .= " AND NOT EXISTS (
                    SELECT 1 FROM user_roles ur2 
                    JOIN users u2 ON ur2.user_id = u2.id
                    WHERE u2.employee_id = e.id AND ur2.role_id = " . \App\Helpers\RoleConstants::SUPER_ADMIN . "
                )";

                if ($hasEmployeeScope) {
                    $associatedCompanyIds = $_SESSION['associated_company_ids'] ?? [];
                    if (!empty($associatedCompanyIds)) {
                        $companyIdList = implode(",", array_map('intval', $associatedCompanyIds));
                        $empWhere .= " AND ec.company_id IN ($companyIdList)";
                    } else {
                        $empWhere .= " AND 1=0";
                    }
                } else {
                    $empWhere .= " AND ec.company_id = :session_company_id";
                    $empParams['session_company_id'] = $myCompanyId;
                }
            }

            $empStmt = $db->prepare("
                SELECT e.id, e.first_name, e.last_name, e.employee_code, e.email, 
                       d.name as department_name, dg.title as designation
                FROM employees e
                LEFT JOIN departments d ON e.department_id = d.id
                LEFT JOIN designations dg ON e.designation_id = dg.id
                LEFT JOIN employee_companies ec ON e.id = ec.employee_id AND ec.is_primary = 1 AND ec.is_active = 1
                $empWhere
                LIMIT 5
            ");
            $empStmt->execute($empParams);
            $results['employees'] = $empStmt->fetchAll(\PDO::FETCH_ASSOC);

            // 2. SEARCH DEPARTMENTS
            $deptStmt = $db->prepare("SELECT id, name FROM departments WHERE name LIKE ? LIMIT 3");
            $deptStmt->execute([$searchTerm]);
            $results['departments'] = $deptStmt->fetchAll(\PDO::FETCH_ASSOC);

            // 3. SEARCH ASSETS (Only for Admins/SuperAdmins, and ONLY IF TABLE EXISTS)
            if ($hasAssetScope || $isSuperAdmin) {
                // Check if assets table exists to prevent 500 error
                $tableCheck = $db->query("SHOW TABLES LIKE 'assets'")->fetch();
                if ($tableCheck) {
                    $assetWhere = "WHERE (name LIKE :qa1 OR serial_number LIKE :qa2 OR model_number LIKE :qa3)";
                    $assetParams = [
                        'qa1' => $searchTerm,
                        'qa2' => $searchTerm,
                        'qa3' => $searchTerm
                    ];

                    if (!$this->isGlobalAdmin() && $myCompanyId) {
                        $assetWhere .= " AND company_id = :company_id";
                        $assetParams['company_id'] = $myCompanyId;
                    }


                    $assetStmt = $db->prepare("SELECT id, name, category, serial_number, status FROM assets $assetWhere LIMIT 5");
                    $assetStmt->execute($assetParams);
                    $results['assets'] = $assetStmt->fetchAll(\PDO::FETCH_ASSOC);
                }
            }

            // 4. GENERATE SHORTCUTS (Based on RBAC)
            $allShortcuts = [
                ['name' => 'Add Employee', 'path' => '/onboarding', 'module' => 'Employees', 'action' => 'create'],
                ['name' => 'Leave Requests', 'path' => '/leave', 'module' => 'Leave', 'action' => 'view'],
                ['name' => 'Attendance Logs', 'path' => '/attendance', 'module' => 'Attendance', 'action' => 'view'],
                ['name' => 'Payroll Dashboard', 'path' => '/payroll', 'module' => 'Payroll', 'action' => 'view'],
                ['name' => 'Asset Inventory', 'path' => '/assets', 'module' => 'Assets', 'action' => 'view'],
                ['name' => 'System Settings', 'path' => '/admin', 'module' => 'Configuration', 'action' => 'view'],
                ['name' => 'My Profile', 'path' => '/employee-profile', 'module' => null, 'action' => null], // Anyone can view
                ['name' => 'Submit Leave', 'path' => '/leave', 'module' => null, 'action' => null], // Anyone can submit their own
            ];

            foreach ($allShortcuts as $shortcut) {
                if (stripos($shortcut['name'], $query) !== false) {
                    $canSee = true;
                    if ($shortcut['module'] && $shortcut['action']) {
                        $canSee = \App\Middleware\RoleMiddleware::hasPermission($shortcut['module'], $shortcut['action']);
                    }
                    if ($canSee) {
                        $results['shortcuts'][] = [
                            'name' => $shortcut['name'],
                            'path' => $shortcut['path']
                        ];
                    }
                }
            }

            return $this->jsonResponse($results);

        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Search execution error: " . $e->getMessage());
        }
    }
}
