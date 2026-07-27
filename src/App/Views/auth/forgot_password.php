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
 * Forgot password page view
 */
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - <?= htmlspecialchars($brandName) ?></title>
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
                <h1 class="text-center mb-2 h3">Forgot Password</h1>
                <p class="text-muted text-center mb-4">Enter your email and we'll send you a reset link.</p>

                <?php if ($msg = \App\Auth\Session::getFlash('error')): ?>
                    <div class="alert alert-danger" role="alert"><?= htmlspecialchars($msg) ?></div>
                <?php endif; ?>
                <?php if ($msg = \App\Auth\Session::getFlash('success')): ?>
                    <div class="alert alert-success" role="alert"><?= htmlspecialchars($msg) ?></div>
                <?php endif; ?>

                <form method="POST" action="/forgot-password">
                    <input type="hidden" name="_csrf" value="<?= \App\Auth\Session::csrfToken() ?>">

                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email"
                               required autocomplete="email">
                    </div>

                    <button type="submit" class="btn btn-primary w-100">Send Reset Link</button>
                </form>

                <div class="mt-3 text-center">
                    <a href="/login">Back to sign in</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
