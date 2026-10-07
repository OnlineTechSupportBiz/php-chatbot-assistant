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
 * Reset password page view
 */
$brandName = $brandName ?? 'Chatbot Assistant';
$token = $_GET['token'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - <?= htmlspecialchars($brandName) ?></title>
    <link href="/assets/css/theme.css" rel="stylesheet">
</head>
<body class="auth-page">
    <a href="#main-content" class="skip-link">Skip to main content</a>
    <div class="auth-wrap">
        <div class="auth-card" id="main-content">
            <div class="auth-logo">
                <span class="logo-text"><?= htmlspecialchars($brandName) ?></span>
            </div>
            <h1>Reset Password</h1>

            <?php if ($msg = \App\Auth\Session::getFlash('error')): ?>
                <div class="alert alert-error" role="alert"><?= htmlspecialchars($msg) ?></div>
            <?php endif; ?>
            <?php if ($msg = \App\Auth\Session::getFlash('success')): ?>
                <div class="alert alert-success" role="alert"><?= htmlspecialchars($msg) ?></div>
            <?php endif; ?>

            <form method="POST" action="/reset-password">
                <input type="hidden" name="_csrf" value="<?= \App\Auth\Session::csrfToken() ?>">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                <div class="field">
                    <label class="label" for="email">Email</label>
                    <input type="email" class="input" id="email" name="email"
                           required autocomplete="email">
                </div>

                <div class="field">
                    <label class="label" for="password">New Password</label>
                    <input type="password" class="input" id="password" name="password"
                           required minlength="8" autocomplete="new-password">
                </div>

                <button type="submit" class="btn btn-primary auth-submit">Reset Password</button>
            </form>
        </div>
    </div>
</body>
</html>
