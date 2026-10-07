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
use App\Model\Setting;
use App\Model\Admin;
use PDO;

/**
 * AdminSettingsController — admin/org-level settings and management.
 *
 * Routes:
 *   GET  /settings → settings()
 *   POST /settings/api-keys → updateApiKeys()
 */
class AdminSettingsController
{
    /**
     * Show admin settings page (API keys, MFA, account info, audit log).
     */
    public function settings(Request $req, Response $res, array $params): void
    {
        $user     = Auth::requireAuth();
        Auth::requirePermission($user, 'access_settings');
        $adminId = (int) $user['admin_id'];
        $isAdmin = ($user['role'] ?? '') === 'admin';

        // Full admin record (for name, api keys, etc.)
        $admin = Admin::find($adminId);
        if (!$admin) {
            http_response_code(404);
            echo '<h1>Admin not found</h1>';
            exit;
        }

        // API keys + provider settings (read from the current user's own record).
        // Raw keys are never sent to the page: the view shows a masked hint as
        // the input placeholder (port of the JS port's maskKey behavior), and a
        // blank submit means "unchanged".
        $provider = Admin::getProviderSettings((int) $user['id']);
        $keys = [
            'openai_api_key_hint'      => self::maskKey($provider['openai_api_key'] ?? null),
            'llamacloud_api_key_hint'  => self::maskKey($provider['llamacloud_api_key'] ?? null),
            'embedding_api_key_hint'   => self::maskKey($provider['embedding_api_key'] ?? null),
            'llm_base_url'             => $provider['llm_base_url'] ?? null,
            'embedding_base_url'       => $provider['embedding_base_url'] ?? null,
            'embedding_model'          => $provider['embedding_model'] ?? null,
            'llm_models'               => array_map(function ($m) {
                return [
                    'name'     => $m['name'] ?? '',
                    'base_url' => $m['base_url'] ?? '',
                    'model'    => $m['model'] ?? '',
                    'hint'     => self::maskKey($m['api_key'] ?? null),
                ];
            }, $provider['llm_models'] ?? []),
        ];

        // MFA status for this user
        $db = \getDb();
        $stmt = $db->prepare('SELECT mfa_enabled, mfa_recovery_codes FROM users WHERE id = :id');
        $stmt->bindValue(':id', $user['id'], \PDO::PARAM_INT);
        $stmt->execute();
        $userRecord = $stmt->fetch();

        $mfaEnabled = !empty($userRecord['mfa_enabled']);

        // Recovery codes are stored hashed, so we never return them from DB for display.
        // New codes are shown once via flash message after enrollment.
        $recoveryCodes = [];

        // User's timezone preference
        $userTimezone = $user['timezone'] ?? 'UTC';

        $auditLogEntries = $isAdmin
            ? AuditLog::findByAdmin((int) $user['admin_id'], '', '', 1, 10)
            : AuditLog::findByUser((int) $user['id'], '', '', 1, 10);

        require __DIR__ . '/../Views/settings/index.php';
    }

    /**
     * Mask a secret for display: first 4 + last 4 characters, or bullets for
     * very short values. Null/empty returns null (the view falls back to the
     * standard placeholder).
     */
    private static function maskKey(?string $value): ?string
    {
        $value = (string) ($value ?? '');
        if ($value === '') {
            return null;
        }
        return strlen($value) > 8 ? substr($value, 0, 4) . '…' . substr($value, -4) : '••••';
    }

    /**
     * Update admin API keys and provider configuration (multi-model LLM list,
     * embeddings endpoint). Port of the JS settings PATCH handler
     * (lib/admin/settings.ts): blank key fields mean "unchanged".
     */
    public function updateApiKeys(Request $req, Response $res, array $params): void
    {
        $user = Auth::requireAuth();
        Auth::requirePermission($user, 'access_settings');

        // ── CSRF ──
        $csrf = (string) $req->get('_csrf');
        if (!Session::validateCsrf($csrf)) {
            Session::flash('error', 'Invalid form token. Please try again.');
            $res->redirect('/settings')->send();
            return;
        }

        $userId     = (int) $user['id'];
        $previous   = Admin::getProviderSettings($userId);
        $openAiKey  = (string) $req->get('openai_api_key');
        $llamaKey   = (string) $req->get('llamacloud_api_key');

        // Build keys array — blank submit = keep the stored key
        $keys = [];
        if ($openAiKey !== '') {
            $keys['openai_api_key'] = $openAiKey;
        }
        if ($llamaKey !== '') {
            $keys['llamacloud_api_key'] = $llamaKey;
        }

        Admin::setApiKeys($userId, $keys);

        // Provider URLs + embedding model (blank = clear to NULL)
        Admin::setProviderUrls($userId, [
            'llm_base_url'       => (string) $req->get('llm_base_url'),
            'embedding_base_url' => (string) $req->get('embedding_base_url'),
            'embedding_model'    => (string) $req->get('embedding_model'),
        ]);

        // Embedding key: blank = unchanged
        $embeddingKey = (string) $req->get('embedding_api_key');
        if ($embeddingKey !== '') {
            Admin::setEmbeddingApiKey($userId, $embeddingKey);
        }

        // ── Ordered LLM model list (multi-model failover) ──
        // Submitted as parallel arrays: llm_model_name[], llm_model_base_url[],
        // llm_model_key[], llm_model_id[]. Blank key = keep the previous key for
        // that entry (matched by name, like the JS merge). Empty model id or
        // completely blank rows are dropped.
        $names    = (array) $req->get('llm_model_name');
        $baseUrls = (array) $req->get('llm_model_base_url');
        $keysIn   = (array) $req->get('llm_model_key');
        $modelIds = (array) $req->get('llm_model_id');

        $previousByName = [];
        foreach ($previous['llm_models'] as $m) {
            if (!empty($m['name'])) {
                $previousByName[$m['name']] = $m;
            }
        }

        $models = [];
        $hadValidationError = false;
        $count = max(count($names), count($baseUrls), count($keysIn), count($modelIds));
        for ($i = 0; $i < $count; $i++) {
            $name    = trim((string) ($names[$i] ?? ''));
            $baseUrl = trim((string) ($baseUrls[$i] ?? ''));
            $apiKey  = trim((string) ($keysIn[$i] ?? ''));
            $modelId = trim((string) ($modelIds[$i] ?? ''));
            if ($name === '' && $baseUrl === '' && $apiKey === '' && $modelId === '') {
                continue; // fully blank row — ignore
            }
            if ($modelId === '' || $name === '') {
                $hadValidationError = true;
                continue;
            }
            // Blank key on an existing entry keeps the previous key (never echo secrets back)
            if ($apiKey === '') {
                $apiKey = (string) ($previousByName[$name]['api_key'] ?? '');
            }
            $models[] = [
                'name'     => $name,
                'base_url' => $baseUrl,
                'api_key'  => $apiKey,
                'model'    => $modelId,
            ];
        }

        if ($hadValidationError) {
            Session::flash('error', 'Some model rows were skipped: every model needs a name and a model id (e.g. gpt-4.1-mini).');
        }

        // Validate any custom base URLs up front (SSRF guard) so a bad row
        // aborts the whole save instead of failing later mid-chat.
        foreach ($models as $m) {
            try {
                if ($m['base_url'] !== '') {
                    \App\Service\OpenAIClient::validateProviderBaseUrl($m['base_url']);
                }
            } catch (\RuntimeException $e) {
                Session::flash('error', 'Model "' . $m['name'] . '": ' . $e->getMessage());
                $res->redirect('/settings')->send();
                return;
            }
        }
        try {
            $llmBase = trim((string) $req->get('llm_base_url'));
            if ($llmBase !== '') {
                \App\Service\OpenAIClient::validateProviderBaseUrl($llmBase);
            }
            $embBase = trim((string) $req->get('embedding_base_url'));
            if ($embBase !== '') {
                \App\Service\OpenAIClient::validateProviderBaseUrl($embBase);
            }
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            $res->redirect('/settings')->send();
            return;
        }

        Admin::setLlmModels($userId, $models);

        $maskOpen = $openAiKey !== '' ? substr($openAiKey, 0, 8) . '…' : 'unchanged';
        $maskLlama = $llamaKey !== '' ? substr($llamaKey, 0, 8) . '…' : 'unchanged';

        AuditLog::log(
            (int) $user['admin_id'],
            (int) $user['id'],
            'update_api_keys',
            $user['role'] ?? 'user',
            (int) $user['id'],
            null,
            [
                'openai_key' => $maskOpen,
                'llamacloud_key' => $maskLlama,
                'llm_models' => array_map(fn($m) => ['name' => $m['name'], 'model' => $m['model']], $models),
            ]
        );

        Session::flash('success', $models === []
            ? 'Provider settings updated. No LLM models configured — the legacy OpenAI key is used.'
            : 'Provider settings updated. ' . count($models) . ' LLM model' . (count($models) === 1 ? '' : 's') . ' configured.');
        $res->redirect('/settings')->send();
    }

    /**
     * Update the admin account's brand name.
     */
    public function updateBrandName(Request $req, Response $res, array $params): void
    {
        $user = Auth::requireAuth();
        $userId = (int) $user['id'];

        // ── CSRF ──
        $csrf = (string) $req->get('_csrf');
        if (!Session::validateCsrf($csrf)) {
            Session::flash('error', 'Invalid form token. Please try again.');
            $res->redirect('/settings')->send();
            return;
        }

        $brandName = trim((string) $req->get('brand_name', ''));
        if ($brandName === '') {
            Session::flash('error', 'Brand name cannot be empty.');
            $res->redirect('/settings')->send();
            return;
        }

        if (mb_strlen($brandName) > 255) {
            Session::flash('error', 'Brand name must be 255 characters or fewer.');
            $res->redirect('/settings')->send();
            return;
        }

        $db = \getDb();
        $stmt = $db->prepare('UPDATE users SET brand_name = :brand_name WHERE id = :id');
        $stmt->bindValue(':brand_name', $brandName, \PDO::PARAM_STR);
        $stmt->bindValue(':id', $userId, \PDO::PARAM_INT);
        $stmt->execute();

        // Update session so current user sees new name immediately
        $_SESSION['brand_name'] = $brandName;

        AuditLog::log(
            $userId,
            $userId,
            'update_brand_name',
            $user['role'],
            $userId,
            null,
            ['brand_name' => $brandName]
        );

        Session::flash('success', 'Brand name updated to "' . htmlspecialchars($brandName) . '".');
        $res->redirect('/settings')->send();
    }

    /**
     * POST /settings/timezone — Update user's timezone preference.
     */
    public function updateTimezone(Request $req, Response $res): void
    {
        $user = Auth::requireAuth();

        // ── CSRF ──
        $csrf = (string) $req->get('_csrf');
        if (!Session::validateCsrf($csrf)) {
            Session::flash('error', 'Invalid form token. Please try again.');
            $res->redirect('/settings')->send();
            return;
        }

        $timezone = trim((string) $req->get('timezone', 'UTC'));

        // Validate against the allowed list
        $allowed = \App\Util\DateTimeHelper::TIMEZONES;
        if (!isset($allowed[$timezone])) {
            Session::flash('error', 'Invalid timezone selected.');
            $res->redirect('/settings')->send();
            return;
        }

        $db = \getDb();
        $stmt = $db->prepare('UPDATE users SET timezone = :tz WHERE id = :id');
        $stmt->bindValue(':tz', $timezone, \PDO::PARAM_STR);
        $stmt->bindValue(':id', (int) $user['id'], \PDO::PARAM_INT);
        $stmt->execute();

        // Update session so it takes effect immediately
        $_SESSION['timezone'] = $timezone;

        Session::flash('success', 'Timezone updated to ' . htmlspecialchars($timezone) . '.');
        $res->redirect('/settings')->send();
    }

    /**
     * GET /settings/audit-log
     * Show paginated audit history for this admin.
     */
    public function auditLog(Request $req, Response $res, array $params): void
    {
        $user     = Auth::requireAuth();
        Auth::requirePermission($user, 'access_audit_log');
        $adminId = (int) $user['admin_id'];

        $page     = max(1, (int) $req->get('page', '1'));
        $perPage  = 50;
        $action   = (string) $req->get('action', '');
        $entity   = (string) $req->get('entity', '');

        // Admins see all logs under their admin_id; regular users see only their own
        $isAdmin = ($user['role'] ?? '') === 'admin';
        if ($isAdmin) {
            $result = AuditLog::findByAdmin($adminId, $action, $entity, $page, $perPage);
            $filters = self::getAuditFilters($adminId, null);
        } else {
            $result = AuditLog::findByUser((int) $user['id'], $action, $entity, $page, $perPage);
            $filters = self::getAuditFilters(null, (int) $user['id']);
        }

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

        require __DIR__ . '/../Views/settings/audit_log.php';
    }

    public static function getAuditFilters(?int $adminId, ?int $userId): array
    {
        $db = \getDb();

        if ($userId !== null) {
            $stmt = $db->prepare(
                "SELECT DISTINCT action FROM audit_logs WHERE user_id = :uid ORDER BY action"
            );
            $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $actions = array_column($stmt->fetchAll(), 'action');

            $stmt = $db->prepare(
                "SELECT DISTINCT entity_type FROM audit_logs WHERE user_id = :uid AND entity_type IS NOT NULL ORDER BY entity_type"
            );
            $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $entities = array_column($stmt->fetchAll(), 'entity_type');
        } else {
            $stmt = $db->prepare(
                "SELECT DISTINCT action FROM audit_logs WHERE admin_id = :aid ORDER BY action"
            );
            $stmt->bindValue(':aid', $adminId, PDO::PARAM_INT);
            $stmt->execute();
            $actions = array_column($stmt->fetchAll(), 'action');

            $stmt = $db->prepare(
                "SELECT DISTINCT entity_type FROM audit_logs WHERE admin_id = :aid AND entity_type IS NOT NULL ORDER BY entity_type"
            );
            $stmt->bindValue(':aid', $adminId, PDO::PARAM_INT);
            $stmt->execute();
            $entities = array_column($stmt->fetchAll(), 'entity_type');
        }

        return ['actions' => $actions, 'entities' => $entities];
    }
}
