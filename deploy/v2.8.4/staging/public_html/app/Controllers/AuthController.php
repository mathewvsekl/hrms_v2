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
                $_SESSION['user_role'] = $role;

                $_SESSION['associated_company_ids'] = $authService->getAssociatedCompanyIds(
                    $user['employee_id'] ? (int)$user['employee_id'] : null,
                    $user['company_id'] ? (int)$user['company_id'] : null
                );

                $this->jsonResponse([
                    'token' => $token,
                    'redirect_url' => '/dashboard',
                    'user_id' => $user['id'],
                    'username' => $username,
                    'first_name' => $user['first_name'] ?? null,
                    'last_name' => $user['last_name'] ?? null,
                    'role' => $role,
                    'designation' => $user['designation'] ?? null,
                    'employee_id' => $user['employee_id'],
                    'company_id' => $user['company_id'],
                    'country_id' => $user['country_id']
                ], 200, "Authentication successful");

            } else {
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

        try {
            $authService = new \App\Services\AuthService();
            $user = $authService->findUserByIdentifier($email);

            if (!$user) {
                $this->jsonResponse(null, 404, "User not found or account inactive.");
            }

            if ($user['is_active'] != 1) {
                $this->jsonResponse(null, 403, "Account is disabled.");
            }

            // 2. Generate 6-digit OTP
            $otp = (string)rand(100000, 999999);
            $authService->createOTP((int)$user['id'], $otp);

            // 4. Send Email
            MailHelper::sendOTP($email, $otp);

            $responseData = ["message" => "OTP sent to your email (Check tmp/mail_log.txt)"];
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
                $this->jsonResponse(null, 401, "Invalid session or account inactive.");
            }

            if (!$authService->verifyOTP((int)$user['id'], $otp)) {
                $this->jsonResponse(null, 401, "Invalid or expired OTP.");
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
            $_SESSION['user_role'] = $role;

            $_SESSION['associated_company_ids'] = $authService->getAssociatedCompanyIds(
                $user['employee_id'] ? (int)$user['employee_id'] : null,
                $user['company_id'] ? (int)$user['company_id'] : null
            );

            $this->jsonResponse([
                'token' => $token,
                'redirect_url' => '/dashboard',
                'user_id' => $user['id'],
                'username' => $email,
                'first_name' => $user['first_name'] ?? null,
                'last_name' => $user['last_name'] ?? null,
                'role' => $role,
                'designation' => $user['designation'] ?? null,
                'employee_id' => $user['employee_id'],
                'company_id' => $user['company_id'],
                'country_id' => $user['country_id']
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
}
