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
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= htmlspecialchars($brandName) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/theme.css" rel="stylesheet">
    <style>
        .skip-link {
            position: absolute;
            top: -100%;
            left: 0;
            z-index: 9999;
            padding: 0.5rem 1rem;
            background: #0d9488;
            color: #fff;
            text-decoration: none;
            font-weight: 600;
            border-radius: 0 0 0.25rem 0;
            transition: top 0.1s;
        }
        .skip-link:focus {
            top: 0;
            outline: 2px solid #fff;
            outline-offset: 2px;
        }
        :focus-visible {
            outline: 2px solid #0d9488;
            outline-offset: 2px;
            border-radius: 2px;
        }
        .nav-tabs .nav-link {
            font-weight: 500;
            font-size: 0.9rem;
        }
        .nav-tabs .nav-link.active {
            border-bottom-color: #fff;
        }
    </style>
</head>
<body class="auth-page">
    <a href="#main-content" class="skip-link">Skip to main content</a>
    <div class="auth-card" id="main-content">
        <div class="card">
            <div class="card-body">
                <div class="auth-logo">
                    <span class="logo-text"><?= htmlspecialchars($brandName) ?></span>
                </div>
                <h1 class="text-center mb-2 h3">Welcome Back</h1>
                <p class="text-muted text-center mb-4">Sign in to your account.</p>

                <?php if ($msg = \App\Auth\Session::getFlash('error')): ?>
                    <div class="alert alert-danger" role="alert"><?= htmlspecialchars(is_string($msg) ? $msg : implode('<br>', $msg)) ?></div>
                <?php endif; ?>
                <?php if ($success = \App\Auth\Session::getFlash('success')): ?>
                    <div class="alert alert-success" role="alert"><?= htmlspecialchars(is_string($success) ? $success : implode('<br>', $success)) ?></div>
                <?php endif; ?>

                <!-- Tab navigation -->
                <ul class="nav nav-tabs nav-justified mb-4" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?= $activeTab === 'password' ? 'active' : '' ?>"
                                id="password-tab" data-bs-toggle="tab"
                                data-bs-target="#password-pane" type="button" role="tab"
                                aria-controls="password-pane" aria-selected="<?= $activeTab === 'password' ? 'true' : 'false' ?>">
                            Sign in with Password
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?= $activeTab === 'magic' ? 'active' : '' ?>"
                                id="magic-tab" data-bs-toggle="tab"
                                data-bs-target="#magic-pane" type="button" role="tab"
                                aria-controls="magic-pane" aria-selected="<?= $activeTab === 'magic' ? 'true' : 'false' ?>">
                            Sign in with Magic Link
                        </button>
                    </li>
                </ul>

                <div class="tab-content">
                    <!-- Password login pane -->
                    <div class="tab-pane fade <?= $activeTab === 'password' ? 'show active' : '' ?>" id="password-pane" role="tabpanel" aria-labelledby="password-tab">
                        <form method="POST" action="/login">
                            <input type="hidden" name="_csrf" value="<?= \App\Auth\Session::csrfToken() ?>">

                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email"
                                       required autocomplete="email" autofocus
                                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password"
                                       required autocomplete="current-password">
                            </div>

                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="remember" name="remember" value="1">
                                <label class="form-check-label" for="remember">Remember me</label>
                            </div>

                            <button type="submit" class="btn btn-primary w-100">Sign In</button>
                        </form>

                        <div class="mt-3 text-center">
                            <a href="/forgot-password">Forgot your password?</a>
                        </div>
                    </div>

                    <!-- Magic link pane -->
                    <div class="tab-pane fade <?= $activeTab === 'magic' ? 'show active' : '' ?>" id="magic-pane" role="tabpanel" aria-labelledby="magic-tab">
                        <p class="text-muted small mb-3">
                            We'll send a one-time sign-in link to your email. No password needed.
                        </p>

                        <form method="POST" action="/magic-login/send">
                            <input type="hidden" name="_csrf" value="<?= \App\Auth\Session::csrfToken() ?>">

                            <div class="mb-3">
                                <label for="magic-email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="magic-email" name="email"
                                       required autocomplete="email"
                                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                            </div>

                            <button type="submit" class="btn btn-primary w-100">Send Magic Link</button>
                        </form>
                    </div>
                </div>

                <div class="mt-3 text-center small">
                    Don't have an account? <a href="/register">Create one</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
