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

/**
 * Front controller — all requests route through here.
 *
 * Start dev server: php -S localhost:8000 -t public_html
 */

// ── Normalize script-name requests to root ────────────────────────────────
$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if ($requestUri === '/index.php') {
    $parsed = parse_url($_SERVER['REQUEST_URI'] ?? '/');
    $qs = isset($parsed['query']) ? '?' . $parsed['query'] : '';
    header('Location: /' . $qs, true, 301);
    exit;
}

// ── Autoload ──────────────────────────────────────────────────────────────
$autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload)) {
    http_response_code(500);
    echo 'Run: composer install';
    exit(1);
}
require $autoload;

// ── Bootstrap ────────────────────────────────────────────────────────────
$config = require __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

use App\Auth\Session;
use App\Controller\AdminController;
use App\Controller\ApiController;
use App\Controller\AuthController;
use App\Controller\ChatbotController;
use App\Controller\ChatController;
use App\Controller\DocumentController;
use App\Controller\QuickAnswerController;
use App\Controller\AdminSettingsController;
use App\Controller\UserController;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;

// Start session early for all requests
$sessionLifetime = $config['session']['lifetime'] ?? 120;
Session::start([
    'lifetime'    => $sessionLifetime,
    'cookie_name' => $config['session']['cookie_name'] ?? 'RAG_SESSION',
]);

// Set PostgreSQL RLS context if authenticated (defense-in-depth tenant isolation)
setRlsContext();

$request  = new Request();
$response = new Response();
$router   = new Router();
$auth     = new AuthController();
$chatbot  = new ChatbotController();
$chat     = new ChatController();
$docs     = new DocumentController();
$api      = new ApiController();
$settings = new AdminSettingsController();
$admin    = new AdminController();
$qa       = new QuickAnswerController();
$userUi   = new UserController();

// ── Security Headers ─────────────────────────────────────────────────────
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
if (env('APP_ENV') === 'production') {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

// ── Routes ───────────────────────────────────────────────────────────────

// Root — redirect to login
$router->get('/', function (Request $req, Response $res) {
    $res->redirect('/login')->send();
    exit;
});

// ── Auth routes ──────────────────────────────────────────────────────────
$router->get('/login', function (Request $req, Response $res) {
    $brandName = \App\Model\Admin::getBrandName();
    require __DIR__ . '/../src/App/Views/auth/login.php';
});
$router->post('/login', [$auth, 'login']);

$router->get('/register', function (Request $req, Response $res) {
    // Check if registration is enabled (platform setting)
    if (\App\Model\Setting::get('registration_enabled', '1') !== '1') {
        Session::flash('error', 'New user registration is currently disabled.');
        $res->redirect('/login')->send();
        return;
    }
    $brandName = \App\Model\Admin::getBrandName();
    require __DIR__ . '/../src/App/Views/auth/register.php';
});
$router->post('/register', [$auth, 'register']);

$router->get('/verify-email', [$auth, 'verifyEmail']);
$router->post('/resend-verification', [$auth, 'resendVerification']);
$router->post('/logout', [$auth, 'logout']);

$router->get('/forgot-password', function (Request $req, Response $res) {
    $brandName = \App\Model\Admin::getBrandName();
    require __DIR__ . '/../src/App/Views/auth/forgot_password.php';
});
$router->post('/forgot-password', [$auth, 'forgotPassword']);

$router->get('/reset-password', function (Request $req, Response $res) {
    $brandName = \App\Model\Admin::getBrandName();
    require __DIR__ . '/../src/App/Views/auth/reset_password.php';
});
$router->post('/reset-password', [$auth, 'resetPassword']);

// ── MFA routes ──
$router->get('/settings/mfa/setup', [$auth, 'mfaSetup']);
$router->post('/settings/mfa/enroll', [$auth, 'mfaEnroll']);
$router->post('/settings/mfa/verify', [$auth, 'mfaVerify']);
$router->post('/settings/mfa/disable', [$auth, 'mfaDisable']);
$router->get('/mfa/challenge', [$auth, 'mfaChallengeShow']);
$router->post('/mfa/challenge', [$auth, 'mfaChallenge']);
$router->get('/mfa/recovery', [$auth, 'mfaRecoveryShow']);
$router->post('/mfa/recovery', [$auth, 'mfaRecovery']);

// ── Magic Link routes ──────────────────────────────────────────────────
$router->post('/magic-login/send', [$auth, 'sendMagicLink']);
$router->get('/magic-login', [$auth, 'verifyMagicLink']);

// ── Dashboard ────────────────────────────────────────────────────────────
$router->get('/dashboard', [$userUi, 'dashboard']);

// ── Chatbots ─────────────────────────────────────────────────────────────
$router->get('/chatbots', [$chatbot, 'index']);
$router->get('/chatbots/create', [$chatbot, 'create']);
$router->get('/chatbots/{id}', [$chatbot, 'show']);
$router->post('/chatbots', [$chatbot, 'store']);
$router->get('/chatbots/{id}/edit', [$chatbot, 'edit']);
$router->post('/chatbots/{id}', [$chatbot, 'update']);   // POST (no PUT natively in browser forms)
$router->post('/chatbots/{id}/delete', [$chatbot, 'destroy']);
$router->post('/chatbots/{id}/clone', [$chatbot, 'clone']);

// ── Admin Settings ────────────────────────────────────────────────────
$router->get('/settings', [$settings, 'settings']);
$router->get('/settings/audit-log', [$settings, 'auditLog']);
$router->get('/settings/php-info', [$settings, 'phpInfo']);
$router->post('/settings/api-keys', [$settings, 'updateApiKeys']);
$router->post('/settings/brand-name', [$settings, 'updateBrandName']);
$router->post('/settings/timezone', [$settings, 'updateTimezone']);

// ── Documents (scoped to chatbot) ──────────────────────────────────────
$router->get('/chatbots/{id}/documents', [$docs, 'index']);
$router->post('/chatbots/{id}/documents/store', [$docs, 'store']);
$router->get('/chatbots/{id}/documents/{did}', [$docs, 'status']);
$router->post('/chatbots/{id}/documents/{did}/train', [$docs, 'train']);
$router->post('/chatbots/{id}/documents/{did}/delete', [$docs, 'delete']);

// ── API ──────────────────────────────────────────────────────────────────
$router->get('/api/stats/summary', [$api, 'statsSummary']);

// ── Chat (test preview) ─────────────────────────────────────────────────
$router->post('/chatbots/{id}/chat', [$chat, 'testChat']);

// ── Public Chat API (widget endpoint, no auth required) ─────────────────
$router->post('/api/public/chat', [$chat, 'publicChat']);
$router->get('/api/public/quick-answers', [$chat, 'publicQuickAnswers']);
$router->post('/api/public/rate', [$chat, 'publicRate']);

// ── Quick Answers (scoped to chatbot) ──────────────────────────────────
$router->get('/chatbots/{chatbotId}/quick-answers', [$qa, 'index']);
$router->get('/chatbots/{chatbotId}/quick-answers/create', [$qa, 'create']);
$router->post('/chatbots/{chatbotId}/quick-answers', [$qa, 'store']);
$router->post('/chatbots/{chatbotId}/quick-answers/reorder', [$qa, 'reorder']);
$router->get('/chatbots/{chatbotId}/quick-answers/{id}/edit', [$qa, 'edit']);
$router->post('/chatbots/{chatbotId}/quick-answers/{id}', [$qa, 'update']);
$router->post('/chatbots/{chatbotId}/quick-answers/{id}/delete', [$qa, 'destroy']);

// ── Leads (scoped to chatbot) ───────────────────────────────────────────
$router->get('/chatbots/{id}/leads', [$chatbot, 'leads']);

// ── Conversations (scoped to chatbot) ────────────────────────────────────
$router->get('/chatbots/{id}/conversations', [$chatbot, 'conversations']);
$router->get('/chatbots/{id}/conversations/{cid}', [$chatbot, 'conversationDetail']);

// ── Super Admin ────────────────────────────────────────────────────────
$router->get('/admin', [$admin, 'dashboard']);
$router->get('/admin/admins', [$admin, 'admins']);
$router->get('/admin/users/{id:\d+}/permissions', [$admin, 'userPermissions']);
$router->post('/admin/users/{id:\d+}/permissions', [$admin, 'updateUserPermissions']);
$router->post('/admin/registration', [$admin, 'updateRegistration']);
$router->get('/admin/api/stats', [$admin, 'apiStats']);

// ── Super Admin Settings ───────────────────────────────────────────────
$router->get('/admin/settings', [$admin, 'settings']);
$router->get('/admin/settings/audit-log', [$admin, 'auditLog']);
$router->get('/admin/settings/php-info', [$admin, 'phpInfo']);
$router->post('/admin/settings/brand-name', [$admin, 'updateBrandName']);
$router->post('/admin/settings/timezone', [$admin, 'updateTimezone']);

// ── Super Admin Audit Log ──────────────────────────────────────────────
$router->get('/admin/audit-log', [$admin, 'auditLog']);

// ── Super Admin Account & MFA ───────────────────────────────────────────
$router->get('/admin/account', function(\App\Http\Request $req, \App\Http\Response $res) {
    $res->redirect('/admin/settings')->send();
});
$router->get('/admin/mfa/setup', [$admin, 'mfaSetup']);
$router->post('/admin/mfa/enroll', [$admin, 'mfaEnroll']);
$router->post('/admin/mfa/verify', [$admin, 'mfaVerify']);
$router->post('/admin/mfa/disable', [$admin, 'mfaDisable']);

// ── Dispatch ─────────────────────────────────────────────────────────────
$router->dispatch($request, $response);
