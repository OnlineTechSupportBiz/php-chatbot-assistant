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
 * MFA challenge page view
 * Shown after password login when user has MFA enabled.
 */
$userName = $pendingUser['name'] ?? 'User';
$userEmail = $pendingUser['email'] ?? '';
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Two-Factor Authentication - <?= htmlspecialchars($brandName) ?></title>
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
                <h1 class="text-center mb-2 h3">Two-Factor Authentication</h1>

                <p class="text-muted text-center mb-4">
                    Hi <strong><?= htmlspecialchars($userName) ?></strong>, enter the 6-digit code
                    from your authenticator app to complete sign-in.
                </p>

                <?php if ($msg = \App\Auth\Session::getFlash('error')): ?>
                    <div class="alert alert-danger" role="alert"><?= htmlspecialchars(is_string($msg) ? $msg : implode('<br>', $msg)) ?></div>
                <?php endif; ?>
                <?php if ($msg = \App\Auth\Session::getFlash('success')): ?>
                    <div class="alert alert-success" role="alert"><?= htmlspecialchars(is_string($msg) ? $msg : implode('<br>', $msg)) ?></div>
                <?php endif; ?>

                <form method="POST" action="/mfa/challenge">
                    <input type="hidden" name="_csrf" value="<?= \App\Auth\Session::csrfToken() ?>">

                    <div class="mb-3">
                        <label for="code" class="form-label">Authentication Code</label>
                        <input type="text" class="form-control text-center fs-3 tracking-wider" id="code" name="code"
                               inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
                               placeholder="000000" required autocomplete="off" autofocus
                               style="letter-spacing: 0.3em;">
                    </div>

                    <button type="submit" class="btn btn-primary w-100">Verify</button>
                </form>

                <hr class="my-4">

                <p class="text-center mb-0">
                    <a href="/mfa/recovery" class="text-decoration-none">Use a recovery code instead</a>
                </p>
                <p class="text-center mt-2">
                    <a href="/login" class="text-decoration-none small">Back to sign in</a>
                </p>
            </div>
        </div>
    </div>

    <script>
    (function() {
        const codeInput = document.getElementById('code');
        const form = codeInput?.closest('form');
        if (codeInput && form) {
            codeInput.addEventListener('input', function() {
                // Auto-submit when 6 digits are entered (typed or pasted)
                if (this.value.replace(/\D/g, '').length === 6) {
                    this.value = this.value.replace(/\D/g, '');
                    form.submit();
                }
            });
        }
    })();
    </script>
</body>
</html>
