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
 * Registration page view
 *
 * Variables:
 *   - $flash['errors']? : array of validation error strings
 *   - $flash['old']?    : array of previously submitted values
 */
\App\Auth\Session::start();
$errors = \App\Auth\Session::getFlash('errors') ?? [];
$old    = \App\Auth\Session::getFlash('old') ?? [];
$msg    = \App\Auth\Session::getFlash('error');
$brandName = $brandName ?? 'Chatbot Assistant';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?= htmlspecialchars($brandName) ?></title>
    <link href="/assets/css/theme.css" rel="stylesheet">
</head>
<body class="auth-page">
    <a href="#main-content" class="skip-link">Skip to main content</a>
    <div class="auth-wrap">
        <div class="auth-card" id="main-content">
            <div class="auth-logo">
                <span class="logo-text"><?= htmlspecialchars($brandName) ?></span>
            </div>
            <h1>Create Your Account</h1>
            <p class="auth-subtitle">Start your Chat Assistant in minutes.</p>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-error" role="alert">
                    <ul>
                        <?php foreach ((array) $errors as $e): ?>
                            <li><?= htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8') ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <?php if ($msg): ?>
                <div class="alert alert-warning" role="alert"><?= htmlspecialchars((string) $msg, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <form method="POST" action="/register">
                <input type="hidden" name="_csrf" value="<?= \App\Auth\Session::csrfToken() ?>">

                <div class="field">
                    <label class="label" for="company">Company / Organization <span class="auth-req">*</span></label>
                    <input type="text" class="input" id="company" name="company"
                           value="<?= htmlspecialchars((string) ($old['company'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                           required placeholder="Acme Inc.">
                </div>

                <div class="field">
                    <label class="label" for="name">Your Name <span class="auth-req">*</span></label>
                    <input type="text" class="input" id="name" name="name"
                           value="<?= htmlspecialchars((string) ($old['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                           required placeholder="Jane Smith">
                </div>

                <div class="field">
                    <label class="label" for="email">Email <span class="auth-req">*</span></label>
                    <input type="email" class="input" id="email" name="email"
                           value="<?= htmlspecialchars((string) ($old['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                           required autocomplete="email" placeholder="jane@acme.com">
                </div>

                <div class="field">
                    <label class="label" for="password">Password <span class="auth-req">*</span></label>
                    <input type="password" class="input" id="password" name="password"
                           required minlength="8" autocomplete="new-password"
                           placeholder="At least 8 characters">
                </div>

                <button type="submit" class="btn btn-primary auth-submit">Create Account</button>
            </form>

            <div class="auth-center muted">
                Already have an account? <a href="/login">Sign in</a>
            </div>
        </div>
    </div>
</body>
</html>
