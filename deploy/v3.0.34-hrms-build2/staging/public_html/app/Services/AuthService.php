<?php

namespace App\Services;

/**
 * AuthService handles authentication and session logic.
 */
class AuthService
{
    /**
     * Authenticate a user by username and password.
     *
     * @param string $username
     * @param string $password
     * @return array|null User data or null if authentication fails
     */
    public function authenticate(string $username, string $password): ?array
    {
        $db = \Database::getInstance()->getConnection();

        $stmt = $db->prepare("
            SELECT u.id, u.password_hash, u.is_active, u.employee_id, ec.company_id, co.country_id, co.timezone as company_timezone, e.first_name, e.last_name, dg.title as designation 
            FROM users u 
            LEFT JOIN employees e ON u.employee_id = e.id
            LEFT JOIN employee_companies ec ON e.id = ec.employee_id AND ec.is_primary = 1 AND ec.is_active = 1
            LEFT JOIN companies co ON ec.company_id = co.id
            LEFT JOIN designations dg ON e.designation_id = dg.id
            WHERE u.username = :usr LIMIT 1
        ");
        $stmt->execute(['usr' => $username]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            return $user;
        }

        return null;
    }

    /**
     * Generate a new API token for a user.
     *
     * @param int $userId
     * @return string
     */
    public function generateToken(int $userId): string
    {
        $db = \Database::getInstance()->getConnection();
        $token = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $token);

        $updateStmt = $db->prepare("UPDATE users SET api_token = :token, last_login_utc = CURRENT_TIMESTAMP WHERE id = :id");
        $updateStmt->execute([
            'token' => $hashedToken,
            'id' => $userId
        ]);

        return $token;
    }

    /**
     * Get user role.
     *
     * @param int $userId
     * @return string
     */
    public function getUserRole(int $userId): string
    {
        $db = \Database::getInstance()->getConnection();
        $roleStmt = $db->prepare("
            SELECT COALESCE(br.name, r.name) as name 
            FROM roles r
            JOIN user_roles ur ON r.id = ur.role_id
            LEFT JOIN roles br ON r.base_role_id = br.id
            WHERE ur.user_id = :uid LIMIT 1
        ");
        $roleStmt->execute(['uid' => $userId]);
        return $roleStmt->fetchColumn() ?: 'Employee';
    }

    public function getUserPermissions(int $userId): array
    {
        $db = \Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT COALESCE(r.base_role_id, r.id) as canonical_role_id, p.module, p.action 
            FROM user_roles ur
            JOIN roles r ON ur.role_id = r.id
            LEFT JOIN role_permissions rp ON r.id = rp.role_id
            LEFT JOIN permissions p ON rp.permission_id = p.id
            WHERE ur.user_id = :user_id
        ");
        $stmt->execute(['user_id' => $userId]);
        $userPrivileges = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $roleIds = array_unique(array_map('intval', array_column($userPrivileges, 'canonical_role_id')));
        
        // SuperAdmins and Admins are the ultimate administrators and should not be locked out by UI misconfigurations
        if (in_array(\App\Helpers\RoleConstants::SUPER_ADMIN, $roleIds, true) || in_array(\App\Helpers\RoleConstants::ADMIN, $roleIds, true)) {
            return ['*'];
        }

        $permissions = [];
        foreach ($userPrivileges as $priv) {
            if (isset($priv['module']) && isset($priv['action'])) {
                $permissions[] = strtolower($priv['module']) . ':' . strtolower($priv['action']);
            }
        }
        return array_values(array_unique($permissions));
    }

    /**
     * Get associated company IDs for an employee.
     *
     * @param int|null $employeeId
     * @param int|null $primaryCompanyId
     * @return array
     */
    public function getAssociatedCompanyIds(?int $employeeId, ?int $primaryCompanyId): array
    {
        $db = \Database::getInstance()->getConnection();
        if ($employeeId) {
            $assocStmt = $db->prepare("SELECT company_id FROM employee_companies WHERE employee_id = ? AND is_active = 1");
            $assocStmt->execute([$employeeId]);
            return $assocStmt->fetchAll(\PDO::FETCH_COLUMN);
        }
        return $primaryCompanyId ? [$primaryCompanyId] : [];
    }

    /**
     * Find user by email or username.
     *
     * @param string $identifier
     * @return array|null
     */
    public function findUserByIdentifier(string $identifier): ?array
    {
        $db = \Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT u.id, u.is_active, u.employee_id, ec.company_id, co.country_id, co.timezone as company_timezone, e.first_name, e.last_name, dg.title as designation 
            FROM users u 
            LEFT JOIN employees e ON u.employee_id = e.id
            LEFT JOIN employee_companies ec ON e.id = ec.employee_id AND ec.is_primary = 1 AND ec.is_active = 1
            LEFT JOIN companies co ON ec.company_id = co.id
            LEFT JOIN designations dg ON e.designation_id = dg.id
            WHERE u.username = ? OR e.email = ?
            LIMIT 1
        ");
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $user ?: null;
    }

    /**
     * Create an OTP for a user.
     *
     * @param int $userId
     * @param string $otp
     * @return void
     */
    public function createOTP(int $userId, string $otp): void
    {
        $db = \Database::getInstance()->getConnection();
        $hashedOtp = password_hash($otp, PASSWORD_BCRYPT);

        // Invalidate old ones
        $db->prepare("UPDATE user_otps SET is_used = 1 WHERE user_id = ? AND is_used = 0")->execute([$userId]);
        
        $insStmt = $db->prepare("
            INSERT INTO user_otps (user_id, otp_code, expires_at) 
            VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))
        ");
        $insStmt->execute([$userId, $hashedOtp]);
    }

    /**
     * Verify OTP for a user.
     *
     * @param int $userId
     * @param string $otp
     * @return bool
     */
    public function verifyOTP(int $userId, string $otp): bool
    {
        $db = \Database::getInstance()->getConnection();
        $otpStmt = $db->prepare("
            SELECT id, otp_code 
            FROM user_otps 
            WHERE user_id = ? AND is_used = 0 AND expires_at > NOW() 
            ORDER BY created_at_utc DESC LIMIT 1
        ");
        $otpStmt->execute([$userId]);
        $otpRecord = $otpStmt->fetch(\PDO::FETCH_ASSOC);

        $isDevBypass = (defined('ENVIRONMENT') && ENVIRONMENT === 'development' && $otp === '123456');
        
        if ($otpRecord && (password_verify($otp, $otpRecord['otp_code']) || $isDevBypass)) {
            // Mark as used
            $db->prepare("UPDATE user_otps SET is_used = 1 WHERE id = ?")->execute([$otpRecord['id']]);
            return true;
        }

        return false;
    }
}
