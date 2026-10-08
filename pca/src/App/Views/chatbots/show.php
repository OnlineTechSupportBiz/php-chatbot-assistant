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

/**
 * Chatbot detail page — one page, five tabs (port of the JS
 * app/(admin)/chatbots/[id]/page.tsx): Settings / Documents / Quick answers /
 * Leads / Conversations. This view renders the Settings tab (the full edit
 * form); the other tabs are their own routes, which render the same tab bar
 * with their tab active. There is no Overview tab, no separate Edit button,
 * and no test chat — the JS design has none of those.
 *
 * Available variables (set by ChatbotController::show):
 *   $chatbot — chatbot array (with decoded model_config, styling)
 *   $user    — authenticated user
 */
\App\Auth\Session::start();
// Consume the flashes BEFORE form.php's prelude runs (its own getFlash
// calls would otherwise return null and clobber these variables).
$savedSuccess = \App\Auth\Session::getFlash('success');
$savedErrors  = \App\Auth\Session::getFlash('errors');

$chatbotId = (int) $chatbot['id'];

// The Settings tab is the old edit page: form.php holds the controller
// prelude (flashes, old input, styling) and the form body, minus its own
// page head and layout. $isEdit is derived from $chatbot inside it.
$renderAsPartial = true;
ob_start();
require __DIR__ . '/form.php';
$settingsTab = ob_get_clean();
ob_start(); ?><div class="page-head">
    <div>
        <h1><?= htmlspecialchars($chatbot['name']) ?></h1>
        <p class="subtitle">
            Widget token: <span class="mono"><?= htmlspecialchars($chatbot['widget_token'] ?? '') ?></span>
        </p>
    </div>
</div>

<?php if (!empty($savedSuccess)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($savedSuccess) ?></div>
<?php endif; ?>
<?php if (is_array($savedErrors)): ?>
    <div class="alert alert-error">
        <ul style="margin:0;padding-left:1.25rem;"><?php foreach ($savedErrors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<div class="tab-bar">
    <a class="tab tab-active" href="/chatbots/<?= $chatbotId ?>" aria-current="page">Settings</a>
    <a class="tab" href="/chatbots/<?= $chatbotId ?>/documents">Documents</a>
    <a class="tab" href="/chatbots/<?= $chatbotId ?>/quick-answers">Quick answers</a>
    <a class="tab" href="/chatbots/<?= $chatbotId ?>/leads">Leads</a>
    <a class="tab" href="/chatbots/<?= $chatbotId ?>/conversations">Conversations</a>
</div>

<?php
// Merge the floating-preview script block (captured by form.php into
// $pageScripts) with the page body, then render everything inside the admin
// layout — the same way every other tab view does.
$pageContent = ob_get_clean() . $settingsTab;
$pageScripts = $pageScripts ?? '';
$pageTitle = htmlspecialchars($chatbot['name']) . ' — ' . ($user['brand_name'] ?? 'Chatbot Assistant');
require __DIR__ . '/../dashboard/layout.php';