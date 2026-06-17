f=open('c:/Users/AneeshMathew/HRMS V2/backend/app/Controllers/AppraisalController.php','r+',encoding='utf-8')
content=f.read()

target = """     */
    {
        $user = \\App\\Middleware\\AuthMiddleware::getUser();"""

replacement = """     */
    public function saveSystemSettings($data)
    {
        $settings = $data['settings'] ?? [];
        $deptRequirements = $data['department_requirements'] ?? [];

        try {
            $this->db->beginTransaction();

            // Update Global Settings
            foreach ($settings as $key => $value) {
                if (is_array($value)) {
                    $value = json_encode($value);
                }
                $stmt = $this->db->prepare("INSERT INTO appraisal_system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?, updated_at_utc = CURRENT_TIMESTAMP");
                $stmt->execute([$key, $value, $value]);
            }

            // Update Department Requirements
            foreach ($deptRequirements as $dept) {
                if (!isset($dept['id'])) continue;
                $stmt = $this->db->prepare("INSERT INTO department_kpi_requirements (department_id, min_kpis) VALUES (?, ?) ON DUPLICATE KEY UPDATE min_kpis = ?, updated_at_utc = CURRENT_TIMESTAMP");
                $stmt->execute([$dept['id'], $dept['min_kpis'] ?? 3, $dept['min_kpis'] ?? 3]);
            }

            $this->db->commit();
            $this->jsonResponse(null, 200, 'System settings updated successfully');
        } catch (\\PDOException $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            $this->jsonResponse(null, 500, 'Database error: ' . $e->getMessage());
        }
    }

    /**
     * Mass deactivation of appraisal records
     */
    public function massDeactivate($data)
    {
        $scope = $data['scope'] ?? 'all';
        
        try {
            try {
                $this->db->exec("ALTER TABLE employee_appraisals ADD COLUMN is_active TINYINT(1) DEFAULT 1");
            } catch (\\PDOException $e) { }

            if ($scope === 'all' || $scope === 'global') {
                $stmt = $this->db->prepare("UPDATE employee_appraisals SET is_active = 0 WHERE id > 0 AND status != 'finalized'");
                $stmt->execute();
            } else {
                $stmt = $this->db->prepare("UPDATE employee_appraisals a JOIN employee_companies ec ON a.employee_id = ec.employee_id AND ec.is_primary = 1 SET a.is_active = 0 WHERE a.id > 0 AND ec.company_id = ? AND a.status != 'finalized'");
                $stmt->execute([$scope]);
            }
            
            $this->jsonResponse(null, 200, 'Mass deactivation completed successfully');
        } catch (\\PDOException $e) {
            $this->jsonResponse(null, 500, 'Database error: ' . $e->getMessage());
        }
    }

    /**
     * Get aggregate appraisal statistics for the dashboard
     */
    public function getStats()
    {
        if (!$this->isInternal) {
            $user = \\App\\Middleware\\AuthMiddleware::getUser();
            if (!$user) {
                return $this->jsonResponse(null, 401, 'Unauthorized');
            }
        }

        try {
            $params = [];
            $geographicFilter = "";
            $isGlobalAdmin = $this->isGlobalAdmin();
            if (!$isGlobalAdmin) {
                $isMultiOffice = $this->hasEntityScope();

                $associatedCompanyIds = $_SESSION['associated_company_ids'] ?? [];
                $sessionCountryId = $_SESSION['scope_country_id'] ?? null;

                if ($isMultiOffice && !empty($associatedCompanyIds)) {
                    $companyIdList = implode(",", array_map('intval', $associatedCompanyIds));
                    $geographicFilter = " AND ec.company_id IN ($companyIdList)";
                } else if ($this->hasGlobalOrRegionalScope() && $sessionCountryId) {
                    $geographicFilter = " AND EXISTS (SELECT 1 FROM companies c2 WHERE ec.company_id = c2.id AND c2.country_id = :session_country_id)";
                    $params['session_country_id'] = $sessionCountryId;
                } else {
                    $sessionCompanyId = $_SESSION['scope_company_id'] ?? null;
                    if ($sessionCompanyId) {
                        $geographicFilter = " AND ec.company_id = :session_company_id";
                        $params['session_company_id'] = $sessionCompanyId;
                    } else if (!$isMultiOffice) {
                        $geographicFilter = " AND 1=0";
                    }
                }

                $geographicFilter .= " AND NOT EXISTS (SELECT 1 FROM user_roles ur2 WHERE ur2.user_id = u.id AND ur2.role_id = 1)";
            }

            $query = "SELECT COUNT(*) as total, SUM(CASE WHEN ea.status = 'finalized' THEN 1 ELSE 0 END) as completed, SUM(CASE WHEN ea.status != 'finalized' AND ea.is_active = 1 THEN 1 ELSE 0 END) as active FROM employee_appraisals ea JOIN employees e ON ea.employee_id = e.id JOIN users u ON e.id = u.employee_id LEFT JOIN employee_companies ec ON e.id = ec.employee_id AND ec.is_primary = 1 AND ec.is_active = 1 WHERE 1=1 $geographicFilter";

            $stmt = $this->db->prepare($query);
            $stmt->execute($params);

            $stats = $stmt->fetch(\\PDO::FETCH_ASSOC);
            
            return $this->jsonResponse([
                'total' => (int)($stats['total'] ?? 0),
                'completed' => (int)($stats['completed'] ?? 0),
                'active' => (int)($stats['active'] ?? 0),
                'appraisalsCount' => (int)($stats['active'] ?? 0)
            ], 200, 'Appraisal stats retrieved successfully');
        } catch (\\PDOException $e) {
            return $this->jsonResponse(null, 500, 'Database error: ' . $e->getMessage());
        }
    }

    /**
     * Generate the Salary Revision / Appraisal Letter
     */
    public function generateLetter($id)
    {
        $user = \\App\\Middleware\\AuthMiddleware::getUser();
        if (!$user || (!str_contains(strtoupper($user['role']), 'HR') && strtoupper($user['role']) !== 'ADMIN' && strtoupper($user['role']) !== 'SUPERADMIN')) {
            return $this->jsonResponse(null, 403, 'Unauthorized');
        }

        try {
            $service = new \\App\\Services\\AppraisalService();
            $result = $service->generateLetter((int)$id, $user);
            return $this->jsonResponse($result, 200, 'Letter generated successfully.');
        } catch (\\Exception $e) {
            $code = $e->getCode() ?: 500;
            return $this->jsonResponse(null, $code, 'Error: ' . $e->getMessage());
        }
    }

    /**
     * Publish the generated letter to the employee
     */
    public function publishLetter($id)
    {
        $user = \\App\\Middleware\\AuthMiddleware::getUser();"""

content = content.replace(target, replacement)
f.seek(0)
f.truncate()
f.write(content)
f.close()
