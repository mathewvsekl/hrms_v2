<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\MailHelper;

/**
 * Handles Authentication and Session Lifecycles
 */
class AuthController extends Controller
{
    /**
     * Endpoint: POST /api/login
     */
    public function login()
    {
        $credentials = $this->getJsonPayload();
        $username = $credentials['username'] ?? '';
        $password = $credentials['password'] ?? '';

        if (empty($username) || empty($password)) {
            $this->jsonResponse(null, 400, "Username and Password are required.");
        }

        try {
            $authService = new \App\Services\AuthService();
            $user = $authService->authenticate($username, $password);

            if ($user) {
                if ($user['is_active'] != 1) {
                    if (class_exists('\App\Core\AuditPDO')) {
                        \App\Core\AuditPDO::logExplicitAction('FAILED_LOGIN', 'AUTH', 'users', $user['id'], ['username' => $username, 'reason' => 'Account disabled']);
                    }
                    $this->jsonResponse(null, 403, "Account is disabled.");
                }

                $token = $authService->generateToken((int)$user['id']);

                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }
                
                // Noah Audit Fix: Session Regeneration for Security
                session_regenerate_id(true);

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['scope_employee_id'] = $user['employee_id'];
                $_SESSION['scope_company_id'] = $user['company_id'];
                $_SESSION['scope_country_id'] = $user['country_id'];

                $role = $authService->getUserRole((int)$user['id']);
                $roleId = \App\Helpers\RoleConstants::getPrimaryRoleId((int)$user['id']);
                $_SESSION['user_role'] = $role;
                $_SESSION['role_id'] = $roleId;

                $associatedCompanyIds = $authService->getAssociatedCompanyIds(
                    $user['employee_id'] ? (int)$user['employee_id'] : null,
                    $user['company_id'] ? (int)$user['company_id'] : null
                );

                // Country Manager Restriction: Only Primary Company allowed
                if ($role === 'CountryManager') {
                    $associatedCompanyIds = $user['company_id'] ? [(int)$user['company_id']] : [];
                }

                $_SESSION['associated_company_ids'] = $associatedCompanyIds;

                $permissions = $authService->getUserPermissions((int)$user['id']);

                $this->jsonResponse([
                    'token' => $token,
                    'redirect_url' => '/dashboard',
                    'user_id' => $user['id'],
                    'username' => $username,
                    'first_name' => $user['first_name'] ?? null,
                    'last_name' => $user['last_name'] ?? null,
                    'role' => $role,
                    'role_id' => $roleId,
                    'permissions' => $permissions,
                    'designation' => $user['designation'] ?? null,
                    'employee_id' => $user['employee_id'],
                    'company_id' => $user['company_id'],
                    'country_id' => $user['country_id'],
                    'timezone' => $user['company_timezone'] ?? null
                ], 200, "Authentication successful");

            } else {
                if (class_exists('\App\Core\AuditPDO')) {
                    \App\Core\AuditPDO::logExplicitAction('FAILED_LOGIN', 'AUTH', 'users', null, ['username' => $username, 'reason' => 'Invalid credentials']);
                }
                $this->jsonResponse(null, 401, "Invalid credentials.");
            }
        } catch (\Throwable $e) {
            $this->jsonResponse(null, 500, "Authentication fault: " . $e->getMessage());
        }
    }

    /**
     * Endpoint: POST /api/auth/request-otp
     */
    public function requestOTP()
    {
        $payload = $this->getJsonPayload();
        $email = $payload['email'] ?? '';

        if (empty($email)) {
            $this->jsonResponse(null, 400, "Email is required.");
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->jsonResponse(null, 400, "Invalid email format.");
        }

        try {
            $authService = new \App\Services\AuthService();
            $user = $authService->findUserByIdentifier($email);

            if (!$user || $user['is_active'] != 1) {
                // Mitigate timing attacks by performing a dummy bcrypt hash matching standard real path execution times
                password_hash("dummy_otp_code", PASSWORD_BCRYPT);

                // Generate a deterministic reference code based on the email so it looks identical to real payloads
                $dummyReferenceCode = substr(strtoupper(hash('sha256', $email . 'avantgarde_security_salt_123')), 0, 4);

                $responseData = [
                    "message" => "OTP sent to your email.",
                    "reference_code" => $dummyReferenceCode
                ];
                if (ENVIRONMENT === 'development') {
                    $responseData['dev_otp'] = '123456'; 
                }

                $this->jsonResponse($responseData, 200, "OTP requested successfully.");
                return;
            }

            // 2. Generate 6-digit OTP
            $otp = (string)rand(100000, 999999);
            $authService->createOTP((int)$user['id'], $otp);

            // 3. Resolve user details for personalized greeting and generate matching reference code
            $userName = '';
            if (!empty($user['first_name']) || !empty($user['last_name'])) {
                $userName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
            }
            if (empty($userName)) {
                $userName = 'User';
            }
            
            $referenceCode = substr(strtoupper(hash('sha256', $otp . $email)), 0, 4);

            // 4. Send Email with premium template & security details
            MailHelper::sendOTP($email, $otp, $userName, $referenceCode);

            $responseData = [
                "message" => "OTP sent to your email.",
                "reference_code" => $referenceCode
            ];
            
            if (ENVIRONMENT === 'development') {
                $responseData['dev_otp'] = $otp; 
            }

            $this->jsonResponse($responseData, 200, "OTP requested successfully.");

        } catch (\Throwable $e) {
            $this->jsonResponse(null, 500, "SYSTEM ERROR: " . $e->getMessage());
        }
    }

    /**
     * Endpoint: POST /api/auth/verify-otp
     */
    public function verifyOTP()
    {
        $payload = $this->getJsonPayload();
        $email = $payload['email'] ?? '';
        $otp = $payload['otp'] ?? '';

        if (empty($email) || empty($otp)) {
            $this->jsonResponse(null, 400, "Email and OTP are required.");
        }

        try {
            $authService = new \App\Services\AuthService();
            $user = $authService->findUserByIdentifier($email);

            if (!$user || $user['is_active'] != 1) {
                // Prevent timing attacks by executing a dummy timing delay that matches real password_verify calculations
                password_verify($otp, '$2y$10$abcdefghijklmnopqrstuvwx');
                if (class_exists('\App\Core\AuditPDO')) {
                    \App\Core\AuditPDO::logExplicitAction('FAILED_LOGIN', 'AUTH', 'users', $user['id'] ?? null, ['email' => $email, 'reason' => 'Invalid account or OTP']);
                }
                $this->jsonResponse(null, 401, "Invalid account or OTP.");
            }

            if (!$authService->verifyOTP((int)$user['id'], $otp)) {
                if (class_exists('\App\Core\AuditPDO')) {
                    \App\Core\AuditPDO::logExplicitAction('FAILED_LOGIN', 'AUTH', 'users', $user['id'], ['email' => $email, 'reason' => 'Invalid OTP']);
                }
                $this->jsonResponse(null, 401, "Invalid account or OTP.");
            }

            // 3. Success: Generate Session
            $token = $authService->generateToken((int)$user['id']);

            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            // Security: Regenerate session ID
            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['scope_employee_id'] = $user['employee_id'];
            $_SESSION['scope_company_id'] = $user['company_id'];
            $_SESSION['scope_country_id'] = $user['country_id'];

            $role = $authService->getUserRole((int)$user['id']);
            $roleId = \App\Helpers\RoleConstants::getPrimaryRoleId((int)$user['id']);
            $_SESSION['user_role'] = $role;
            $_SESSION['role_id'] = $roleId;

            $associatedCompanyIds = $authService->getAssociatedCompanyIds(
                $user['employee_id'] ? (int)$user['employee_id'] : null,
                $user['company_id'] ? (int)$user['company_id'] : null
            );

            // Country Manager Restriction: Only Primary Company allowed
            if ($role === 'CountryManager') {
                $associatedCompanyIds = $user['company_id'] ? [(int)$user['company_id']] : [];
            }

            $_SESSION['associated_company_ids'] = $associatedCompanyIds;

            $permissions = $authService->getUserPermissions((int)$user['id']);

            $this->jsonResponse([
                'token' => $token,
                'redirect_url' => '/dashboard',
                'user_id' => $user['id'],
                'username' => $email,
                'first_name' => $user['first_name'] ?? null,
                'last_name' => $user['last_name'] ?? null,
                'role' => $role,
                'role_id' => $roleId,
                'permissions' => $permissions,
                'designation' => $user['designation'] ?? null,
                'employee_id' => $user['employee_id'],
                'company_id' => $user['company_id'],
                'country_id' => $user['country_id'],
                'timezone' => $user['company_timezone'] ?? null
            ], 200, "Authentication successful");

        } catch (\Throwable $e) {
            $this->jsonResponse(null, 500, "Verification fault: " . $e->getMessage());
        }
    }

    /**
     * Endpoint: POST /api/logout
     */
    public function logout()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_destroy();

        $this->jsonResponse(null, 200, "Logged out successfully. All sessions terminated.");
    }

    /**
     * Endpoint: GET /api/auth/me
     */
    public function me()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            $this->jsonResponse(null, 401, "Not authenticated");
            return;
        }

        try {
            $authService = new \App\Services\AuthService();
            $db = \Database::getInstance()->getConnection();
            $stmt = $db->prepare("
                SELECT u.id, u.username as email, u.employee_id, ec.company_id, co.country_id, e.first_name, e.last_name, dg.title as designation
                FROM users u
                LEFT JOIN employees e ON u.employee_id = e.id
                LEFT JOIN employee_companies ec ON e.id = ec.employee_id AND ec.is_primary = 1 AND ec.is_active = 1
                LEFT JOIN companies co ON ec.company_id = co.id
                LEFT JOIN designations dg ON e.designation_id = dg.id
                WHERE u.id = :id AND u.is_active = 1
            ");
            $stmt->execute(['id' => $userId]);
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$user) {
                $this->jsonResponse(null, 401, "User not found or disabled");
                return;
            }

            $role = $authService->getUserRole((int)$user['id']);
            $roleId = \App\Helpers\RoleConstants::getPrimaryRoleId((int)$user['id']);
            $permissions = $authService->getUserPermissions((int)$user['id']);

            $this->jsonResponse([
                'user_id' => $user['id'],
                'username' => $user['email'],
                'first_name' => $user['first_name'] ?? null,
                'last_name' => $user['last_name'] ?? null,
                'role' => $role,
                'role_id' => $roleId,
                'permissions' => $permissions,
                'designation' => $user['designation'] ?? null,
                'employee_id' => $user['employee_id'],
                'company_id' => $user['company_id'],
                'country_id' => $user['country_id']
            ], 200, "User details fetched successfully");

        } catch (\Throwable $e) {
            $this->jsonResponse(null, 500, "System fault: " . $e->getMessage());
        }
    }
}
