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
use App\Http\Request;
use App\Http\Response;
use App\Model\Chatbot;
use App\Model\Conversation;
use App\Model\Message;

/**
 * UserController — user-facing pages for regular users.
 *
 * Routes:
 *   GET /dashboard → dashboard()
 */
class UserController
{
    /**
     * GET /dashboard — User dashboard with stats.
     */
    public function dashboard(Request $req, Response $res): void
    {
        $user = Auth::requireAuth();

        $userId = (int) $user['id'];

        // Message chart date range: 7 / 30 / 90 days, default 30.
        $range = (int) $req->get('range', 30);
        if (!in_array($range, [7, 30, 90], true)) {
            $range = 30;
        }

        // Compute dashboard stats (used as server-side fallback)
        $totalMessages     = Message::countByUser($userId);
        $totalConversations = Conversation::countByUser($userId);
        $uniqueVisitors    = Conversation::uniqueVisitorsByUser($userId);
        $totalTokens       = Message::totalTokensByUser($userId);
        $chatbots          = Chatbot::findByUser($userId);
        $messageChart      = Message::chartByUser(
            $userId,
            date('Y-m-d', strtotime('-' . ($range - 1) . ' days')),
            date('Y-m-d')
        );
        // Fill zero-count days so the chart shows a continuous timeline.
        $byDate = [];
        foreach ($messageChart as $row) {
            $byDate[$row['date']] = (int) $row['count'];
        }
        $messageChart = [];
        for ($i = $range - 1; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-{$i} days"));
            $messageChart[] = ['date' => $d, 'count' => $byDate[$d] ?? 0];
        }
        $chartRange       = $range;
        $sourceBreakdown   = Message::sourceBreakdownByUser($userId);
        $chatbotStats      = Conversation::perChatbotByUser($userId);

        $brandName = $user['brand_name'] ?? 'Chatbot Assistant';

        $view = __DIR__ . '/../Views/dashboard/index.php';
        require $view;
    }
}
