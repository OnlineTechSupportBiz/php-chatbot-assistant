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
 * Session management helper.
 *
 * Handles secure session configuration, regeneration, CSRF tokens,
 * Remember Me cookie extension, and database-backed session storage.
 *
 * Timeout model:
 *   SESSION_LIFETIME — cookie lifetime in minutes. The session cookie
 *   expires after this period of inactivity. When "Remember Me" is
 *   checked on login, the cookie is extended to 30 days.
 */
class Session
{
    /**
     * Start a secure PHP session.
     */
    public static function start(array $config = []): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $lifetime = ($config['lifetime'] ?? 120) * 60;

        // Secure cookie params
        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path'     => '/',
            'domain'   => '',
            'secure'   => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_name($config['cookie_name'] ?? 'chatbot_session');
        session_start();

        // Track session creation time
        if (!isset($_SESSION['_created'])) {
            $_SESSION['_created'] = time();
        }

        // Periodically rotate the session ID so a stolen ID is short-lived
        $rotationInterval = ($config['rotation_interval'] ?? 30) * 60;
        if (isset($_SESSION['_last_regenerated'])) {
            if (time() - $_SESSION['_last_regenerated'] >= $rotationInterval) {
                session_regenerate_id(true);
                $_SESSION['_last_regenerated'] = time();
            }
        } else {
            $_SESSION['_last_regenerated'] = time();
        }

        // Refresh the cookie on each request so the lifetime slides forward
        // (resets the expiry window with each page load)
        if (!empty($_SESSION['_remember_me'])) {
            self::extendCookie(30 * 86400); // 30 days
        } else {
            self::extendCookie($lifetime);
        }
    }

    /**
     * Set the session cookie with a specific lifetime.
     */
    private static function extendCookie(int $ttl): void
    {
        $params = session_get_cookie_params();
        setcookie(session_name(), session_id(), [
            'expires'  => time() + $ttl,
            'path'     => $params['path'],
            'domain'   => $params['domain'],
            'secure'   => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'],
        ]);
    }

    /**
     * Destroy the session.
     */
    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $params['path'],
                'domain'   => $params['domain'],
                'secure'   => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'],
            ]);
        }
        session_destroy();
    }

    /**
     * Authenticate a user into the session.
     *
     * @param bool $remember When true, sets a persistent cookie (30 day lifetime).
     */
    public static function login(array $user, bool $remember = false): void
    {
        session_regenerate_id(true);
        $_SESSION['_created']   = time();
        $_SESSION['_last_regenerated'] = time();
        $_SESSION['user_id']    = (int) $user['id'];
        $_SESSION['admin_id']  = (int) $user['admin_id'];
        $_SESSION['user_role']  = $user['role'];
        $_SESSION['user_name']  = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        // Use the user's own brand_name; fall back to default
        $_SESSION['brand_name'] = ($user['brand_name'] ?? '') ?: 'Chatbot Assistant';
        $_SESSION['company_name'] = $user['company_name'] ?? '';
        $_SESSION['timezone'] = $user['timezone'] ?? 'UTC';

        if ($remember) {
            $_SESSION['_remember_me'] = true;
            self::extendCookie(30 * 86400);
        } else {
            unset($_SESSION['_remember_me']);
        }
    }

    /**
     * Logout — destroy session and clear remember-me flag.
     */
    public static function logout(): void
    {
        $_SESSION = [];
        self::destroy();
    }

    /**
     * Check if a user is authenticated.
     */
    public static function isAuthenticated(): bool
    {
        return !empty($_SESSION['user_id']);
    }

    /**
     * Get authenticated user's ID.
     */
    public static function userId(): ?int
    {
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    /**
     * Get authenticated user's admin ID.
     */
    public static function adminId(): ?int
    {
        return isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null;
    }

    /**
     * Get authenticated user's role.
     */
    public static function userRole(): ?string
    {
        return $_SESSION['user_role'] ?? null;
    }

    /**
     * Generate and store a CSRF token.
     */
    public static function csrfToken(): string
    {
        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf_token'];
    }

    /**
     * Output a hidden CSRF token field.
     */
    public static function csrfField(): void
    {
        echo '<input type="hidden" name="_csrf" value="' . self::csrfToken() . '">' . "\n";
    }

    /**
     * Validate a CSRF token.
     */
    public static function validateCsrf(string $token): bool
    {
        if (empty($_SESSION['_csrf_token']) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION['_csrf_token'], $token);
    }

    /**
     * Set a flash message (survives one redirect).
     *
     * @param string|array $message
     */
    public static function flash(string $key, string|array $message): void
    {
        $_SESSION['_flash'][$key] = $message;
    }

    /**
     * Get and clear a flash message.
     */
    public static function getFlash(string $key): string|array|null
    {
        $value = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return $value;
    }

    /**
     * Set a session value.
     */
    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    /**
     * Get a session value.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Remove a session value.
     */
    public static function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }
}
