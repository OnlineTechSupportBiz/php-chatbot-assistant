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
 * Start dev server (from the project root): php -S localhost:8000 -t public_html
 */

// ── Normalize script-name requests to root ────────────────────────────────
$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if ($requestUri === '/index.php') {
    $parsed = parse_url($_SERVER['REQUEST_URI'] ?? '/');
    $qs = isset($parsed['query']) ? '?' . $parsed['query'] : '';
    header('Location: /' . $qs, true, 301);
    exit;
}

// ── Application root (everything except the web root lives here) ──────────
$appDir = __DIR__ . '/../pca';

// ── Autoload ──────────────────────────────────────────────────────────────
$autoload = $appDir . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    http_response_code(500);
    echo 'Run: composer install';
    exit(1);
}
require $autoload;

// ── Bootstrap ────────────────────────────────────────────────────────────
$config = require $appDir . '/config/config.php';
require_once $appDir . '/config/database.php';

use App\Auth\Session;
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

// ── Database-outage handling ─────────────────────────────────────────────
// Registered FIRST: setRlsContext() below opens the PDO connection whenever a
// session is authenticated, and the DB-backed checks on every route throw the
// same way. Port of the JS port's dbGuarded/error-boundary behavior: 503 JSON
// for /api paths, styled outage page for HTML. Anything else rethrows to the
// normal 500 path.
set_exception_handler(function (\Throwable $e) {
    if (isDatabaseUnavailable($e)) {
        renderDatabaseUnavailable();
    }
    throw $e;
});

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
$qa       = new QuickAnswerController();
$userUi   = new UserController();

// ── Security Headers ─────────────────────────────────────────────────────
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
if (env('APP_ENV') === 'production') {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

// Production refuses to run without an MFA encryption key: a TOTP seed is a
// bearer credential, and storing it in the clear defeats the second factor.
// Port of the JS assertSecretEncryptionConfiguration.
if (env('APP_ENV') === 'production' && trim((string) env('APP_ENCRYPTION_KEY', '')) === '') {
    http_response_code(500);
    exit(
        'Refusing to start: APP_ENCRYPTION_KEY is unset, so multi-factor authentication ' .
        "seeds would be stored unencrypted. Generate one with `openssl rand -hex 32`.\n"
    );
}

// ── Routes ───────────────────────────────────────────────────────────────

// Root — redirect to login
$router->get('/', function (Request $req, Response $res) {
    $res->redirect('/login')->send();
    exit;
});

// ── Auth routes ──────────────────────────────────────────────────────────
$router->get('/login', function (Request $req, Response $res) use ($appDir) {
    $brandName = \App\Model\Admin::getBrandName();
    require $appDir . '/src/App/Views/auth/login.php';
});
$router->post('/login', [$auth, 'login']);

// Registration gate. ALLOW_REGISTRATION is deployment-level (JS parity): when
// set, it overrides the platform_settings row; when unset, the DB toggle rules.
function registrationEnabled(): bool
{
    $env = trim((string) env('ALLOW_REGISTRATION', ''));
    if ($env !== '') {
        return in_array(strtolower($env), ['1', 'true', 'yes'], true);
    }
    return \App\Model\Setting::get('registration_enabled', '1') === '1';
}

$router->get('/register', function (Request $req, Response $res) use ($appDir) {
    if (!registrationEnabled()) {
        Session::flash('error', 'New user registration is currently disabled.');
        $res->redirect('/login')->send();
        return;
    }
    $brandName = \App\Model\Admin::getBrandName();
    require $appDir . '/src/App/Views/auth/register.php';
});
$router->post('/register', [$auth, 'register']);

$router->get('/verify-email', [$auth, 'verifyEmail']);
$router->post('/resend-verification', [$auth, 'resendVerification']);
$router->post('/logout', [$auth, 'logout']);

$router->get('/forgot-password', function (Request $req, Response $res) use ($appDir) {
    $brandName = \App\Model\Admin::getBrandName();
    require $appDir . '/src/App/Views/auth/forgot_password.php';
});
$router->post('/forgot-password', [$auth, 'forgotPassword']);

$router->get('/reset-password', function (Request $req, Response $res) use ($appDir) {
    $brandName = \App\Model\Admin::getBrandName();
    require $appDir . '/src/App/Views/auth/reset_password.php';
});
$router->post('/reset-password', [$auth, 'resetPassword']);

// ── MFA routes ──
// The MFA flow keeps its full backend + enrollment page; only the login-time
// challenge/recovery screens were removed (flat admin design, JS parity).
$router->get('/settings/mfa/setup', [$auth, 'mfaSetup']);
$router->post('/settings/mfa/enroll', [$auth, 'mfaEnroll']);
$router->post('/settings/mfa/verify', [$auth, 'mfaVerify']);
$router->post('/settings/mfa/disable', [$auth, 'mfaDisable']);

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
$router->get('/audit', [$settings, 'auditLog']);
$router->post('/settings/api-keys', [$settings, 'updateApiKeys']);
$router->post('/settings/brand-name', [$settings, 'updateBrandName']);
$router->post('/settings/timezone', [$settings, 'updateTimezone']);

// ── Documents (scoped to chatbot) ──────────────────────────────────────
$router->get('/chatbots/{id}/documents', [$docs, 'index']);
$router->post('/chatbots/{id}/documents/store', [$docs, 'store']);
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

// ── Leads ────────────────────────────────────────────────────────────────
$router->get('/leads', [$chatbot, 'leadsAll']);
$router->get('/chatbots/{id}/leads', [$chatbot, 'leads']);

// ── Conversations ────────────────────────────────────────────────────────
$router->get('/conversations', [$chatbot, 'conversationsAll']);
$router->get('/chatbots/{id}/conversations', [$chatbot, 'conversations']);
$router->get('/chatbots/{id}/conversations/{cid}', [$chatbot, 'conversationDetail']);

// ── Dispatch ─────────────────────────────────────────────────────────────
$router->dispatch($request, $response);
