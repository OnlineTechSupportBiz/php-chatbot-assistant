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
use App\Auth\RateLimiter;
use App\Auth\Session;
use App\Http\Request;
use App\Http\Response;
use App\Model\AuditLog;
use App\Model\MagicLink;
use App\Model\User;
use App\Model\UserPermission;
use App\Service\EmailService;
use PDO;

/**
 * Handles all authentication flows:
 *   - Registration (creates Admin + first user)
 *   - Email verification
 *   - Login (with rate limiting / lockout)
 *   - Logout
 *   - Password reset
 *   - MFA enrollment & verification (TOTP)
 */
class AuthController
{
    /**
     * POST /register
     * Creates a new admin account + first user.
     */
    public function register(Request $req, Response $res): void
    {
        $name     = trim((string) $req->get('name'));
        $email    = strtolower(trim((string) $req->get('email')));
        $password = (string) $req->get('password');
        $company  = trim((string) $req->get('company'));

        // ── CSRF ──
        $csrf = (string) $req->get('_csrf');
        if (!Session::validateCsrf($csrf)) {
            if ($req->wantsJson()) {
                $res->json(['error' => 'Invalid CSRF token.'], 419)->send();
            } else {
                Session::flash('error', 'Invalid form token. Please try again.');
                $res->redirect('/register')->send();
            }
            return;
        }

        // ── Registration enabled? ──
        if (\App\Model\Setting::get('registration_enabled', '1') !== '1') {
            if ($req->wantsJson()) {
                $res->json(['error' => 'New user registration is currently disabled.'], 403)->send();
            } else {
                Session::flash('error', 'New user registration is currently disabled.');
                $res->redirect('/login')->send();
            }
            return;
        }

        // ── Validation ──
        $errors = [];

        if ($name === '') {
            $errors[] = 'Name is required.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Valid email is required.';
        }
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }


        if ($company === '') {
            $errors[] = 'Company / organization name is required.';
        }

        // Check if email already exists
        if (empty($errors) && User::findByEmail($email)) {
            $errors[] = 'An account with this email already exists.';
        }

        if (!empty($errors)) {
            if ($req->wantsJson()) {
                $res->json(['errors' => $errors], 422)->send();
            } else {
                Session::flash('errors', $errors);
                Session::flash('old', [
                    'name'    => $name,
                    'email'   => $email,
                    'company' => $company,
                ]);
                $res->redirect('/register')->send();
            }
            return;
        }

        // ── Find the single admin account (created by installer) ──
        $adminStmt = \getDb()->prepare("SELECT id, company_name, slug FROM users WHERE role = 'admin' LIMIT 1");
        $adminStmt->execute();
        $adminRow = $adminStmt->fetch();
        if (!$adminRow) {
            if ($req->wantsJson()) {
                $res->json(['error' => 'Registration is not available yet. Please contact support.'], 503)->send();
            } else {
                Session::flash('error', 'Registration is not available yet. Please contact support.');
                $res->redirect('/register')->send();
            }
            return;
        }
        $adminId = (int) $adminRow['id'];

        // ── Create the user (role='user') belonging to this admin ──
        $passwordHash = password_hash($password, PASSWORD_ARGON2ID);
        $verifyToken = bin2hex(random_bytes(32));
        $slug = \App\Model\User::generateSlug($company);

        $userId = \App\Model\User::createUser([
            'name'               => $name,
            'email'              => $email,
            'password_hash'      => $passwordHash,
            'company_name'       => $company,
            'slug'               => $slug,
            'is_active'          => 1,
            'role'               => 'user',
            'email_verify_token' => $verifyToken,
            'admin_id'           => $adminId,
        ]);

        AuditLog::log($adminId, $userId, 'register', 'user', $userId, null, [
            'email' => $email,
            'role'  => 'user',
        ]);

        // ── Grant default widget permissions ──
        UserPermission::bulkSetForUser($userId, [
            'widget_chat'          => 1,
            'widget_quick_answers' => 1,
        ]);

        // ── Send verification email ──
        if (class_exists(EmailService::class)) {
            $mailer = new EmailService(\App\Model\Admin::getBrandName($adminId));
            $mailer->sendVerification($email, $verifyToken);
        }

        // ── Auto-login and redirect ──
        $user = User::find($userId);
        Session::login($user);

        Session::flash('success', 'Account created! Please check your email to verify your address.');
        $res->redirect('/dashboard')->send();
    }

    /**
     * GET /verify-email
     */
    public function verifyEmail(Request $req, Response $res): void
    {
        $token = (string) $req->get('token');

        if ($token === '') {
            $res->html('<h1>Invalid verification link.</h1>', 400)->send();
            return;
        }

        $user = User::findByVerifyToken($token);
        if (!$user) {
            $res->html('<h1>Invalid or expired verification link.</h1>', 400)->send();
            return;
        }

        User::verifyEmail((int) $user['id']);

        AuditLog::log(
            (int) $user['admin_id'],
            (int) $user['id'],
            'verify_email',
            'user',
            (int) $user['id']
        );

        Session::flash('success', 'Email verified successfully!');
        $res->redirect('/dashboard')->send();
    }

    /**
     * POST /resend-verification
     * Re-send the email verification link.
     */
    public function resendVerification(Request $req, Response $res): void
    {
        $email = strtolower(trim((string) $req->get('email')));

        // ── CSRF ──
        $csrf = (string) $req->get('_csrf');
        if (!Session::validateCsrf($csrf)) {
            Session::flash('error', 'Invalid form token. Please try again.');
            $res->redirect('/login')->send();
            return;
        }

        // ── Validation ──
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'Please enter a valid email address.');
            $res->redirect('/login')->send();
            return;
        }

        $user = User::findByEmail($email);
        if (!$user) {
            // Don't reveal whether the email exists
            Session::flash('success', 'If that email is registered, a new verification link has been sent.');
            $res->redirect('/login')->send();
            return;
        }

        // Already verified?
        if ($user['email_verified_at'] !== null) {
            Session::flash('success', 'If that email is registered, a new verification link has been sent.');
            $res->redirect('/login')->send();
            return;
        }

        // ── Generate new token ──
        $verifyToken = bin2hex(random_bytes(32));
        $db = \getDb();
        $stmt = $db->prepare(
            'UPDATE users SET email_verify_token = :token WHERE id = :id'
        );
        $stmt->bindValue(':token', $verifyToken, \PDO::PARAM_STR);
        $stmt->bindValue(':id', (int) $user['id'], \PDO::PARAM_INT);
        $stmt->execute();

        // ── Send email ──
        try {
            $mailer = new \App\Service\EmailService();
            $sent = $mailer->sendVerification($email, $verifyToken);
            if (!$sent) {
                error_log('Resend verification: EmailService returned false for ' . $email);
            }
        } catch (\Throwable $e) {
            error_log('Resend verification failed for ' . $email . ': ' . $e->getMessage());
        }

        Session::flash('success', 'If that email is registered, a new verification link has been sent.');
        $res->redirect('/login')->send();
    }

    /**
     * POST /login
     */
    public function login(Request $req, Response $res): void
    {
        // ── Per‑IP rate limit ──
        $ip = $req->clientIp();
        if (!RateLimiter::check($ip)) {
            if ($req->wantsJson()) {
                $res->json(['error' => 'Too many login attempts. Try again later.'], 429)->send();
            } else {
                Session::flash('error', 'Too many login attempts from your IP. Please wait 5 minutes.');
                $res->redirect('/login')->send();
            }
            return;
        }

        $email    = strtolower(trim((string) $req->get('email')));
        $password = (string) $req->get('password');
        $remember = (bool) $req->get('remember');

        // ── CSRF ──
        $csrf = (string) $req->get('_csrf');
        if (!Session::validateCsrf($csrf)) {
            if ($req->wantsJson()) {
                $res->json(['error' => 'Invalid CSRF token.'], 419)->send();
            } else {
                Session::flash('error', 'Invalid form token. Please try again.');
                $res->redirect('/login')->send();
            }
            return;
        }

        // Generic error — don't reveal whether email exists
        $genericError = 'Invalid email or password.';

        // ── Find user by email (single auth source) ──
        $user = User::findByEmail($email);
        if (!$user) {
            RateLimiter::record($ip);
            // Use constant-time comparison anyway to avoid timing leaks
            password_verify($password, PASSWORD_ARGON2ID);
            if ($req->wantsJson()) {
                $res->json(['error' => $genericError], 401)->send();
            } else {
                Session::flash('error', $genericError);
                $res->redirect('/login')->send();
            }
            return;
        }

        // ── Check account is active ──
        if (empty($user['is_active'])) {
            AuditLog::log(
                (int) $user['admin_id'],
                (int) $user['id'],
                'login_disabled',
                'user',
                (int) $user['id']
            );
            if ($req->wantsJson()) {
                $res->json(['error' => 'This account has been disabled.'], 403)->send();
            } else {
                Session::flash('error', 'This account has been disabled.');
                $res->redirect('/login')->send();
            }
            return;
        }

        // ── Check lockout ──
        if ($user['is_locked'] && $user['locked_until'] !== null) {
            $lockedUntil = strtotime($user['locked_until']);
            if (time() < $lockedUntil) {
                AuditLog::log(
                    (int) $user['admin_id'],
                    (int) $user['id'],
                    'login_locked',
                    'user',
                    (int) $user['id']
                );
                $res->json(['error' => 'Account is temporarily locked. Try again later.'], 429)->send();
                return;
            }
            // Lock expired — unlock
            User::unlockAccount((int) $user['id']);
        }

        // ── Verify password ──
        if (!password_verify($password, $user['password_hash'])) {
            User::incrementFailedAttempts((int) $user['id']);
            RateLimiter::record($ip);

            // Lock after 5 failed attempts
            $newAttempts = $user['failed_attempts'] + 1;
            if ($newAttempts >= 5) {
                User::lockAccount((int) $user['id'], 15);
                AuditLog::log(
                    (int) $user['admin_id'],
                    (int) $user['id'],
                    'account_locked',
                    'user',
                    (int) $user['id'],
                    null,
                    ['failed_attempts' => $newAttempts]
                );
            }

            AuditLog::log(
                (int) $user['admin_id'],
                (int) $user['id'],
                'login_failed',
                'user',
                (int) $user['id']
            );

            if ($req->wantsJson()) {
                $res->json(['error' => $genericError], 401)->send();
            } else {
                Session::flash('error', $genericError);
                $res->redirect('/login')->send();
            }
            return;
        }

        // ── Check email verified ──
        if ($user['email_verified_at'] === null) {
            AuditLog::log(
                (int) $user['admin_id'],
                (int) $user['id'],
                'login_unverified',
                'user',
                (int) $user['id']
            );
            if ($req->wantsJson()) {
                $res->json(['error' => 'Please verify your email before logging in.'], 403)->send();
            } else {
                Session::flash('error', 'Please verify your email before logging in.');
                $res->redirect('/login')->send();
            }
            return;
        }

        // ── Check MFA ──
        if (!empty($user['mfa_enabled']) && !empty($user['mfa_secret'])) {
            // Store user ID and remember-me flag pending MFA verification
            Session::set('_mfa_pending_user', [
                'user_id'   => (int) $user['id'],
                'admin_id' => (int) $user['admin_id'],
                'name'      => $user['name'],
                'email'     => $user['email'],
                'remember'  => $remember,
            ]);

            AuditLog::log(
                (int) $user['admin_id'],
                (int) $user['id'],
                'mfa_challenge_pending',
                'user',
                (int) $user['id']
            );

            $res->redirect('/mfa/challenge')->send();
            return;
        }

        // ── Success (no MFA) ──
        User::resetFailedAttempts((int) $user['id']);
        User::updateLastLogin((int) $user['id']);
        Session::login($user, $remember);
        RateLimiter::clear($ip);

        AuditLog::log(
            (int) $user['admin_id'],
            (int) $user['id'],
            'login',
            'user',
            (int) $user['id']
        );

        $redirectPath = in_array($user['role'] ?? '', ['admin'], true) ? '/admin' : '/dashboard';

        if ($req->wantsJson()) {
            $res->json(['success' => true, 'redirect' => $redirectPath])->send();
        } else {
            $res->redirect($redirectPath)->send();
        }
    }

    /**
     * POST /forgot-password
     */
    public function forgotPassword(Request $req, Response $res): void
    {
        $email = strtolower(trim((string) $req->get('email')));

        // ── CSRF ──
        $csrf = (string) $req->get('_csrf');
        if (!Session::validateCsrf($csrf)) {
            Session::flash('error', 'Invalid form token. Please try again.');
            $res->redirect('/forgot-password')->send();
            return;
        }

        // ── Validation ──
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'Please enter a valid email address.');
            $res->redirect('/forgot-password')->send();
            return;
        }

        // ── Ensure the email exists ──
        $user = User::findByEmail($email);
        if (!$user) {
            // Don't reveal whether the email exists — show generic success
            Session::flash('success', 'If that email is registered, a reset link has been sent.');
            $res->redirect('/forgot-password')->send();
            return;
        }

        // Rate-limit: only one reset token per email every 60 seconds
        $db = \getDb();
        $stmt = $db->prepare(
            'SELECT expires_at FROM password_resets WHERE email = :email AND used = 0 ORDER BY created_at DESC LIMIT 1'
        );
        $stmt->bindValue(':email', $email, PDO::PARAM_STR);
        $stmt->execute();
        $existing = $stmt->fetch();
        if ($existing && strtotime($existing['expires_at']) > time()) {
            // A token already exists and hasn't expired — don't generate another
            Session::flash('success', 'If that email is registered, a reset link has been sent.');
            $res->redirect('/forgot-password')->send();
            return;
        }

        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hour

        // Upsert: replace old token
        $stmt = $db->prepare(
            'REPLACE INTO password_resets (email, token, expires_at, used, created_at) VALUES (:email, :token, :expires, 0, NOW())'
        );
        $stmt->bindValue(':email', $email, PDO::PARAM_STR);
        $stmt->bindValue(':token', $token, PDO::PARAM_STR);
        $stmt->bindValue(':expires', $expiresAt, PDO::PARAM_STR);
        $stmt->execute();

        // ── Send email ──
        $mailer = new EmailService(\App\Model\Admin::getBrandName((int) $user['admin_id']));
        $mailer->sendPasswordReset($email, $token);

        AuditLog::log(
            (int) $user['admin_id'],
            (int) $user['id'],
            'password_reset_requested',
            $user['role'],
            (int) $user['id']
        );

        Session::flash('success', 'If that email is registered, a reset link has been sent.');
        $res->redirect('/forgot-password')->send();
    }

    /**
     * POST /reset-password
     */
    public function resetPassword(Request $req, Response $res): void
    {
        $token    = trim((string) $req->get('token'));
        $email    = strtolower(trim((string) $req->get('email')));
        $password = (string) $req->get('password');

        // ── CSRF ──
        $csrf = (string) $req->get('_csrf');
        if (!Session::validateCsrf($csrf)) {
            Session::flash('error', 'Invalid form token. Please try again.');
            $res->redirect('/reset-password?token=' . urlencode($token))->send();
            return;
        }

        // ── Validation ──
        $errors = [];
        if ($token === '') {
            $errors[] = 'Missing reset token.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Valid email is required.';
        }
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            $res->redirect('/reset-password?token=' . urlencode($token))->send();
            return;
        }

        // ── Validate token ──
        $db = \getDb();
        $stmt = $db->prepare(
            'SELECT * FROM password_resets WHERE email = :email AND token = :token AND used = 0 AND expires_at > NOW()'
        );
        $stmt->bindValue(':email', $email, PDO::PARAM_STR);
        $stmt->bindValue(':token', $token, PDO::PARAM_STR);
        $stmt->execute();
        $reset = $stmt->fetch();

        if (!$reset) {
            Session::flash('error', 'Invalid or expired reset link. Please request a new one.');
            $res->redirect('/forgot-password')->send();
            return;
        }

        // ── Find user by email ──
        $user = User::findByEmail($email);
        if (!$user) {
            Session::flash('error', 'Invalid or expired reset link. Please request a new one.');
            $res->redirect('/forgot-password')->send();
            return;
        }

        $passwordHash = password_hash($password, PASSWORD_ARGON2ID);

        // ── Update password ──
        User::updatePassword((int) $user['id'], $passwordHash);

        // ── Mark token as used ──
        $stmt = $db->prepare('UPDATE password_resets SET used = 1 WHERE email = :email AND token = :token');
        $stmt->bindValue(':email', $email, PDO::PARAM_STR);
        $stmt->bindValue(':token', $token, PDO::PARAM_STR);
        $stmt->execute();

        AuditLog::log(
            (int) $user['admin_id'],
            (int) $user['id'],
            'password_reset_completed',
            $user['role'],
            (int) $user['id']
        );

        Session::flash('success', 'Password reset successfully. Please sign in.');
        $res->redirect('/login')->send();
    }

    // ── Magic Link ─────────────────────────────────────────────────────────

    /**
     * POST /magic-login/send
     * Generate and email a magic link for password-less login.
     */
    public function sendMagicLink(Request $req, Response $res): void
    {
        $email = strtolower(trim((string) $req->get('email')));

        // ── CSRF ──
        $csrf = (string) $req->get('_csrf');
        if (!Session::validateCsrf($csrf)) {
            Session::flash('error', 'Invalid form token. Please try again.');
            $res->redirect('/login')->send();
            return;
        }

        // ── Validation ──
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'Please enter a valid email address.');
            $res->redirect('/login')->send();
            return;
        }

        // ── Find user ──
        $user = User::findByEmail($email);
        if (!$user) {
            // Don't reveal whether the email exists
            Session::flash('success', 'If that email is registered, a sign-in link has been sent.');
            $res->redirect('/login')->send();
            return;
        }

        // ── Check account is active ──
        if (empty($user['is_active'])) {
            Session::flash('error', 'This account has been disabled.');
            $res->redirect('/login')->send();
            return;
        }

        // ── Rate-limit: one token per email every 60 seconds ──
        if (MagicLink::hasRecentToken($email)) {
            Session::flash('success', 'If that email is registered, a sign-in link has been sent.');
            $res->redirect('/login')->send();
            return;
        }

        // ── Generate token ──
        $token     = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + 900); // 15 minutes

        MagicLink::createToken($email, (int) $user['admin_id'], $token, $expiresAt);

        // ── Send email ──
        $brandName = \App\Model\Admin::getBrandName((int) $user['admin_id']);
        $mailer = new EmailService($brandName);
        $mailer->sendMagicLink($email, $token);

        AuditLog::log(
            (int) $user['admin_id'],
            (int) $user['id'],
            'magic_link_sent',
            $user['role'],
            (int) $user['id']
        );

        require __DIR__ . '/../Views/auth/magic_link_sent.php';
    }

    /**
     * GET /magic-login
     * Verify a magic link token and log the user in.
     */
    public function verifyMagicLink(Request $req, Response $res): void
    {
        $token = trim((string) $req->get('token'));

        // ── Validate token ──
        if ($token === '') {
            require __DIR__ . '/../Views/auth/magic_link_invalid.php';
            return;
        }

        $row = MagicLink::findValidToken($token);
        if (!$row) {
            require __DIR__ . '/../Views/auth/magic_link_invalid.php';
            return;
        }

        // ── Find user by email ──
        $user = User::findByEmail($row['email']);
        if (!$user) {
            require __DIR__ . '/../Views/auth/magic_link_invalid.php';
            return;
        }

        // ── Check account is active ──
        if (empty($user['is_active'])) {
            Session::flash('error', 'This account has been disabled.');
            $res->redirect('/login')->send();
            return;
        }

        // ── Check lockout ──
        if ($user['is_locked'] && $user['locked_until'] !== null) {
            $lockedUntil = strtotime($user['locked_until']);
            if (time() < $lockedUntil) {
                $res->redirect('/login')->send();
                return;
            }
            User::unlockAccount((int) $user['id']);
        }

        // ── Check MFA ──
        if (!empty($user['mfa_enabled']) && !empty($user['mfa_secret'])) {
            Session::set('_mfa_pending_user', [
                'user_id'  => (int) $user['id'],
                'admin_id' => (int) $user['admin_id'],
                'name'     => $user['name'],
                'email'    => $user['email'],
                'remember' => false,
            ]);

            AuditLog::log(
                (int) $user['admin_id'],
                (int) $user['id'],
                'mfa_challenge_pending',
                'user',
                (int) $user['id']
            );

            // Mark token used and redirect to MFA
            MagicLink::markUsed((int) $row['id']);
            $res->redirect('/mfa/challenge')->send();
            return;
        }

        // ── Log in ──
        User::resetFailedAttempts((int) $user['id']);
        User::updateLastLogin((int) $user['id']);
        Session::login($user, false);
        MagicLink::markUsed((int) $row['id']);

        AuditLog::log(
            (int) $user['admin_id'],
            (int) $user['id'],
            'login',
            'user',
            (int) $user['id']
        );

        $redirectPath = in_array($user['role'] ?? '', ['admin'], true) ? '/admin' : '/dashboard';
        $res->redirect($redirectPath)->send();
    }

    // ── MFA / TOTP ─────────────────────────────────────────────────────────

    /**
     * POST /settings/mfa/enroll
     * Generate a new TOTP secret and show QR code for enrollment.
     */
    public function mfaEnroll(Request $req, Response $res): void
    {
        $user    = Auth::requireAuth();
        $isAdmin = ($user['role'] ?? '') === 'admin';

        // ── CSRF ──
        $csrf = (string) $req->get('_csrf');
        if (!Session::validateCsrf($csrf)) {
            Session::flash('error', 'Invalid form token. Please try again.');
            $res->redirect('/settings')->send();
            return;
        }

        // Generate new TOTP secret
        $totp = \OTPHP\TOTP::create();
        $totp->setLabel($user['email']);
        $brandName = !empty($user['brand_name']) ? $user['brand_name'] : (\App\Model\Admin::getBrandName((int) ($user['admin_id'] ?? 0)) ?: 'Chatbot Assistant');
        $totp->setIssuer($brandName);
        $secret = $totp->getSecret();

        // Store pending secret in session for verification
        Session::set('_mfa_pending_secret', $secret);

        $mfaSecret = $secret;
        require __DIR__ . '/../Views/auth/mfa_enroll.php';
    }

    /**
     * GET /settings/mfa/setup
     * Show MFA enrollment page (with existing pending secret if any).
     */
    public function mfaSetup(Request $req, Response $res): void
    {
        $user = Auth::requireAuth();

        $mfaSecret = Session::get('_mfa_pending_secret', '');
        $brandName = $user['brand_name'] ?? \App\Model\Admin::getBrandName();
        require __DIR__ . '/../Views/auth/mfa_enroll.php';
    }

    /**
     * POST /settings/mfa/verify
     * Verify a TOTP code and enable MFA for the user.
     */
    public function mfaVerify(Request $req, Response $res): void
    {
        $user    = Auth::requireAuth();
        $isAdmin = ($user['role'] ?? '') === 'admin';
        $code    = trim((string) $req->get('code'));
        $secret  = (string) Session::get('_mfa_pending_secret', '');

        // ── CSRF ──
        $csrf = (string) $req->get('_csrf');
        if (!Session::validateCsrf($csrf)) {
            Session::flash('error', 'Invalid form token. Please try again.');
            $res->redirect('/settings/mfa/setup')->send();
            return;
        }

        if ($secret === '') {
            Session::flash('error', 'No pending MFA enrollment found. Please start again.');
            $res->redirect('/settings/mfa/setup')->send();
            return;
        }

        if (!preg_match('/^[0-9]{6}$/', $code)) {
            Session::flash('error', 'Invalid code format. Please enter a 6-digit code.');
            $res->redirect('/settings/mfa/setup')->send();
            return;
        }

        // Verify TOTP code
        $totp = \OTPHP\TOTP::create($secret);
        $totp->setLabel($user['email']);

        if (!$totp->verify($code)) {
            Session::flash('error', 'Invalid verification code. Please try again.');
            $res->redirect('/settings/mfa/setup')->send();
            return;
        }

        // Generate recovery codes (10 codes)
        $recoveryCodes = [];
        for ($i = 0; $i < 10; $i++) {
            $recoveryCodes[] = strtoupper(
                substr(bin2hex(random_bytes(4)), 0, 5) . '-' . substr(bin2hex(random_bytes(4)), 0, 5)
            );
        }

        // Store hashed recovery codes
        $hashedRecoveryCodes = array_map(function (string $code): string {
            return password_hash($code, PASSWORD_BCRYPT);
        }, $recoveryCodes);

        // Enable MFA
        User::update((int) $user['id'], [
            'mfa_secret'          => $secret,
            'mfa_enabled'         => 1,
            'mfa_recovery_codes'  => json_encode($hashedRecoveryCodes),
        ]);

        Session::remove('_mfa_pending_secret');

        AuditLog::log(
            (int) $user['admin_id'],
            (int) $user['id'],
            'mfa_enabled',
            $isAdmin ? 'admin' : 'user',
            (int) $user['id']
        );

        // Show recovery codes to the user
        Session::flash('mfa_recovery_codes', $recoveryCodes);
        Session::flash('success', 'Two-factor authentication enabled! Save these recovery codes in a safe place.');
        $res->redirect('/settings')->send();
    }

    /**
     * POST /settings/mfa/disable
     * Disable MFA for the authenticated user.
     */
    public function mfaDisable(Request $req, Response $res): void
    {
        $user    = Auth::requireAuth();
        $isAdmin = ($user['role'] ?? '') === 'admin';

        // ── CSRF ──
        $csrf = (string) $req->get('_csrf');
        if (!Session::validateCsrf($csrf)) {
            Session::flash('error', 'Invalid form token. Please try again.');
            $res->redirect('/settings')->send();
            return;
        }

        User::update((int) $user['id'], [
            'mfa_secret'         => null,
            'mfa_enabled'        => 0,
            'mfa_recovery_codes' => null,
        ]);

        AuditLog::log(
            (int) $user['admin_id'],
            (int) $user['id'],
            'mfa_disabled',
            $isAdmin ? 'admin' : 'user',
            (int) $user['id']
        );

        Session::flash('success', 'Two-factor authentication has been disabled.');
        $res->redirect('/settings')->send();
    }

    /**
     * GET /mfa/challenge
     * Show MFA challenge page (after password login).
     */
    public function mfaChallengeShow(Request $req, Response $res): void
    {
        $pendingUser = Session::get('_mfa_pending_user');
        if (!$pendingUser) {
            $res->redirect('/login')->send();
            return;
        }

        $brandName = \App\Model\Admin::getBrandName();
        require __DIR__ . '/../Views/auth/mfa_challenge.php';
    }

    /**
     * POST /mfa/challenge
     * Verify TOTP code and complete login.
     */
    public function mfaChallenge(Request $req, Response $res): void
    {
        $code        = trim((string) $req->get('code'));
        $pendingUser = Session::get('_mfa_pending_user');

        // ── CSRF ──
        $csrf = (string) $req->get('_csrf');
        if (!Session::validateCsrf($csrf)) {
            Session::flash('error', 'Invalid form token. Please try again.');
            $res->redirect('/mfa/challenge')->send();
            return;
        }

        if (!$pendingUser) {
            $res->redirect('/login')->send();
            return;
        }

        if (!preg_match('/^[0-9]{6}$/', $code)) {
            Session::flash('error', 'Invalid code format. Please enter a 6-digit code.');
            $res->redirect('/mfa/challenge')->send();
            return;
        }

        $type      = $pendingUser['type'] ?? 'user';
        $accountId = (int) $pendingUser['user_id'];

        // ── Verify TOTP code against users table ──
        $user = User::find((int) $pendingUser['user_id']);
        if (!$user || empty($user['mfa_secret'])) {
            Session::flash('error', 'MFA configuration not found. Please sign in again.');
            Session::remove('_mfa_pending_user');
            $res->redirect('/login')->send();
            return;
        }

        $totp = \OTPHP\TOTP::create($user['mfa_secret']);
        $totp->setLabel($user['email']);

        // Use window of ±1 to account for clock drift
        if (!$totp->verify($code, null, 1)) {
            AuditLog::log(
                (int) $user['admin_id'],
                (int) $user['id'],
                'mfa_failed',
                'user',
                (int) $user['id']
            );
            Session::flash('error', 'Invalid authentication code. Please try again.');
            $res->redirect('/mfa/challenge')->send();
            return;
        }

        // ── MFA success — complete login ──
        User::resetFailedAttempts($accountId);
        User::updateLastLogin($accountId);
        Session::login($user, $pendingUser['remember'] ?? false);
        Session::remove('_mfa_pending_user');

        AuditLog::log(
            (int) $user['admin_id'],
            (int) $user['id'],
            'mfa_verified',
            'user',
            (int) $user['id']
        );

        $redirectPath = in_array($user['role'] ?? '', ['admin'], true) ? '/admin' : '/dashboard';
        $res->redirect($redirectPath)->send();
    }

    /**
     * GET /mfa/recovery
     * Show recovery code form.
     */
    public function mfaRecoveryShow(Request $req, Response $res): void
    {
        if (!Session::get('_mfa_pending_user')) {
            $res->redirect('/login')->send();
            return;
        }

        $brandName = \App\Model\Admin::getBrandName();
        require __DIR__ . '/../Views/auth/mfa_recovery.php';
    }

    /**
     * POST /mfa/recovery
     * Verify a recovery code and complete login.
     */
    public function mfaRecovery(Request $req, Response $res): void
    {
        $code        = strtoupper(trim((string) $req->get('code')));
        $pendingUser = Session::get('_mfa_pending_user');

        // ── CSRF ──
        $csrf = (string) $req->get('_csrf');
        if (!Session::validateCsrf($csrf)) {
            Session::flash('error', 'Invalid form token. Please try again.');
            $res->redirect('/mfa/recovery')->send();
            return;
        }

        if (!$pendingUser) {
            $res->redirect('/login')->send();
            return;
        }

        if ($code === '' || !str_contains($code, '-')) {
            Session::flash('error', 'Invalid recovery code format.');
            $res->redirect('/mfa/recovery')->send();
            return;
        }

        $type      = $pendingUser['type'] ?? 'user';
        $accountId = (int) $pendingUser['user_id'];

        // ── Verify recovery code against users table ──
        $user = User::find((int) $pendingUser['user_id']);
        if (!$user || empty($user['mfa_recovery_codes'])) {
            Session::flash('error', 'No recovery codes available. Please sign in again.');
            Session::remove('_mfa_pending_user');
            $res->redirect('/login')->send();
            return;
        }

        $recoveryCodes = json_decode($user['mfa_recovery_codes'], true);
        if (!is_array($recoveryCodes)) {
            Session::flash('error', 'Invalid recovery codes. Please sign in again.');
            Session::remove('_mfa_pending_user');
            $res->redirect('/login')->send();
            return;
        }

        // Find matching recovery code (hashed comparison)
        $matchedIndex = null;
        foreach ($recoveryCodes as $index => $hashedCode) {
            if (password_verify($code, $hashedCode)) {
                $matchedIndex = $index;
                break;
            }
        }

        if ($matchedIndex === null) {
            AuditLog::log(
                (int) $user['admin_id'],
                (int) $user['id'],
                'mfa_recovery_failed',
                'user',
                (int) $user['id']
            );
            Session::flash('error', 'Invalid recovery code.');
            $res->redirect('/mfa/recovery')->send();
            return;
        }

        // Remove the used recovery code
        unset($recoveryCodes[$matchedIndex]);
        $recoveryCodes = array_values($recoveryCodes); // re-index

        User::update((int) $user['id'], [
            'mfa_recovery_codes' => json_encode($recoveryCodes),
        ]);

        // ── MFA recovery success — complete login ──
        User::resetFailedAttempts((int) $user['id']);
        User::updateLastLogin((int) $user['id']);
        Session::login($user, $pendingUser['remember'] ?? false);
        Session::remove('_mfa_pending_user');

        AuditLog::log(
            (int) $user['admin_id'],
            (int) $user['id'],
            'mfa_recovery_used',
            'user',
            (int) $user['id']
        );

        Session::flash('warning', 'Used a recovery code. You have ' . count($recoveryCodes) . ' remaining.');
        $redirectPath = in_array($user['role'] ?? '', ['admin'], true) ? '/admin' : '/dashboard';
        $res->redirect($redirectPath)->send();
    }

    /**
     * POST /forgot-password
     */
    public function logout(Request $req, Response $res): void
    {
        // ── CSRF ──
        $csrf = (string) $req->get('_csrf');
        if (!Session::validateCsrf($csrf)) {
            Session::flash('error', 'Invalid form token. Please try again.');
            $res->redirect('/login')->send();
            return;
        }

        $userId   = Session::userId();
        $adminId = Session::adminId();

        if ($userId) {
            AuditLog::log($adminId, $userId, 'logout', 'user', $userId);
        }

        Session::destroy();
        $res->redirect('/login')->send();
    }
}
