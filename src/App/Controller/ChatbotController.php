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
use App\Model\IndustryTemplate;
use App\Model\Lead;
use App\Model\Message;

/**
 * Chatbot CRUD controller.
 *
 * All actions require an authenticated user.
 */
class ChatbotController
{
    /**
     * GET /chatbots — list all chatbots for the current user.
     */
    public function index(Request $req, Response $res): void
    {
        $user = Auth::requireAuth();

        if ($user['role'] === 'admin') {
            $chatbots = Chatbot::findByAdmin((int) $user['admin_id']);
        } else {
            $chatbots = Chatbot::findByUser((int) $user['id']);
        }

        // Decode JSON fields for display
        foreach ($chatbots as &$bot) {
            if (isset($bot['model_config']) && is_string($bot['model_config'])) {
                $decoded = json_decode($bot['model_config'], true);
                $bot['model_config'] = is_array($decoded) ? $decoded : [];
            }
            if (isset($bot['styling']) && is_string($bot['styling'])) {
                $decoded = json_decode($bot['styling'], true);
                $bot['styling'] = is_array($decoded) ? $decoded : [];
            }
        }
        unset($bot);

        require __DIR__ . '/../Views/chatbots/index.php';
    }

    /**
     * GET /chatbots/{id} — show chatbot detail page.
     */
    public function show(Request $req, Response $res, array $params): void
    {
        $user = Auth::requireAuth();
        $id = (int) ($params['id'] ?? 0);

        $chatbot = Chatbot::find($id);
        if (!$chatbot || !self::canAccessChatbot($chatbot, $user)) {
            $res->setStatus(404)->html('<h1>Chatbot not found.</h1>', 404)->send();
            return;
        }

        // Decode JSON
        if (isset($chatbot['model_config']) && is_string($chatbot['model_config'])) {
            $decoded = json_decode($chatbot['model_config'], true);
            $chatbot['model_config'] = is_array($decoded) ? $decoded : [];
        }
        if (isset($chatbot['styling']) && is_string($chatbot['styling'])) {
            $decoded = json_decode($chatbot['styling'], true);
            $chatbot['styling'] = is_array($decoded) ? $decoded : [];
        }

        $documents      = \App\Model\Document::findByChatbot((int) $user['admin_id'], $id);
        $indexedCount   = \App\Model\Document::countIndexedByChatbot((int) $user['admin_id'], $id);
        $totalFileSize  = \App\Model\Document::totalFileSizeByChatbot((int) $user['admin_id'], $id);
        $vectorStorage  = \App\Model\Document::vectorStorageByChatbot((int) $user['admin_id'], $id);

        require __DIR__ . '/../Views/chatbots/show.php';
    }

    /**
     * GET /chatbots/create — show creation form.
     */
    public function create(Request $req, Response $res): void
    {
        $user = Auth::requireAuth();
        Auth::requireRole($user, ['admin', 'user']);
        Auth::requirePermission($user, 'manage_chatbots');

        $industryPresetsGrouped = IndustryTemplate::allGrouped();
        require __DIR__ . '/../Views/chatbots/form.php';
    }

    /**
     * POST /chatbots — store a new chatbot.
     */
    public function store(Request $req, Response $res): void
    {
        $user = Auth::requireAuth();
        Auth::requireRole($user, ['admin', 'user']);
        Auth::requirePermission($user, 'manage_chatbots');
        $adminId = (int) $user['admin_id'];

        // ── CSRF ──
        $csrf = (string) $req->get('_csrf');
        if (!Session::validateCsrf($csrf)) {
            Session::flash('error', 'Invalid form token. Please try again.');
            $res->redirect('/chatbots/create')->send();
            return;
        }

        $name      = trim((string) $req->get('name'));
        $industry  = trim((string) $req->get('industry'));
        $prompt    = trim((string) $req->get('system_prompt'));

        // ── Validation ──
        $errors = [];
        if ($name === '') {
            $errors[] = 'Chatbot name is required.';
        }

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            Session::flash('old', [
                'name'          => $name,
                'industry'      => $industry,
                'system_prompt' => $prompt,
            ]);
            $res->redirect('/chatbots/create')->send();
            return;
        }

        $modelConfig = [
            'temperature' => (float) ($req->get('temperature') ?: 0.7),
            'max_tokens'  => (int) ($req->get('max_tokens') ?: 1024),
            'model'       => (string) ($req->get('model') ?: 'gpt-4o-mini'),
        ];

        $styling = [
            'primary_color'      => (string) ($req->get('primary_color') ?: '#0d6efd'),
            'header_text_color'  => (string) ($req->get('header_text_color') ?: '#ffffff'),
            'header_gradient_to' => (string) ($req->get('header_gradient_to') ?: ''),
            'accent_color'       => (string) ($req->get('accent_color') ?: ''),
            'header_icon'        => (string) ($req->get('header_icon') ?: ''),
            'header_subtitle'    => (string) ($req->get('header_subtitle') ?: ''),
            'placeholder_icon'   => (string) ($req->get('placeholder_icon') ?: ''),
            'placeholder_title'  => (string) ($req->get('placeholder_title') ?: ''),
            'placeholder_text'   => (string) ($req->get('placeholder_text') ?: ''),
            'position'           => (string) ($req->get('position') ?: 'bottom-right'),
            'bot_name'           => (string) ($req->get('bot_name') ?: 'Assistant'),
            'widget_theme'       => (string) ($req->get('widget_theme') ?: ''),
            'panel_theme'        => (string) ($req->get('panel_theme') ?: 'light'),
        ];

        $chatbotId = Chatbot::createChatbot([
            'admin_id'             => $adminId,
            'created_by'           => (int) $user['id'],
            'name'                 => $name,
            'industry'             => $industry ?: null,
            'system_prompt'        => $prompt ?: null,
            'model_config'         => $modelConfig,
            'styling'              => $styling,
            'status'               => 'active',
            'lead_capture_enabled'        => (int) ($req->get('lead_capture_enabled') ? 1 : 0),
            'retrieval_strategy'        => (string) ($req->get('retrieval_strategy') ?: 'traditional_rag'),
            'allowed_domains'             => self::processAllowedDomains($req->get('allowed_domains')),
            'max_message_length'          => self::parseNullableInt($req->get('max_message_length')),
            'max_messages_per_conversation' => self::parseNullableInt($req->get('max_messages_per_conversation')),
        ]);

        AuditLog::log(
            $adminId,
            (int) $user['id'],
            'create_chatbot',
            'chatbot',
            $chatbotId,
            null,
            ['name' => $name, 'industry' => $industry]
        );

        Session::flash('success', "Chatbot \"{$name}\" created successfully! Widget token is ready.");
        $res->redirect('/chatbots')->send();
    }

    /**
     * GET /chatbots/{id}/edit — show edit form.
     */
    public function edit(Request $req, Response $res, array $params): void
    {
        $user = Auth::requireAuth();
        Auth::requireRole($user, ['admin', 'user']);
        Auth::requirePermission($user, 'manage_chatbots');
        $id = (int) ($params['id'] ?? 0);

        $chatbot = Chatbot::find($id);
        if (!$chatbot || !self::canAccessChatbot($chatbot, $user)) {
            $res->setStatus(404)->html('<h1>Chatbot not found.</h1>', 404)->send();
            exit;
        }

        // Decode JSON
        if (isset($chatbot['model_config']) && is_string($chatbot['model_config'])) {
            $decoded = json_decode($chatbot['model_config'], true);
            $chatbot['model_config'] = is_array($decoded) ? $decoded : [];
        }
        if (isset($chatbot['styling']) && is_string($chatbot['styling'])) {
            $decoded = json_decode($chatbot['styling'], true);
            $chatbot['styling'] = is_array($decoded) ? $decoded : [];
        }

        $industryPresetsGrouped = IndustryTemplate::allGrouped();
        require __DIR__ . '/../Views/chatbots/form.php';
    }

    /**
     * POST /chatbots/{id} — update a chatbot.
     */
    public function update(Request $req, Response $res, array $params): void
    {
        $user = Auth::requireAuth();
        Auth::requireRole($user, ['admin', 'user']);
        Auth::requirePermission($user, 'manage_chatbots');
        $id = (int) ($params['id'] ?? 0);

        // ── CSRF ──
        $csrf = (string) $req->get('_csrf');
        if (!Session::validateCsrf($csrf)) {
            Session::flash('error', 'Invalid form token. Please try again.');
            $res->redirect("/chatbots/{$id}/edit")->send();
            return;
        }

        $chatbot = Chatbot::find($id);
        if (!$chatbot || !self::canAccessChatbot($chatbot, $user)) {
            $res->setStatus(404)->html('<h1>Chatbot not found.</h1>', 404)->send();
            exit;
        }

        $name      = trim((string) $req->get('name'));
        $industry  = trim((string) $req->get('industry'));
        $prompt    = trim((string) $req->get('system_prompt'));
        $status    = (string) $req->get('status');

        // ── Validation ──
        $errors = [];
        if ($name === '') {
            $errors[] = 'Chatbot name is required.';
        }
        if (!in_array($status, ['active', 'paused'], true)) {
            $status = 'active';
        }

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            $res->redirect("/chatbots/{$id}/edit")->send();
            return;
        }

        $modelConfig = [
            'temperature' => (float) ($req->get('temperature') ?: 0.7),
            'max_tokens'  => (int) ($req->get('max_tokens') ?: 1024),
            'model'       => (string) ($req->get('model') ?: 'gpt-4o-mini'),
        ];

        $styling = [
            'primary_color'      => (string) ($req->get('primary_color') ?: '#0d6efd'),
            'header_text_color'  => (string) ($req->get('header_text_color') ?: '#ffffff'),
            'header_gradient_to' => (string) ($req->get('header_gradient_to') ?: ''),
            'accent_color'       => (string) ($req->get('accent_color') ?: ''),
            'header_icon'        => (string) ($req->get('header_icon') ?: ''),
            'header_subtitle'    => (string) ($req->get('header_subtitle') ?: ''),
            'placeholder_icon'   => (string) ($req->get('placeholder_icon') ?: ''),
            'placeholder_title'  => (string) ($req->get('placeholder_title') ?: ''),
            'placeholder_text'   => (string) ($req->get('placeholder_text') ?: ''),
            'position'           => (string) ($req->get('position') ?: 'bottom-right'),
            'bot_name'           => (string) ($req->get('bot_name') ?: 'Assistant'),
            'widget_theme'       => (string) ($req->get('widget_theme') ?: ''),
            'panel_theme'        => (string) ($req->get('panel_theme') ?: 'light'),
        ];

        // Cost & abuse protection: 0/empty = unlimited (NULL)
        $dailyTokenBudgetStr    = preg_replace('/[^0-9]/', '', trim((string) $req->get('daily_token_budget')));
        $rateLimitPerSessionStr = preg_replace('/[^0-9]/', '', trim((string) $req->get('rate_limit_per_session')));
        // Daily budget minimum = 100 (one GPT-4-mini response uses ~50-100 tokens);
        // anything below is treated as unlimited to prevent accidental breakage.
        $dailyTokenBudget       = $dailyTokenBudgetStr !== '' && (int) $dailyTokenBudgetStr >= 100 ? (int) $dailyTokenBudgetStr : null;
        // Rate limit of 1 message/min is valid (prevent spam), so allow >= 1.
        $rateLimitPerSession    = $rateLimitPerSessionStr !== '' && (int) $rateLimitPerSessionStr >= 1 ? (int) $rateLimitPerSessionStr : null;

        $oldValue = [
            'name'                      => $chatbot['name'],
            'industry'                  => $chatbot['industry'],
            'system_prompt'             => $chatbot['system_prompt'],
            'status'                    => $chatbot['status'],
            'daily_token_budget'        => $chatbot['daily_token_budget'],
            'rate_limit_per_session'    => $chatbot['rate_limit_per_session'],
            'max_message_length'        => $chatbot['max_message_length'],
            'max_messages_per_conversation' => $chatbot['max_messages_per_conversation'],
        ];

        Chatbot::updateChatbot($id, [
            'name'                   => $name,
            'industry'               => $industry ?: null,
            'system_prompt'          => $prompt ?: null,
            'model_config'           => $modelConfig,
            'styling'                => $styling,
            'status'                 => $status,
            'lead_capture_enabled'   => (int) ($req->get('lead_capture_enabled') ? 1 : 0),
            'retrieval_strategy'   => (string) ($req->get('retrieval_strategy') ?: 'traditional_rag'),
            'daily_token_budget'     => $dailyTokenBudget,
            'rate_limit_per_session' => $rateLimitPerSession,
            'allowed_domains'        => self::processAllowedDomains($req->get('allowed_domains')),
            'max_message_length'          => self::parseNullableInt($req->get('max_message_length')),
            'max_messages_per_conversation' => self::parseNullableInt($req->get('max_messages_per_conversation')),
        ]);

        AuditLog::log(
            (int) $user['admin_id'],
            (int) $user['id'],
            'update_chatbot',
            'chatbot',
            $id,
            $oldValue,
            ['name' => $name, 'status' => $status]
        );

        Session::flash('success', "Chatbot \"{$name}\" updated successfully!");
        $res->redirect('/chatbots')->send();
    }

    /**
     * POST /chatbots/{id}/delete — delete a chatbot.
     */
    public function destroy(Request $req, Response $res, array $params): void
    {
        $user = Auth::requireAuth();
        Auth::requireRole($user, ['admin', 'user']);
        Auth::requirePermission($user, 'manage_chatbots');
        $id = (int) ($params['id'] ?? 0);

        // ── CSRF ──
        $csrf = (string) $req->get('_csrf');
        if (!Session::validateCsrf($csrf)) {
            Session::flash('error', 'Invalid form token. Please try again.');
            $res->redirect('/chatbots')->send();
            return;
        }

        $chatbot = Chatbot::find($id);
        if (!$chatbot || !self::canAccessChatbot($chatbot, $user)) {
            $res->setStatus(404)->html('<h1>Chatbot not found.</h1>', 404)->send();
            return;
        }

        $name = $chatbot['name'];
        Chatbot::delete($id);

        AuditLog::log(
            (int) $user['admin_id'],
            (int) $user['id'],
            'delete_chatbot',
            'chatbot',
            $id,
            ['name' => $name],
            null
        );

        Session::flash('success', "Chatbot \"{$name}\" deleted.");
        $res->redirect('/chatbots')->send();
    }

    /**
     * POST /chatbots/{id}/clone — clone a chatbot with a new name.
     */
    public function clone(Request $req, Response $res, array $params): void
    {
        $user = Auth::requireAuth();
        Auth::requireRole($user, ['admin', 'user']);
        Auth::requirePermission($user, 'manage_chatbots');
        $id = (int) ($params['id'] ?? 0);

        // ── CSRF ──
        $csrf = (string) $req->get('_csrf');
        if (!Session::validateCsrf($csrf)) {
            Session::flash('error', 'Invalid form token. Please try again.');
            $res->redirect('/chatbots')->send();
            return;
        }

        $chatbot = Chatbot::find($id);
        if (!$chatbot || !self::canAccessChatbot($chatbot, $user)) {
            $res->setStatus(404)->html('<h1>Chatbot not found.</h1>', 404)->send();
            return;
        }

        $newName = trim((string) $req->get('new_name'));
        if ($newName === '') {
            Session::flash('error', 'A name for the cloned chatbot is required.');
            $res->redirect('/chatbots')->send();
            return;
        }

        $sourceName = $chatbot['name'];
        $newId = Chatbot::cloneChatbot($id, $newName);

        AuditLog::log(
            (int) $user['admin_id'],
            (int) $user['id'],
            'clone_chatbot',
            'chatbot',
            $newId,
            ['source_id' => $id, 'source_name' => $sourceName],
            ['name' => $newName]
        );

        Session::flash('success', "Chatbot &quot;" . htmlspecialchars($sourceName) . "&quot; cloned as &quot;" . htmlspecialchars($newName) . "&quot;!");
        $res->redirect('/chatbots')->send();
    }

    /**
     * GET /chatbots/{id}/leads — display captured leads for a chatbot.
     */
    public function leads(Request $req, Response $res, array $params): void
    {
        $user = Auth::requireAuth();
        Auth::requirePermission($user, 'manage_leads');
        $id = (int) ($params['id'] ?? 0);

        $chatbot = Chatbot::find($id);
        if (!$chatbot || !self::canAccessChatbot($chatbot, $user)) {
            $res->setStatus(404)->html('<h1>Chatbot not found.</h1>', 404)->send();
            return;
        }

        $leads = Lead::findByChatbot((int) $user['admin_id'], $id);

        require __DIR__ . '/../Views/chatbots/leads.php';
    }

    /**
     * GET /chatbots/{id}/conversations — list all conversation sessions for a chatbot.
     */
    public function conversations(Request $req, Response $res, array $params): void
    {
        $user = Auth::requireAuth();
        Auth::requirePermission($user, 'view_conversations');
        $id = (int) ($params['id'] ?? 0);

        $chatbot = Chatbot::find($id);
        if (!$chatbot || !self::canAccessChatbot($chatbot, $user)) {
            $res->setStatus(404)->html('<h1>Chatbot not found.</h1>', 404)->send();
            return;
        }

        $conversations = Conversation::getRecentWithMessages($id, 200);

        $adminId = (int) $user['admin_id'];
        require __DIR__ . '/../Views/chatbots/conversations.php';
    }

    /**
     * GET /chatbots/{id}/conversations/{cid} — show full messages for a conversation.
     */
    public function conversationDetail(Request $req, Response $res, array $params): void
    {
        $user = Auth::requireAuth();
        Auth::requirePermission($user, 'view_conversations');
        $chatbotId = (int) ($params['id'] ?? 0);
        $convId = (int) ($params['cid'] ?? 0);

        $chatbot = Chatbot::find($chatbotId);
        if (!$chatbot || !self::canAccessChatbot($chatbot, $user)) {
            $res->setStatus(404)->html('<h1>Chatbot not found.</h1>', 404)->send();
            return;
        }

        $conversation = Conversation::find($convId);
        if (!$conversation || (int) $conversation['chatbot_id'] !== $chatbotId) {
            $res->setStatus(404)->html('<h1>Conversation not found.</h1>', 404)->send();
            return;
        }

        $messages = Message::findByConversation($convId);
        $lead = Lead::findByConversation($convId);

        require __DIR__ . '/../Views/chatbots/conversation_detail.php';
    }

    /**
     * Check whether the current user is allowed to access a given chatbot.
     *
     * Admin users can access any chatbot under their tenant.
     * Regular users can only access chatbots they created.
     */
    public static function canAccessChatbot(array $chatbot, array $user): bool
    {
        if ($user['role'] === 'admin') {
            return (int) $chatbot['admin_id'] === (int) $user['admin_id'];
        }
        return (int) ($chatbot['created_by'] ?? 0) === (int) $user['id'];
    }

    /**
     * Process raw allowed_domains input into a normalized newline-separated string.
     *
     * Handles full URLs (https://example.com/path → example.com), bare domains,
     * host:port with or without protocol, and strips default ports (80 for http,
     * 443 for https). Wildcard port syntax (example.com:*) is preserved.
     * Returns null if no valid domains remain.
     */
    private static function processAllowedDomains(?string $input): ?string
    {
        if ($input === null || trim($input) === '') {
            return null;
        }

        $lines = explode("\n", $input);
        $domains = [];

        foreach ($lines as $line) {
            $domain = trim($line);
            if ($domain === '') {
                continue;
            }

            // Full URL — use parse_url for proper host/port extraction
            if (preg_match('#^https?://#i', $domain)) {
                $parts = parse_url($domain);
                $host = $parts['host'] ?? '';
                $port = $parts['port'] ?? null;
                $scheme = strtolower($parts['scheme'] ?? '');

                if ($host === '') {
                    continue;
                }

                // Strip default ports (80 for http, 443 for https)
                if ($port !== null) {
                    if (($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443)) {
                        $port = null;
                    }
                }

                $domain = strtolower($host) . ($port !== null ? ':' . $port : '');
            } else {
                // Bare domain or host:port — strip any trailing path
                $domain = preg_replace('~[/?#].*$~', '', $domain);
                $domain = strtolower($domain);
            }

            $domain = trim($domain);
            if ($domain !== '' && preg_match('/^[a-z0-9.\-:*]+$/i', $domain)) {
                $domains[] = $domain;
            }
        }

        return !empty($domains) ? implode("\n", $domains) : null;
    }

    /**
     * Parse a form input as a nullable non-negative integer.
     *
     * Returns null for empty strings and non-numeric input,
     * the parsed integer otherwise (clamped to 0+).
     */
    private static function parseNullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '' || $value === false) {
            return null;
        }
        $cleaned = preg_replace('/[^0-9]/', '', trim((string) $value));
        return $cleaned !== '' ? (int) $cleaned : null;
    }
}
