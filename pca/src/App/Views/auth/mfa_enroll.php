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
 * MFA enrollment page — set up two-factor authentication.
 *
 * Restored in the new design-system vocabulary (the JS port ships the MFA
 * backend routes but no enrollment UI; PHP keeps the full flow, so the page is
 * rebuilt here rather than dropped).
 *
 * Variables provided by AuthController::mfaSetup / mfaEnroll:
 *   $mfaSecret -- pending TOTP secret ('' when enrollment has not started)
 *   $brandName -- brand name for the page title/logo
 *
 * Flow: GET /settings/mfa/setup shows this page; press "Begin setup" to POST
 * /settings/mfa/enroll (mints the secret, returns here with the QR); scan,
 * then POST /settings/mfa/verify to enable.
 */
$escapedBrand = urlencode($brandName ?? 'Chatbot Assistant');
$secret    = $mfaSecret ?? '';
$qrCodeUrl = 'otpauth://totp/' . $escapedBrand . ':' . urlencode($_SESSION['user_email'] ?? '')
           . '?secret=' . $secret
           . '&issuer=' . $escapedBrand;
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Two-Factor Auth - <?= htmlspecialchars($brandName ?? 'Chatbot Assistant') ?></title>
    <link href="/assets/css/theme.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
</head>
<body>
    <a href="#main-content" class="skip-link">Skip to main content</a>

    <div class="auth-wrap">
        <div class="auth-card" id="main-content">
            <div class="auth-logo"><span class="logo-text"><?= htmlspecialchars($brandName ?? 'Chatbot Assistant') ?></span></div>
            <h1>Two-Factor Authentication</h1>
            <p class="auth-subtitle">Add an extra layer of security to your account.</p>

            <?php if ($msg = \App\Auth\Session::getFlash('error')): ?>
                <div class="alert alert-error" role="alert"><?= htmlspecialchars(is_string($msg) ? $msg : implode('<br>', $msg)) ?></div>
            <?php endif; ?>
            <?php if ($msg = \App\Auth\Session::getFlash('success')): ?>
                <div class="alert alert-success" role="alert"><?= htmlspecialchars(is_string($msg) ? $msg : implode('<br>', $msg)) ?></div>
            <?php endif; ?>

            <?php if ($secret === ''): ?>
                <p class="auth-note">Two-factor sign-in uses a time-based one-time password (TOTP) from an authenticator app such as Google Authenticator or Authy.</p>
                <form method="POST" action="/settings/mfa/enroll">
                    <input type="hidden" name="_csrf" value="<?= \App\Auth\Session::csrfToken() ?>">
                    <button type="submit" class="btn btn-primary auth-submit">Begin setup</button>
                </form>
            <?php else: ?>
                <div class="alert alert-info" role="alert">
                    <strong>Step 1:</strong> Scan this QR code or enter the secret key manually in your authenticator app (Google Authenticator, Authy, etc.).
                </div>

                <p style="text-align:center;">
                    <span id="qrcode" style="display:inline-block;padding:12px;background:#fff;border-radius:var(--radius);"></span>
                </p>
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

                <div class="field">
                    <label class="label" for="secretKey">Secret key</label>
                    <input type="text" class="input mono" value="<?= htmlspecialchars($secret) ?>" readonly id="secretKey">
                    <button type="button" class="btn" onclick="navigator.clipboard.writeText(document.getElementById('secretKey').value)">Copy</button>
                </div>

                <div class="alert alert-info" role="alert">
                    <strong>Step 2:</strong> Enter the 6-digit code from your authenticator app to verify setup.
                </div>

                <form method="POST" action="/settings/mfa/verify">
                    <input type="hidden" name="_csrf" value="<?= \App\Auth\Session::csrfToken() ?>">

                    <div class="field">
                        <label for="code" class="label">Verification code</label>
                        <input type="text" class="input mono" id="code" name="code"
                               inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
                               placeholder="000000" required autocomplete="off">
                    </div>

                    <button type="submit" class="btn btn-primary auth-submit">Verify and enable</button>
                </form>
            <?php endif; ?>

            <p class="auth-center"><a href="/settings">Back to settings</a></p>
        </div>
    </div>
</body>
</html>