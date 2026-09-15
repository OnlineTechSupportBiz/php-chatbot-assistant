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
 * MFA enrollment page view
 */
$escapedBrand = urlencode($brandName ?? 'Chatbot Assistant');
$secret    = $mfaSecret ?? '';
$qrCodeUrl = 'otpauth://totp/' . $escapedBrand . ':' . urlencode($_SESSION['user_email'] ?? '')
           . '?secret=' . $secret
           . '&issuer=' . $escapedBrand;
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Two-Factor Auth - <?= htmlspecialchars($brandName ?? 'Chatbot Assistant') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/theme.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    <style>
        #qrcode { display: inline-flex; padding: 12px; background: #fff; border-radius: var(--radius-sm); }
        #qrcode img { display: none; }
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
    <div class="auth-card" id="main-content" style="max-width: 520px;">
        <div class="card">
            <div class="card-body">
                <div class="auth-logo">
                    <span class="logo-text"><?= htmlspecialchars($brandName ?? 'Chatbot Assistant') ?></span>
                </div>
                <h1 class="text-center mb-4 h3">Set Up Two-Factor Authentication</h1>

                <?php if ($msg = \App\Auth\Session::getFlash('error')): ?>
                    <div class="alert alert-danger" role="alert"><?= htmlspecialchars(is_string($msg) ? $msg : implode('<br>', $msg)) ?></div>
                <?php endif; ?>
                <?php if ($msg = \App\Auth\Session::getFlash('success')): ?>
                    <div class="alert alert-success" role="alert"><?= htmlspecialchars(is_string($msg) ? $msg : implode('<br>', $msg)) ?></div>
                <?php endif; ?>

                <?php if ($secret): ?>
                    <div class="alert alert-info" role="alert">
                        <strong>Step 1:</strong> Scan this QR code or enter the secret key manually in your authenticator app (Google Authenticator, Authy, etc.).
                    </div>

                    <!-- QR code generated client-side -->
                    <div class="text-center mb-3">
                        <div class="d-inline-block" id="qrcode"></div>
                    </div>
                    <script>
                    new QRCode(document.getElementById('qrcode'), {
                        text: <?= json_encode($qrCodeUrl) ?>,
                        width: 200,
                        height: 200,
                        colorDark: '#000000',
                        colorLight: '#ffffff',
                        correctLevel: QRCode.CorrectLevel.M
                    });
                    </script>

                    <div class="mb-3">
                        <label class="form-label" for="secretKey">Secret Key</label>
                        <div class="input-group">
                            <input type="text" class="form-control" value="<?= htmlspecialchars($secret) ?>" readonly id="secretKey">
                            <button class="btn btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText(document.getElementById('secretKey').value)">Copy</button>
                        </div>
                    </div>

                    <hr>

                    <div class="alert alert-info" role="alert">
                        <strong>Step 2:</strong> Enter the 6-digit code from your authenticator app to verify setup.
                    </div>

                    <form method="POST" action="/settings/mfa/verify">
                        <input type="hidden" name="_csrf" value="<?= \App\Auth\Session::csrfToken() ?>">

                        <div class="mb-3">
                            <label for="code" class="form-label">Verification Code</label>
                            <input type="text" class="form-control" id="code" name="code"
                                   inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
                                   placeholder="000000" required autocomplete="off">
                        </div>

                        <button type="submit" class="btn btn-primary w-100">Verify &amp; Enable</button>
                    </form>
                <?php else: ?>
                    <p class="text-muted">Click the button below to generate a new TOTP secret and set up two-factor authentication.</p>

                    <form method="POST" action="/settings/mfa/enroll">
                        <input type="hidden" name="_csrf" value="<?= \App\Auth\Session::csrfToken() ?>">
                        <div class="text-center">
                            <button type="submit" name="action" value="enroll" class="btn btn-primary" id="generateBtn">Generate Secret</button>
                        </div>
                    </form>
                <?php endif; ?>

                <div class="mt-3 text-center">
                    <a href="/settings">Back to Settings</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
