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
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?= htmlspecialchars($brandName) ?></title>
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
                <h1 class="text-center mb-2 h3">Create Your Account</h1>
                <p class="text-muted text-center mb-4">Start your Chat Assistant in minutes.</p>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger" role="alert">
                        <ul class="mb-0">
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

                    <div class="mb-3">
                        <label for="company" class="form-label">Company / Organization <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="company" name="company"
                               value="<?= htmlspecialchars((string) ($old['company'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                               required placeholder="Acme Inc.">
                    </div>

                    <div class="mb-3">
                        <label for="name" class="form-label">Your Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name"
                               value="<?= htmlspecialchars((string) ($old['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                               required placeholder="Jane Smith">
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="email" name="email"
                               value="<?= htmlspecialchars((string) ($old['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                               required autocomplete="email" placeholder="jane@acme.com">
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="password" name="password"
                               required minlength="8" autocomplete="new-password"
                               placeholder="At least 8 characters">
                    </div>

                    <button type="submit" class="btn btn-primary w-100">Create Account</button>
                </form>

                <div class="mt-3 text-center">
                    Already have an account? <a href="/login">Sign in</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
