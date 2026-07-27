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
use App\Model\Document;
use App\Model\Message;

/**
 * Simple JSON API controller for dashboard stats.
 */
class ApiController
{
    /**
     * GET /api/stats/summary — return aggregate counts for the dashboard.
     */
    public function statsSummary(Request $req, Response $res): void
    {
        $user = Auth::requireAuth();
        $adminId = (int) $user['admin_id'];

        $from = $req->get('from');
        $to   = $req->get('to');

        // Admin sees tenant-wide data; regular users see only their own.
        if ($user['role'] === 'admin') {
            $messageChart = $from && $to
                ? Message::chartByAdmin($adminId, $from, $to)
                : Message::last7DaysByAdmin($adminId);

            $data = [
                'chatbots'          => Chatbot::countByAdmin($adminId),
                'documents'         => Document::countByAdmin($adminId),
                'conversations'     => Conversation::countByAdmin($adminId),
                'messages'          => Message::countByAdmin($adminId),
                'unique_visitors'   => Conversation::uniqueVisitorsByAdmin($adminId),
                'tokens_used'       => Message::totalTokensByAdmin($adminId),
                'avg_response_time' => Message::avgResponseTimeByAdmin($adminId),
                'message_chart'     => $messageChart,
                'sources'           => Message::sourceBreakdownByAdmin($adminId),
                'bot_chart'         => Conversation::perChatbotByAdmin($adminId),
            ];
        } else {
            $userId = (int) $user['id'];

            $messageChart = $from && $to
                ? Message::chartByUser($userId, $from, $to)
                : Message::last7DaysByUser($userId);

            $data = [
                'chatbots'          => Chatbot::countByUser($userId),
                'documents'         => Document::countByUser($userId),
                'conversations'     => Conversation::countByUser($userId),
                'messages'          => Message::countByUser($userId),
                'unique_visitors'   => Conversation::uniqueVisitorsByUser($userId),
                'tokens_used'       => Message::totalTokensByUser($userId),
                'avg_response_time' => Message::avgResponseTimeByUser($userId),
                'message_chart'     => $messageChart,
                'sources'           => Message::sourceBreakdownByUser($userId),
                'bot_chart'         => Conversation::perChatbotByUser($userId),
            ];
        }

        $res->json($data)->send();
    }
}
