<?php

/**
 * Copyright (c) 2026 Online Tech Support, LLC
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

declare(strict_types=1);

namespace App\Controller;

use App\Auth\Auth;
use App\Auth\Session;
use App\Http\Request;
use App\Http\Response;
use App\Model\AuditLog;
use App\Model\Chatbot;
use App\Model\Conversation;
use App\Model\Document;
use App\Model\Message;
use App\Model\Setting;
use App\Model\Admin;
use App\Model\UserPermission;
use App\Model\User;
use PDO;

/**
 * Every action in here requires admin role.
 *
 * @package App\Controller
 */
class AdminController
{
    /**
     * GET /admin — Unified admin dashboard (admin only).
     */
    public function dashboard(Request $req, Response $res): void
    {
        $user = Auth::requireAuth();
        Auth::requireAdmin($user);

        $db = \getDb();

        // Registration toggle
        $registrationEnabled = (Setting::get('registration_enabled', '1') === '1');

        // Top users by chatbot count (top 5)
        $stmt = $db->query(
            'SELECT t.id, t.name,
                    COUNT(DISTINCT c.id) AS chatbot_count,
                    COUNT(DISTINCT d.id) AS doc_count
             FROM users t
             LEFT JOIN chatbots c ON c.created_by = t.id
             LEFT JOIN documents d ON d.chatbot_id = c.id
             WHERE t.role = \'user\'
             GROUP BY t.id, t.name
             ORDER BY chatbot_count DESC
             LIMIT 5'
        );
        $topUsers = $stmt->fetchAll();

        // All users (full table with search)
        $search = trim((string) $req->get('q'));

        if ($search !== '') {
            $stmt = $db->prepare(
                'SELECT t.id, t.name, t.slug, t.admin_id, t.company_name,
                        t.is_active, t.created_at,
                        COUNT(DISTINCT c.id) AS chatbot_count
                 FROM users t
                 LEFT JOIN chatbots c ON c.created_by = t.id
                 WHERE t.role = \'user\'
                   AND (t.company_name LIKE :q OR t.name LIKE :q2 OR t.email LIKE :q3)
                 GROUP BY t.id
                 ORDER BY t.name ASC'
            );
            $like = '%' . $search . '%';
            $stmt->bindValue(':q', $like, \PDO::PARAM_STR);
            $stmt->bindValue(':q2', $like, \PDO::PARAM_STR);
            $stmt->bindValue(':q3', $like, \PDO::PARAM_STR);
        } else {
            $stmt = $db->query(
                'SELECT t.id, t.name, t.slug, t.admin_id, t.company_name,
                        t.is_active, t.created_at,
                        COUNT(DISTINCT c.id) AS chatbot_count
                 FROM users t
                 LEFT JOIN chatbots c ON c.created_by = t.id
                 WHERE t.role = \'user\'
                 GROUP BY t.id
                 ORDER BY t.name ASC'
            );
        }
        $stmt->execute();
        $users = $stmt->fetchAll();

        $pageTitle = 'Admin Dashboard - ' . ($user['brand_name'] ?? 'Chatbot Assistant');
        ob_start();
        require __DIR__ . '/../Views/admin/dashboard.php';
        $pageContent = ob_get_clean();
        require __DIR__ . '/../Views/admin/layout.php';
    }

    /**
     * POST /admin/registration — Toggle new user registration.
     */
    public function updateRegistration(Request $req, Response $res): void
    {
        $user = Auth::requireAuth();
        Auth::requireAdmin($user);

        // ── CSRF ──
        $csrf = (string) $req->get('_csrf');
        if (!Session::validateCsrf($csrf)) {
            Session::flash('error', 'Invalid form token. Please try again.');
            $res->redirect('/admin')->send();
            return;
        }

        $enabled = $req->get('registration_enabled') === '1' ? '1' : '0';
        Setting::set('registration_enabled', $enabled);

        AuditLog::log(
            null,
            (int) $user['id'],
            'update_settings',
            'platform',
            null,
            null,
            ['registration_enabled' => $enabled]
        );

        Session::flash('success', 'Registration setting saved.');
        $res->redirect('/admin')->send();
    }

    /**
     * POST /admin/settings/timezone — Update super admin's timezone preference.
     */
    public function updateTimezone(Request $req, Response $res): void
    {
        $user = Auth::requireAuth();
        Auth::requireAdmin($user);

        // ── CSRF ──
        $csrf = (string) $req->get('_csrf');
        if (!Session::validateCsrf($csrf)) {
            Session::flash('error', 'Invalid form token. Please try again.');
            $res->redirect('/admin/settings')->send();
            return;
        }

        $timezone = trim((string) $req->get('timezone', 'UTC'));

        $allowed = \App\Util\DateTimeHelper::TIMEZONES;
        if (!isset($allowed[$timezone])) {
            Session::flash('error', 'Invalid timezone selected.');
            $res->redirect('/admin/settings')->send();
            return;
        }

        $db = \getDb();
        $stmt = $db->prepare('UPDATE users SET timezone = :tz WHERE id = :id');
        $stmt->bindValue(':tz', $timezone, \PDO::PARAM_STR);
        $stmt->bindValue(':id', (int) $user['id'], \PDO::PARAM_INT);
        $stmt->execute();

        $_SESSION['timezone'] = $timezone;

        Session::flash('success', 'Timezone updated to ' . htmlspecialchars($timezone) . '.');
        $res->redirect('/admin/settings')->send();
    }

    /**
     * GET /admin/admins — List all admin accounts.
     */
    public function admins(Request $req, Response $res): void
    {
        $user = Auth::requireAuth();
        Auth::requireAdmin($user);

        $db = \getDb();
        $search = trim((string) $req->get('q'));

        if ($search !== '') {
            $stmt = $db->prepare(
                'SELECT t.id, t.company_name AS name, t.slug, t.created_at,
                        COUNT(DISTINCT u.id) AS user_count,
                        COUNT(DISTINCT c.id) AS chatbot_count
                 FROM users t
                 LEFT JOIN users u ON u.admin_id = t.id
                 LEFT JOIN chatbots c ON c.admin_id = t.id
                 WHERE t.role = \'admin\' AND (t.company_name LIKE :q OR t.slug LIKE :q2)
                 GROUP BY t.id
                 ORDER BY t.company_name ASC'
            );
            $like = '%' . $search . '%';
            $stmt->bindValue(':q', $like, PDO::PARAM_STR);
            $stmt->bindValue(':q2', $like, PDO::PARAM_STR);
        } else {
            $stmt = $db->query(
                'SELECT t.id, t.company_name AS name, t.slug, t.created_at,
                        COUNT(DISTINCT u.id) AS user_count,
                        COUNT(DISTINCT c.id) AS chatbot_count
                 FROM users t
                 LEFT JOIN users u ON u.admin_id = t.id
                 LEFT JOIN chatbots c ON c.admin_id = t.id
                 WHERE t.role = \'admin\'
                 GROUP BY t.id
                 ORDER BY t.company_name ASC'
            );
        }
        $stmt->execute();
        $admins = $stmt->fetchAll();

        $pageTitle = 'Admins - ' . ($user['brand_name'] ?? 'Chatbot Assistant');

        // Flash messages for permission save feedback
        $flashSuccess = Session::getFlash('success');

        ob_start();
        require __DIR__ . '/../Views/admin/admins.php';
        $pageContent = ob_get_clean();
        require __DIR__ . '/../Views/admin/layout.php';
    }

    /**
     * GET /admin/users/{id}/permissions — Edit a user account's feature permissions.
     */
    public function userPermissions(Request $req, Response $res, array $params = []): void
    {
        $user = Auth::requireAuth();
        Auth::requireAdmin($user);

        $userId = (int) ($params['id'] ?? 0);
        $targetUser = \App\Model\User::find($userId);
        if (!$targetUser) {
            $res->html('<h1>User not found.</h1>', 404)->send();
            return;
        }

        $permissions     = UserPermission::getByUser($userId);
        $endpointToggles = UserPermission::endpointToggles();
        $flashSuccess    = Session::getFlash('success');

        $companyName = $targetUser['company_name'] ?? $targetUser['name'] ?? 'User';
        $pageTitle = 'Permissions - ' . htmlspecialchars($companyName);
        ob_start();
        require __DIR__ . '/../Views/admin/user_permissions.php';
        $pageContent = ob_get_clean();
        require __DIR__ . '/../Views/admin/layout.php';
    }

    /**
     * POST /admin/users/{id}/permissions — Save user permissions.
     */
    public function updateUserPermissions(Request $req, Response $res, array $params = []): void
    {
        $user = Auth::requireAuth();
        Auth::requireAdmin($user);

        $userId = (int) ($params['id'] ?? 0);
        $targetUser = \App\Model\User::find($userId);
        if (!$targetUser) {
            $res->html('<h1>User not found.</h1>', 404)->send();
            return;
        }

        // Determine entity type based on target user's role
        $entityType = ($targetUser['role'] ?? '') === 'admin' ? 'admin' : 'user';

        // CSRF
        $csrf = (string) $req->get('_csrf');
        if (!Session::validateCsrf($csrf)) {
            Session::flash('error', 'Invalid form token.');
            $res->redirect('/admin/users/' . $userId . '/permissions')->send();
            return;
        }

        $knownPerms = UserPermission::allPermissions();
        $submitted = $req->get('perms', []);
        $perms = [];
        foreach ($knownPerms as $key) {
            $perms[$key] = !empty($submitted[$key]) ? 1 : 0;
        }
        UserPermission::bulkSetForUser($userId, $perms);

        // ── Account active status ──
        $isActive = (int) ($req->get('is_active') ? 1 : 0);
        \App\Model\User::update($userId, ['is_active' => $isActive]);

        // ── Password change (admin override, no email) ──
        $newPassword = trim((string) $req->get('new_password'));
        $confirm     = trim((string) $req->get('new_password_confirm'));
        $passwordChanged = false;
        if ($newPassword !== '') {
            if (strlen($newPassword) < 8) {
                Session::flash('error', 'Password must be at least 8 characters.');
                $res->redirect('/admin/users/' . $userId . '/permissions')->send();
                return;
            }
            if ($newPassword !== $confirm) {
                Session::flash('error', 'Password confirmation does not match.');
                $res->redirect('/admin/users/' . $userId . '/permissions')->send();
                return;
            }
            $hash = password_hash($newPassword, PASSWORD_ARGON2ID);
            \App\Model\User::updatePassword($userId, $hash);
            AuditLog::log(
                (int) $user['id'],
                (int) $user['id'],
                'change_password',
                $entityType,
                $userId,
                null,
                ['admin_override' => true]
            );
            $passwordChanged = true;
        }

        $companyName = $targetUser['company_name'] ?? $targetUser['name'] ?? 'User';

        $logChanges = [
            'permissions' => $perms,
            'is_active'   => $isActive,
        ];
        $oldChanges = [
            'permissions' => UserPermission::getByUser($userId),
            'is_active'   => (int) ($targetUser['is_active'] ?? 1),
        ];
        if ($passwordChanged) {
            $logChanges['password_changed'] = true;
            $oldChanges['password_changed'] = false;
        }
        // Determine specific action based on is_active change
        $oldActive = $oldChanges['is_active'];
        $newActive = $logChanges['is_active'];
        if ($oldActive === 0 && $newActive === 1) {
            $action = 'verify_user';
        } elseif ($oldActive === 1 && $newActive === 0) {
            $action = 'deactivate_user';
        } else {
            $action = 'update_user_permissions';
        }

        AuditLog::log($user['id'] ?? 0, $userId, $action, $entityType, $userId, $oldChanges, $logChanges);

        $parts = ['Permissions updated for <strong>' . htmlspecialchars($companyName) . '</strong>'];
        if ($passwordChanged) {
            $parts[] = 'Password changed.';
        }
        Session::flash('success', implode(' ', $parts));
        $res->redirect('/admin/users/' . $userId . '/permissions')->send();
    }

    /**
     * GET /admin/api/stats — JSON endpoint for dashboard chart data.
     */
    public function apiStats(Request $req, Response $res): void
    {
        $user = Auth::requireAuth();
        Auth::requireAdmin($user);

        $db = \getDb();

        // Last 30 days message activity (all admins)
        $stmt = $db->query(
            "SELECT DATE(created_at) AS date, COUNT(*) AS count
             FROM messages
             WHERE created_at >= CURRENT_DATE - INTERVAL '30 days'
             GROUP BY DATE(created_at)
             ORDER BY date ASC"
        );
        $chart = $stmt->fetchAll();

        // New admin/tenants per day (last 30)
        $stmt = $db->query(
            "SELECT DATE(created_at) AS date, COUNT(*) AS count
             FROM users
             WHERE role = 'admin' AND created_at >= CURRENT_DATE - INTERVAL '30 days'
             GROUP BY DATE(created_at)
             ORDER BY date ASC"
        );
        $adminGrowth = $stmt->fetchAll();

        $res->json([
            'message_chart'  => $chart,
            'admin_growth'   => $adminGrowth,
            'total_admins'   => (int) $db->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn(),
            'total_users'    => (int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn(),
            'total_chatbots' => (int) $db->query('SELECT COUNT(*) FROM chatbots')->fetchColumn(),
            'total_messages' => (int) $db->query('SELECT COUNT(*) FROM messages')->fetchColumn(),
        ])->send();
    }

    // ── Audit Log ──────────────────────────────────────────────────────────

    /**
     * GET /admin/audit-log — Paginated audit log across all tenants (admin only).
     */
    public function auditLog(Request $req, Response $res): void
    {
        $user = Auth::requireAuth();
        Auth::requireAdmin($user);

        $page     = max(1, (int) $req->get('page', '1'));
        $perPage  = 50;
        $action   = (string) $req->get('action', '');
        $entity   = (string) $req->get('entity', '');

        $result  = AuditLog::findByAdmin((int) $user['id'], $action, $entity, $page, $perPage);
        $filters = AdminSettingsController::getAuditFilters((int) $user['id'], null);

        // Fetch user names for the user_ids in this page
        $userIds = array_unique(array_filter(array_map(fn($r) => $r['user_id'], $result['rows'])));
        $userNames = [];
        if (!empty($userIds)) {
            $placeholders = implode(',', array_fill(0, count($userIds), '?'));
            $stmt = \getDb()->prepare(
                "SELECT id, name FROM users WHERE id IN ({$placeholders})"
            );
            $stmt->execute(array_values($userIds));
            while ($row = $stmt->fetch()) {
                $userNames[(int) $row['id']] = $row['name'];
            }
        }

        $pageTitle = 'Audit Log - Admin - ' . ($user['brand_name'] ?? 'Chatbot Assistant');
        ob_start();
        require __DIR__ . '/../Views/admin/audit_log.php';
        $pageContent = ob_get_clean();
        require __DIR__ . '/../Views/admin/layout.php';
    }

    // ── MFA ────────────────────────────────────────────────────────────────

    /**
     * GET /admin/mfa/setup — Show MFA enrollment page (admin layout).
     */
    public function mfaSetup(Request $req, Response $res): void
    {
        $user = Auth::requireAuth();
        Auth::requireAdmin($user);

        $mfaSecret = Session::get('_mfa_pending_secret', '');

        $pageTitle = 'Setup Two-Factor Auth - ' . ($user['brand_name'] ?? 'Chatbot Assistant');
        ob_start();
        require __DIR__ . '/../Views/admin/mfa_enroll.php';
        $pageContent = ob_get_clean();
        require __DIR__ . '/../Views/admin/layout.php';
    }

    /**
     * POST /admin/mfa/enroll — Generate TOTP secret for admin.
     */
    public function mfaEnroll(Request $req, Response $res): void
    {
        $user = Auth::requireAuth();
        Auth::requireAdmin($user);

        $csrf = (string) $req->get('_csrf');
        if (!Session::validateCsrf($csrf)) {
            Session::flash('error', 'Invalid form token. Please try again.');
            $res->redirect('/admin/settings')->send();
            return;
        }

        $totp = \OTPHP\TOTP::create();
        $totp->setLabel($user['email']);
        $totp->setIssuer($user['brand_name'] ?? 'Chatbot Assistant');
        $secret = $totp->getSecret();

        Session::set('_mfa_pending_secret', $secret);

        AuditLog::log(
            (int) $user['id'],
            null,
            'enroll',
            'mfa',
            (int) $user['id'],
            null,
            ['mfa_enabled' => 0, 'status' => 'pending']
        );

        $mfaSecret = $secret;
        $pageTitle = 'Setup Two-Factor Auth - ' . ($user['brand_name'] ?? 'Chatbot Assistant');
        ob_start();
        require __DIR__ . '/../Views/admin/mfa_enroll.php';
        $pageContent = ob_get_clean();
        require __DIR__ . '/../Views/admin/layout.php';
    }

    /**
     * POST /admin/mfa/verify — Verify TOTP code and enable MFA for admin.
     */
    public function mfaVerify(Request $req, Response $res): void
    {
        $user = Auth::requireAuth();
        Auth::requireAdmin($user);

        $code   = trim((string) $req->get('code'));
        $secret = (string) Session::get('_mfa_pending_secret', '');

        $csrf = (string) $req->get('_csrf');
        if (!Session::validateCsrf($csrf)) {
            Session::flash('error', 'Invalid form token. Please try again.');
            $res->redirect('/admin/mfa/setup')->send();
            return;
        }

        if ($secret === '') {
            Session::flash('error', 'No pending MFA enrollment found. Please start again.');
            $res->redirect('/admin/mfa/setup')->send();
            return;
        }

        if (!preg_match('/^[0-9]{6}$/', $code)) {
            Session::flash('error', 'Invalid code format. Please enter a 6-digit code.');
            $res->redirect('/admin/mfa/setup')->send();
            return;
        }

        $totp = \OTPHP\TOTP::create($secret);
        $totp->setLabel($user['email']);

        if (!$totp->verify($code)) {
            Session::flash('error', 'Invalid verification code. Please try again.');
            $res->redirect('/admin/mfa/setup')->send();
            return;
        }

        // Generate recovery codes (10 codes)
        $recoveryCodes = [];
        for ($i = 0; $i < 10; $i++) {
            $recoveryCodes[] = strtoupper(
                substr(bin2hex(random_bytes(4)), 0, 5) . '-' . substr(bin2hex(random_bytes(4)), 0, 5)
            );
        }

        $hashedRecoveryCodes = array_map(function (string $code): string {
            return password_hash($code, PASSWORD_BCRYPT);
        }, $recoveryCodes);

        $db = \getDb();
        $stmt = $db->prepare(
            'UPDATE users SET mfa_secret = :secret, mfa_enabled = 1, mfa_recovery_codes = :recovery WHERE id = :id'
        );
        $stmt->bindValue(':secret', $secret, PDO::PARAM_STR);
        $stmt->bindValue(':recovery', json_encode($hashedRecoveryCodes), PDO::PARAM_STR);
        $stmt->bindValue(':id', $user['id'], PDO::PARAM_INT);
        $stmt->execute();

        Session::remove('_mfa_pending_secret');

        AuditLog::log(
            (int) $user['id'],
            null,
            'verify',
            'mfa',
            (int) $user['id'],
            ['mfa_enabled' => 0],
            ['mfa_enabled' => 1]
        );

        Session::flash('mfa_recovery_codes', $recoveryCodes);
        Session::flash('success', 'Two-factor authentication has been enabled successfully.');
        $res->redirect('/admin/settings')->send();
    }

    /**
     * POST /admin/mfa/disable — Disable MFA for admin.
     */
    public function mfaDisable(Request $req, Response $res): void
    {
        $user = Auth::requireAuth();
        Auth::requireAdmin($user);

        $csrf = (string) $req->get('_csrf');
        if (!Session::validateCsrf($csrf)) {
            Session::flash('error', 'Invalid form token. Please try again.');
            $res->redirect('/admin/settings')->send();
            return;
        }

        $db = \getDb();
        $stmt = $db->prepare(
            'UPDATE users SET mfa_secret = NULL, mfa_enabled = 0, mfa_recovery_codes = NULL WHERE id = :id'
        );
        $stmt->bindValue(':id', $user['id'], PDO::PARAM_INT);
        $stmt->execute();

        AuditLog::log(
            (int) $user['id'],
            null,
            'disable',
            'mfa',
            (int) $user['id'],
            ['mfa_enabled' => 1],
            ['mfa_enabled' => 0]
        );

        Session::flash('success', 'Two-factor authentication has been disabled.');
        $res->redirect('/admin/settings')->send();
    }

    // ── Admin Settings ────────────────────────────────────────────────────

    /**
     * GET /admin/settings — Show admin settings with brand name, MFA.
     */
    public function settings(Request $req, Response $res): void
    {
        $user   = Auth::requireAuth();
        Auth::requireAdmin($user);
        $adminId = (int) $user['id']; // admin IS the user

        // Full admin record (for name, etc.)
        $admin = Admin::find($adminId);
        if (!$admin) {
            http_response_code(404);
            echo '<h1>Admin not found</h1>';
            exit;
        }

        // MFA status
        $db = \getDb();
        $stmt = $db->prepare('SELECT mfa_enabled, mfa_recovery_codes FROM users WHERE id = :id');
        $stmt->bindValue(':id', $user['id'], \PDO::PARAM_INT);
        $stmt->execute();
        $userRecord = $stmt->fetch();
        $mfaEnabled = !empty($userRecord['mfa_enabled']);
        $recoveryCodes = [];

        // Recent audit log entries for this admin
        $auditLogEntries = AuditLog::findByAdmin($adminId, '', '', 1, 10);

        $pageTitle = 'Settings - ' . ($user['brand_name'] ?? 'Chatbot Assistant');
        ob_start();
        require __DIR__ . '/../Views/admin/settings.php';
        $pageContent = ob_get_clean();
        require __DIR__ . '/../Views/admin/layout.php';
    }

    /**
     * GET /admin/settings/php-info — Show PHP info in admin layout.
     */
    public function phpInfo(Request $req, Response $res): void
    {
        $user = Auth::requireAuth();
        Auth::requireAdmin($user);

        $config = [
            'php_version'              => PHP_VERSION,
            'sapi'                     => PHP_SAPI,
            'architecture'             => PHP_INT_SIZE === 8 ? '64-bit' : '32-bit',
            'php_ini_path'             => php_ini_loaded_file() ?: 'none',
            'server_platform'          => PHP_OS_FAMILY . ' (' . php_uname('r') . ')',
            'server_software'          => $_SERVER['SERVER_SOFTWARE'] ?? 'Built-in PHP Dev Server',

            // Critical
            'upload_max_filesize'      => ini_get('upload_max_filesize') ?: 'unknown',
            'post_max_size'            => ini_get('post_max_size') ?: 'unknown',
            'post_max_size_note'       => null,
            'memory_limit'             => ini_get('memory_limit') ?: 'unknown',
            'max_execution_time'       => ini_get('max_execution_time') ?: 'unknown',
            'max_input_time'           => ini_get('max_input_time') ?: 'unknown',
            'max_input_vars'           => ini_get('max_input_vars') ?: 'unknown',

            // Session
            'session_save_path'        => session_save_path() ?: 'unknown',
            'session_gc_maxlifetime'   => ini_get('session.gc_maxlifetime') ?: 'unknown',
            'session_cookie_name'      => session_name() ?: 'unknown',
            'session_cookie_lifetime'  => ini_get('session.cookie_lifetime') ?: '0',
            'session_cookie_samesite'  => ini_get('session.cookie_samesite') ?: 'Lax',
            'session_cookie_secure'    => ini_get('session.cookie_secure') ?: '0',
            'session_cookie_httponly'  => ini_get('session.cookie_httponly') ?: '1',

            // Upload & File
            'file_uploads'             => ini_get('file_uploads') ?: '0',
            'allow_url_fopen'          => ini_get('allow_url_fopen') ?: '0',
            'allow_url_include'        => ini_get('allow_url_include') ?: '0',

            // Error Reporting
            'display_errors'           => ini_get('display_errors') ?: '0',
            'display_startup_errors'   => ini_get('display_startup_errors') ?: '0',
            'error_reporting'          => self::errorReportingName(error_reporting()),
            'log_errors'               => ini_get('log_errors') ?: '0',
            'error_log'                => ini_get('error_log') ?: 'unknown',

            // Timezone
            'date_timezone'            => ini_get('date.timezone') ?: 'UTC',
            'default_charset'          => ini_get('default_charset') ?: 'UTF-8',

            // Extensions
            'pdo_drivers'              => PDO::getAvailableDrivers(),
            'loaded_extensions'        => get_loaded_extensions(),
        ];

        // post_max_size should be >= upload_max_filesize
        $uploadBytes = self::shorthandToBytes($config['upload_max_filesize']);
        $postBytes   = self::shorthandToBytes($config['post_max_size']);
        if ($postBytes > 0 && $uploadBytes > 0 && $postBytes < $uploadBytes) {
            $config['post_max_size_note'] = 'warning';
        }

        $pageTitle = 'PHP Info - ' . ($user['brand_name'] ?? 'Chatbot Assistant');
        ob_start();
        require __DIR__ . '/../Views/admin/php_info.php';
        $pageContent = ob_get_clean();
        require __DIR__ . '/../Views/admin/layout.php';
    }

    /**
     * POST /admin/settings/brand-name — Update the admin's brand name.
     */
    public function updateBrandName(Request $req, Response $res): void
    {
        $user = Auth::requireAuth();
        Auth::requireAdmin($user);
        $userId = (int) $user['id'];

        // ── CSRF ──
        $csrf = (string) $req->get('_csrf');
        if (!Session::validateCsrf($csrf)) {
            Session::flash('error', 'Invalid form token. Please try again.');
            $res->redirect('/admin/settings')->send();
            return;
        }

        $brandName = trim((string) $req->get('brand_name', ''));
        if ($brandName === '') {
            Session::flash('error', 'Brand name cannot be empty.');
            $res->redirect('/admin/settings')->send();
            return;
        }

        if (mb_strlen($brandName) > 255) {
            Session::flash('error', 'Brand name must be 255 characters or fewer.');
            $res->redirect('/admin/settings')->send();
            return;
        }

        $db = \getDb();

        // Fetch current brand_name for audit trail
        $stmt = $db->prepare('SELECT brand_name FROM users WHERE id = :id');
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $oldBrandName = (string) ($stmt->fetchColumn() ?: '');

        $stmt = $db->prepare('UPDATE users SET brand_name = :brand_name WHERE id = :id');
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':brand_name', $brandName, PDO::PARAM_STR);
        $stmt->execute();

        // Update session
        Session::set('brand_name', $brandName);
        Session::set('user', array_merge(Session::get('user') ?: [], ['brand_name' => $brandName]));

        AuditLog::log(
            (int) $userId,
            null,
            'update',
            'brand_name',
            (int) $userId,
            ['brand_name' => $oldBrandName],
            ['brand_name' => $brandName]
        );

        Session::flash('success', 'Brand name updated successfully.');
        $res->redirect('/admin/settings')->send();
    }

    /**
     * Convert PHP shorthand (e.g. "128M", "1G") to bytes.
     */
    private static function shorthandToBytes(string $shorthand): int
    {
        $shorthand = trim($shorthand);
        if ($shorthand === '' || $shorthand === '-1') {
            return 0;
        }
        $value = (int) $shorthand;
        $last  = strtolower(substr($shorthand, -1));
        return match ($last) {
            'g' => $value * 1073741824,
            'm' => $value * 1048576,
            'k' => $value * 1024,
            default => $value,
        };
    }

    /**
     * Convert an E_* error reporting integer to a human-readable name.
     */
    private static function errorReportingName(int $level): string
    {
        if ($level === 0) {
            return '0 (none)';
        }
        $names = [];
        $constants = [
            E_ERROR           => 'E_ERROR',
            E_WARNING         => 'E_WARNING',
            E_PARSE           => 'E_PARSE',
            E_NOTICE          => 'E_NOTICE',
            E_CORE_ERROR      => 'E_CORE_ERROR',
            E_CORE_WARNING    => 'E_CORE_WARNING',
            E_COMPILE_ERROR   => 'E_COMPILE_ERROR',
            E_COMPILE_WARNING => 'E_COMPILE_WARNING',
            E_USER_ERROR      => 'E_USER_ERROR',
            E_USER_WARNING    => 'E_USER_WARNING',
            E_USER_NOTICE     => 'E_USER_NOTICE',
            E_STRICT          => 'E_STRICT',
            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            E_DEPRECATED      => 'E_DEPRECATED',
            E_USER_DEPRECATED => 'E_USER_DEPRECATED',
        ];
        foreach ($constants as $bit => $name) {
            if ($level & $bit) {
                $names[] = $name;
            }
        }
        return implode(' | ', $names);
    }
}
