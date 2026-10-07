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
 * Login page view
 */
$brandName = $brandName ?? 'Chatbot Assistant';
$activeTab = $_GET['tab'] ?? 'password';
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= htmlspecialchars($brandName) ?></title>
    <link href="/assets/css/theme.css" rel="stylesheet">
</head>
<body class="auth-page">
    <a href="#main-content" class="skip-link">Skip to main content</a>
    <div class="auth-wrap">
        <div class="auth-card" id="main-content">
            <div class="auth-logo">
                <span class="logo-text"><?= htmlspecialchars($brandName) ?></span>
            </div>
            <h1>Welcome Back</h1>
            <p class="auth-subtitle">Sign in to your account.</p>

            <?php if ($msg = \App\Auth\Session::getFlash('error')): ?>
                <div class="alert alert-error" role="alert"><?= htmlspecialchars(is_string($msg) ? $msg : implode('<br>', $msg)) ?></div>
            <?php endif; ?>
            <?php if ($success = \App\Auth\Session::getFlash('success')): ?>
                <div class="alert alert-success" role="alert"><?= htmlspecialchars(is_string($success) ? $success : implode('<br>', $success)) ?></div>
            <?php endif; ?>

            <div class="auth-tabs" role="tablist">
                <a class="auth-tab <?= $activeTab === 'password' ? 'active' : '' ?>"
                   href="?tab=password" role="tab"
                   aria-selected="<?= $activeTab === 'password' ? 'true' : 'false' ?>">
                    Sign in with Password
                </a>
                <a class="auth-tab <?= $activeTab === 'magic' ? 'active' : '' ?>"
                   href="?tab=magic" role="tab"
                   aria-selected="<?= $activeTab === 'magic' ? 'true' : 'false' ?>">
                    Sign in with Magic Link
                </a>
            </div>

            <?php if ($activeTab === 'magic'): ?>
                <p class="muted auth-note">
                    We will send a one-time sign-in link to your email. No password needed.
                </p>

                <form method="POST" action="/magic-login/send">
                    <input type="hidden" name="_csrf" value="<?= \App\Auth\Session::csrfToken() ?>">

                    <div class="field">
                        <label class="label" for="magic-email">Email</label>
                        <input type="email" class="input" id="magic-email" name="email"
                               required autocomplete="email"
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    </div>

                    <button type="submit" class="btn btn-primary auth-submit">Send Magic Link</button>
                </form>
            <?php else: ?>
                <form method="POST" action="/login">
                    <input type="hidden" name="_csrf" value="<?= \App\Auth\Session::csrfToken() ?>">

                    <div class="field">
                        <label class="label" for="email">Email</label>
                        <input type="email" class="input" id="email" name="email"
                               required autocomplete="email" autofocus
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    </div>

                    <div class="field">
                        <label class="label" for="password">Password</label>
                        <input type="password" class="input" id="password" name="password"
                               required autocomplete="current-password">
                    </div>

                    <label class="auth-remember">
                        <input type="checkbox" name="remember" value="1"> Remember me
                    </label>

                    <button type="submit" class="btn btn-primary auth-submit">Sign In</button>
                </form>

                <div class="auth-center">
                    <a href="/forgot-password">Forgot your password?</a>
                </div>
            <?php endif; ?>

            <div class="auth-center muted">
                Don't have an account? <a href="/register">Create one</a>
            </div>
        </div>
    </div>
</body>
</html>
