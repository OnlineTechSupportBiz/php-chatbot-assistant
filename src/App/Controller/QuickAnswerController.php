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
use App\Model\QuickAnswer;

/**
 * QuickAnswer CRUD controller.
 *
 * All quick answers are scoped to a chatbot within an admin account.
 * Routes: /chatbots/{chatbotId}/quick-answers/...
 */
class QuickAnswerController
{
    /**
     * GET /chatbots/{chatbotId}/quick-answers — list quick answers for a chatbot.
     */
    public function index(Request $req, Response $res, array $params): void
    {
        $user = Auth::requireAuth();
        $adminId = (int) $user['admin_id'];
        $chatbotId = (int) ($params['chatbotId'] ?? 0);

        $chatbot = Chatbot::find($chatbotId);
        if (!$chatbot || !\App\Controller\ChatbotController::canAccessChatbot($chatbot, $user)) {
            $res->setStatus(404)->html('<h1>Chatbot not found.</h1>', 404)->send();
            return;
        }

        $quickAnswers = QuickAnswer::findByChatbot($adminId, $chatbotId);
        require __DIR__ . '/../Views/quick_answers/index.php';
    }

    /**
     * GET /chatbots/{chatbotId}/quick-answers/create — show creation form.
     */
    public function create(Request $req, Response $res, array $params): void
    {
        $user = Auth::requireAuth();
        Auth::requireRole($user, ['admin', 'user']);
        Auth::requirePermission($user, 'manage_quick_answers');
        $adminId = (int) $user['admin_id'];
        $chatbotId = (int) ($params['chatbotId'] ?? 0);

        $chatbot = Chatbot::find($chatbotId);
        if (!$chatbot || !\App\Controller\ChatbotController::canAccessChatbot($chatbot, $user)) {
            $res->setStatus(404)->html('<h1>Chatbot not found.</h1>', 404)->send();
            return;
        }

        $answer = null;
        require __DIR__ . '/../Views/quick_answers/form.php';
    }

    /**
     * POST /chatbots/{chatbotId}/quick-answers — store a new quick answer.
     */
    public function store(Request $req, Response $res, array $params): void
    {
        $user = Auth::requireAuth();
        Auth::requireRole($user, ['admin', 'user']);
        Auth::requirePermission($user, 'manage_quick_answers');
        $adminId = (int) $user['admin_id'];
        $chatbotId = (int) ($params['chatbotId'] ?? 0);

        // ── CSRF ──
        $csrf = (string) $req->get('_csrf');
        if (!Session::validateCsrf($csrf)) {
            Session::flash('error', 'Invalid form token. Please try again.');
            $res->redirect("/chatbots/{$chatbotId}/quick-answers/create")->send();
            return;
        }

        $chatbot = Chatbot::find($chatbotId);
        if (!$chatbot || !\App\Controller\ChatbotController::canAccessChatbot($chatbot, $user)) {
            $res->setStatus(404)->html('<h1>Chatbot not found.</h1>', 404)->send();
            return;
        }

        $trigger  = trim((string) $req->get('trigger'));
        $answer   = trim((string) $req->get('answer'));

        // ── Validation ──
        $errors = [];
        if ($trigger === '') {
            $errors[] = 'Trigger text is required.';
        }
        if ($answer === '') {
            $errors[] = 'Answer text is required.';
        }

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            Session::flash('old', [
                'trigger'  => $trigger,
                'answer'   => $answer,
                'priority' => $priority,
            ]);
            $res->redirect("/chatbots/{$chatbotId}/quick-answers/create")->send();
            return;
        }

        QuickAnswer::insert([
            'admin_id'  => $adminId,
            'chatbot_id' => $chatbotId,
            'trigger'    => $trigger,
            'answer'     => $answer,
            'priority'   => 0,
            'is_active'  => 1,
        ]);

        AuditLog::log(
            $adminId,
            (int) $user['id'],
            'create_quick_answer',
            'quick_answer',
            null,
            null,
            ['chatbot_id' => $chatbotId, 'trigger' => $trigger]
        );

        Session::flash('success', "Quick answer \"{$trigger}\" created!");
        $res->redirect("/chatbots/{$chatbotId}/quick-answers")->send();
    }

    /**
     * GET /chatbots/{chatbotId}/quick-answers/{id}/edit — show edit form.
     */
    public function edit(Request $req, Response $res, array $params): void
    {
        $user = Auth::requireAuth();
        Auth::requireRole($user, ['admin', 'user']);
        Auth::requirePermission($user, 'manage_quick_answers');
        $adminId = (int) $user['admin_id'];
        $chatbotId = (int) ($params['chatbotId'] ?? 0);
        $id = (int) ($params['id'] ?? 0);

        $chatbot = Chatbot::find($chatbotId);
        if (!$chatbot || !\App\Controller\ChatbotController::canAccessChatbot($chatbot, $user)) {
            $res->setStatus(404)->html('<h1>Chatbot not found.</h1>', 404)->send();
            return;
        }

        $answer = QuickAnswer::find($id);
        if (!$answer || (int) $answer['admin_id'] !== $adminId || (int) $answer['chatbot_id'] !== $chatbotId) {
            $res->setStatus(404)->html('<h1>Quick answer not found.</h1>', 404)->send();
            return;
        }

        require __DIR__ . '/../Views/quick_answers/form.php';
    }

    /**
     * POST /chatbots/{chatbotId}/quick-answers/{id} — update a quick answer.
     */
    public function update(Request $req, Response $res, array $params): void
    {
        $user = Auth::requireAuth();
        Auth::requireRole($user, ['admin', 'user']);
        Auth::requirePermission($user, 'manage_quick_answers');
        $adminId = (int) $user['admin_id'];
        $chatbotId = (int) ($params['chatbotId'] ?? 0);
        $id = (int) ($params['id'] ?? 0);

        // ── CSRF ──
        $csrf = (string) $req->get('_csrf');
        if (!Session::validateCsrf($csrf)) {
            Session::flash('error', 'Invalid form token. Please try again.');
            $res->redirect("/chatbots/{$chatbotId}/quick-answers/{$id}/edit")->send();
            return;
        }

        $answer = QuickAnswer::find($id);
        if (!$answer || (int) $answer['admin_id'] !== $adminId || (int) $answer['chatbot_id'] !== $chatbotId) {
            $res->setStatus(404)->html('<h1>Quick answer not found.</h1>', 404)->send();
            return;
        }

        $trigger  = trim((string) $req->get('trigger'));
        $answerText = trim((string) $req->get('answer'));
        $isActive = (int) ($req->get('is_active') ?: 0);

        // ── Validation ──
        $errors = [];
        if ($trigger === '') {
            $errors[] = 'Trigger text is required.';
        }
        if ($answerText === '') {
            $errors[] = 'Answer text is required.';
        }

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            $res->redirect("/chatbots/{$chatbotId}/quick-answers/{$id}/edit")->send();
            return;
        }

        QuickAnswer::update($id, [
            'trigger'   => $trigger,
            'answer'    => $answerText,
            'is_active' => $isActive,
        ]);

        AuditLog::log(
            $adminId,
            (int) $user['id'],
            'update_quick_answer',
            'quick_answer',
            $id,
            ['trigger' => $answer['trigger']],
            ['trigger' => $trigger]
        );

        Session::flash('success', "Quick answer \"{$trigger}\" updated!");
        $res->redirect("/chatbots/{$chatbotId}/quick-answers")->send();
    }

    /**
     * POST /chatbots/{chatbotId}/quick-answers/reorder — reorder by drag-and-drop.
     */
    public function reorder(Request $req, Response $res, array $params): void
    {
        $user = Auth::requireAuth();
        Auth::requireRole($user, ['admin', 'user']);
        Auth::requirePermission($user, 'manage_quick_answers');
        $adminId = (int) $user['admin_id'];
        $chatbotId = (int) ($params['chatbotId'] ?? 0);

        $csrf = (string) $req->get('_csrf');
        if (!Session::validateCsrf($csrf)) {
            $res->json(['error' => 'Invalid CSRF token.'], 419)->send();
            return;
        }

        $chatbot = Chatbot::find($chatbotId);
        if (!$chatbot || !ChatbotController::canAccessChatbot($chatbot, $user)) {
            $res->json(['error' => 'Chatbot not found.'], 404)->send();
            return;
        }

        $idsRaw = $req->get('ids');
        $ids = is_string($idsRaw) ? json_decode($idsRaw, true) : $idsRaw;
        if (!is_array($ids)) {
            $res->json(['error' => 'Invalid payload.'], 400)->send();
            return;
        }
        // Sanitize — ensure all values are integers
        $ids = array_map('intval', $ids);

        QuickAnswer::reorder($adminId, $chatbotId, $ids);

        AuditLog::log(
            $adminId,
            (int) $user['id'],
            'reorder_quick_answers',
            'quick_answer',
            null,
            null,
            ['chatbot_id' => $chatbotId, 'count' => count($ids)]
        );

        Session::flash('success', 'Quick answers reordered.');
        $res->redirect("/chatbots/{$chatbotId}/quick-answers")->send();
    }

    /**
     * POST /chatbots/{chatbotId}/quick-answers/{id}/delete — delete a quick answer.
     */
    public function destroy(Request $req, Response $res, array $params): void
    {
        $user = Auth::requireAuth();
        Auth::requireRole($user, ['admin', 'user']);
        Auth::requirePermission($user, 'manage_quick_answers');
        $adminId = (int) $user['admin_id'];
        $chatbotId = (int) ($params['chatbotId'] ?? 0);
        $id = (int) ($params['id'] ?? 0);

        // ── CSRF ──
        $csrf = (string) $req->get('_csrf');
        if (!Session::validateCsrf($csrf)) {
            Session::flash('error', 'Invalid form token. Please try again.');
            $res->redirect("/chatbots/{$chatbotId}/quick-answers")->send();
            return;
        }

        $answer = QuickAnswer::find($id);
        if (!$answer || (int) $answer['admin_id'] !== $adminId || (int) $answer['chatbot_id'] !== $chatbotId) {
            $res->setStatus(404)->html('<h1>Quick answer not found.</h1>', 404)->send();
            return;
        }

        $trigger = $answer['trigger'];
        QuickAnswer::delete($id);

        AuditLog::log(
            $adminId,
            (int) $user['id'],
            'delete_quick_answer',
            'quick_answer',
            $id,
            ['trigger' => $trigger],
            null
        );

        Session::flash('success', "Quick answer \"{$trigger}\" deleted.");
        $res->redirect("/chatbots/{$chatbotId}/quick-answers")->send();
    }
}
