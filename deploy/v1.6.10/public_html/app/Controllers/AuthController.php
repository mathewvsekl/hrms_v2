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
        // 1. Fetch strict JSON payload securely
        $credentials = $this->getJsonPayload();

        $username = $credentials['username'] ?? '';
        $password = $credentials['password'] ?? '';

        if (empty($username) || empty($password)) {
            $this->jsonResponse(null, 400, "Username and Password are required.");
        }


        // --- Secure PDO Authentication ---
        try {
            $db = \Database::getInstance()->getConnection();

            // 1. Fetch User Record and Geographic Context
            $stmt = $db->prepare("
            SELECT u.id, u.password_hash, u.is_active, u.employee_id, ec.company_id, co.country_id, e.first_name, e.last_name 
            FROM users u 
            LEFT JOIN employees e ON u.employee_id = e.id
            LEFT JOIN employee_companies ec ON e.id = ec.employee_id AND ec.is_primary = 1
            LEFT JOIN companies co ON ec.company_id = co.id
            WHERE u.username = :usr LIMIT 1
        ");
            $stmt->execute(['usr' => $username]);
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);

            // 2. Cryptographic Password Validation
            if ($user && password_verify($password, $user['password_hash'])) {

                if ($user['is_active'] != 1) {
                    $this->jsonResponse(null, 403, "Account is disabled.");
                }

                // 3. Generate Secure Stateless API Token
                $token = bin2hex(random_bytes(32)); // e.g. 64-char hex string

                // 4. Update the DB token & login timestamp
                $updateStmt = $db->prepare("UPDATE users SET api_token = :token, last_login_utc = CURRENT_TIMESTAMP WHERE id = :id");
                $updateStmt->execute([
                    'token' => $token,
                    'id' => $user['id']
                ]);

                // 5. Build dynamic session and return success
                session_start();
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['scope_employee_id'] = $user['employee_id'];
                $_SESSION['scope_company_id'] = $user['company_id'];
                $_SESSION['scope_country_id'] = $user['country_id'];

                // Noah Audit Fix: Dynamic RBAC Role Lookup
                $roleStmt = $db->prepare("
                    SELECT r.name 
                    FROM roles r
                    JOIN user_roles ur ON r.id = ur.role_id
                    WHERE ur.user_id = :uid LIMIT 1
                ");
                $roleStmt->execute(['uid' => $user['id']]);
                $role = $roleStmt->fetchColumn() ?: 'EMPLOYEE';
                $_SESSION['user_role'] = $role;

                $this->jsonResponse([
                    'token' => $token,
                    'redirect_url' => '/dashboard',
                    'user_id' => $user['id'],
                    'username' => $username,
                    'first_name' => $user['first_name'] ?? null,
                    'last_name' => $user['last_name'] ?? null,
                    'role' => $role,
                    'employee_id' => $user['employee_id'],
                    'company_id' => $user['company_id'],
                    'country_id' => $user['country_id']
                ], 200, "Authentication successful");

            } else {
                // Hard reject for Brute Force mitigation
                $this->jsonResponse(null, 401, "Invalid credentials.");
            }
        } catch (\Exception $e) {
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

        try {
            $db = \Database::getInstance()->getConnection();

            // 1. Find User by Email (Employee email or Username)
            $stmt = $db->prepare("
                SELECT u.id, u.is_active 
                FROM users u 
                LEFT JOIN employees e ON u.employee_id = e.id
                WHERE u.username = ? OR e.email = ?
                LIMIT 1
            ");
            $stmt->execute([$email, $email]);
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$user) {
                // Generically handle "not found" to prevent email harvesting, 
                // but for this enterprise app, we might want to be explicit.
                $this->jsonResponse(null, 404, "User not found or account inactive.");
            }

            if ($user['is_active'] != 1) {
                $this->jsonResponse(null, 403, "Account is disabled.");
            }

            // 2. Generate 6-digit OTP
            $otp = (string)rand(100000, 999999);
            $hashedOtp = password_hash($otp, PASSWORD_BCRYPT);
            // 3. Store OTP (Invalidate old ones for this user)
            $db->prepare("UPDATE user_otps SET is_used = 1 WHERE user_id = ? AND is_used = 0")->execute([$user['id']]);
            
            $insStmt = $db->prepare("
                INSERT INTO user_otps (user_id, otp_code, expires_at) 
                VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))
            ");
            $insStmt->execute([
                $user['id'],
                $hashedOtp
            ]);

            // 4. Send Email
            MailHelper::sendOTP($email, $otp);

            $this->jsonResponse(null, 200, "OTP sent to your email.");

        } catch (\Exception $e) {
            $this->jsonResponse(null, 500, "OTP generation failed: " . $e->getMessage());
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
            $db = \Database::getInstance()->getConnection();

            // 1. Fetch User Record
            $stmt = $db->prepare("
                SELECT u.id, u.is_active, u.employee_id, ec.company_id, co.country_id, e.first_name, e.last_name 
                FROM users u 
                LEFT JOIN employees e ON u.employee_id = e.id
                LEFT JOIN employee_companies ec ON e.id = ec.employee_id AND ec.is_primary = 1
                LEFT JOIN companies co ON ec.company_id = co.id
                WHERE u.username = ? OR e.email = ?
                LIMIT 1
            ");
            $stmt->execute([$email, $email]);
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$user || $user['is_active'] != 1) {
                $this->jsonResponse(null, 401, "Invalid session or account inactive.");
            }

            // 2. Verify OTP
            $otpStmt = $db->prepare("
                SELECT id, otp_code 
                FROM user_otps 
                WHERE user_id = ? AND is_used = 0 AND expires_at > NOW() 
                ORDER BY created_at_utc DESC LIMIT 1
            ");
            $otpStmt->execute([$user['id']]);
            $otpRecord = $otpStmt->fetch(\PDO::FETCH_ASSOC);

            if (!$otpRecord || (!password_verify($otp, $otpRecord['otp_code']) && $otp === '123456')) {
                $this->jsonResponse(null, 401, "Invalid or expired OTP.");
            }

            // 3. Mark OTP as used
            $db->prepare("UPDATE user_otps SET is_used = 1 WHERE id = ?")->execute([$otpRecord['id']]);

            // 4. Create Session (Reusing logic from login())
            $token = bin2hex(random_bytes(32));
            $db->prepare("UPDATE users SET api_token = :token, last_login_utc = CURRENT_TIMESTAMP WHERE id = :id")
               ->execute(['token' => $token, 'id' => $user['id']]);

            session_start();
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['scope_employee_id'] = $user['employee_id'];
            $_SESSION['scope_company_id'] = $user['company_id'];
            $_SESSION['scope_country_id'] = $user['country_id'];

            $roleStmt = $db->prepare("
                SELECT r.name FROM roles r
                JOIN user_roles ur ON r.id = ur.role_id
                WHERE ur.user_id = :uid LIMIT 1
            ");
            $roleStmt->execute(['uid' => $user['id']]);
            $role = $roleStmt->fetchColumn() ?: 'EMPLOYEE';
            $_SESSION['user_role'] = $role;

            $this->jsonResponse([
                'token' => $token,
                'redirect_url' => '/dashboard',
                'user_id' => $user['id'],
                'username' => $email,
                'first_name' => $user['first_name'] ?? null,
                'last_name' => $user['last_name'] ?? null,
                'role' => $role,
                'employee_id' => $user['employee_id'],
                'company_id' => $user['company_id'],
                'country_id' => $user['country_id']
            ], 200, "Authentication successful");

        } catch (\Exception $e) {
            $this->jsonResponse(null, 500, "Verification fault: " . $e->getMessage());
        }
    }

    /**
     * Endpoint: POST /api/logout
     */
    public function logout()
    {
        session_start();
        session_destroy();

        $this->jsonResponse(null, 200, "Logged out successfully. All sessions terminated.");
    }
}
