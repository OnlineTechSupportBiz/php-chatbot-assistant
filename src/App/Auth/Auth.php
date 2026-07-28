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

namespace App\Auth;

/**
 * Authorization / role-checking helper.
 *
 * Called at the top of every Controller action — no middleware pattern.
 *
 * Usage:
 *   Auth::requireRole($user, ['admin', 'user']);
 *   Auth::requireAnyRole($user, ['admin', 'user']);
 *   Auth::requireSameAdmin($user, $chatbot);
 */
class Auth
{
    /**
     * Require the user to have one of the specified roles.
     * Sends 403 JSON/HTML and exits if unauthorized.
     */
    public static function requireRole(array $user, array $roles): void
    {
        if (!in_array($user['role'] ?? '', $roles, true)) {
            self::deny('Insufficient permissions.');
        }
    }

    /**
     * Require admin role.  Throws 403 if not.
     */
    public static function requireAdmin(?array $user): void
    {
        self::requireAuth();
        if (!in_array($user['role'] ?? '', ['admin'], true)) {
            http_response_code(403);
            echo '<h1>403 Forbidden</h1><p>Admin access required.</p>';
            exit;
        }
    }

    /**
     * Require a specific permission for the current user.
     *
     * Admin users bypass all admin-level checks.
     * Default behaviour: if no permission row exists for the admin, access is granted.
     *
     * NOTE: Only widget_* (endpoint) permissions are enforced for admin users.
     *       All page-level permissions (access_dashboard, manage_chatbots, etc.)
     *       are now bypassed — every admin user has access to every page.
     */
    public static function requirePermission(array $user, string $permission): void
    {
        if (in_array($user['role'] ?? '', ['admin'], true)) {
            return; // admin bypasses admin-level restrictions
        }
        // Page-level permissions (non-widget_*) are no longer enforced;
        // all admin users have full page access. Only endpoint toggles are checked.
        if (!str_starts_with($permission, 'widget_')) {
            return;
        }
        $userId = (int) ($user['id'] ?? 0);
        if ($userId <= 0) {
            self::deny('No user context for permission check.');
        }
        if (!\App\Model\UserPermission::has($userId, $permission)) {
            self::deny("Feature \"{$permission}\" is disabled for this account.");
        }
    }

    /**
     * Ensure the user belongs to the same admin account as the resource.
     * Pass the resource array (must have an 'admin_id' key).
     */
    public static function requireSameAdmin(array $user, ?array $resource): void
    {
        if ($resource === null) {
            self::deny('Resource not found.');
        }
        if (in_array($user['role'] ?? '', ['admin'], true)) {
            return; // admin can cross admins
        }
        if (($user['admin_id'] ?? null) !== ($resource['admin_id'] ?? null)) {
            self::deny('Cross-admin access denied.');
        }
    }

    /**
     * Require authentication. Redirects to login if not authenticated.
     * Also redirects admin users (admin users) away from user-facing pages to /admin.
     */
    public static function requireAuth(): array
    {
        if (!Session::isAuthenticated()) {
            self::redirectToLogin();
        }

        // Admin users (admin) should only access admin pages;
        // but allow /settings, /settings/*, and /api/* (data endpoints) as well
        // as /chatbots/* and /chatbots (their main management interface).
        if (in_array(Session::userRole(), ['admin'], true)) {
            $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
            if (
                !str_starts_with($path, '/admin')
                && !str_starts_with($path, '/settings')
                && !str_starts_with($path, '/api/')
                && !str_starts_with($path, '/chatbots')
                && !str_starts_with($path, '/dashboard')
            ) {
                header('Location: /admin');
                exit;
            }
        }

        // Reconstruct user array from session
        $brandName = ($_SESSION['brand_name'] ?? '') ?: 'Chatbot Assistant';
        return [
            'id'         => Session::userId(),
            'admin_id'   => Session::adminId(),
            'role'       => Session::userRole(),
            'name'       => $_SESSION['user_name'] ?? '',
            'email'      => $_SESSION['user_email'] ?? '',
            'brand_name' => $brandName ?: 'Chatbot Assistant',
            'company_name' => $_SESSION['company_name'] ?? '',
            'timezone'   => $_SESSION['timezone'] ?? 'UTC',
        ];
    }

    /**
     * Send a 403 response and terminate.
     */
    private static function deny(string $message): void
    {
        http_response_code(403);

        // Detect AJAX / API requests
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['error' => $message]);
            exit;
        }

        // Default: HTML response
        echo '<h1>403 Forbidden</h1><p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>';
        exit;
    }

    /**
     * Redirect to login page with return URL.
     */
    private static function redirectToLogin(): void
    {
        header('Location: /login');
        exit;
    }
}
