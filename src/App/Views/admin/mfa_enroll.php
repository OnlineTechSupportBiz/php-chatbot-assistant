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
 * MFA enrollment page for admin (embedded in admin layout).
 *
 * Available variables:
 *   $mfaSecret — string (TOTP secret, empty if not yet generated)
 */
$secret    = $mfaSecret ?? '';
$brandName = $user['brand_name'] ?? 'Chatbot Assistant';
$escapedBrand = urlencode($brandName);
$qrCodeUrl = 'otpauth://totp/' . $escapedBrand . ':' . urlencode($_SESSION['user_email'] ?? '')
    . '?secret=' . urlencode($mfaSecret ?? '')
    . '&issuer=' . $escapedBrand;
?><div class="container mt-4">
    <h1 class="mb-1">Setup Two-Factor Authentication</h1>
    <p class="text-muted mb-4" style="font-size:0.85rem;">Enhance your account security with TOTP-based two-factor authentication.</p>

    <?php if ($msg = \App\Auth\Session::getFlash('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show"><?= htmlspecialchars(is_string($msg) ? $msg : implode('<br>', $msg)) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($msg = \App\Auth\Session::getFlash('success')): ?>
        <div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars(is_string($msg) ? $msg : implode('<br>', $msg)) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-8">
            <?php if ($secret): ?>
            <div class="card mb-4">
                <div class="card-body">
                    <div class="alert alert-info">
                        <strong>Step 1:</strong> Scan this QR code or enter the secret key manually in your authenticator app (Google Authenticator, Authy, etc.).
                    </div>

                    <div class="text-center mb-3">
                        <div class="d-inline-block" id="qrcode"></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Secret Key</label>
                        <div class="input-group">
                            <input type="text" class="form-control" value="<?= htmlspecialchars($secret) ?>" readonly id="secretKey">
                            <button class="btn btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText(document.getElementById('secretKey').value)">Copy</button>
                        </div>
                    </div>

                    <hr>

                    <div class="alert alert-info">
                        <strong>Step 2:</strong> Enter the 6-digit code from your authenticator app to verify setup.
                    </div>

                    <form method="POST" action="/admin/mfa/verify">
                        <input type="hidden" name="_csrf" value="<?= \App\Auth\Session::csrfToken() ?>">

                        <div class="mb-3">
                            <label for="code" class="form-label">Verification Code</label>
                            <input type="text" class="form-control" id="code" name="code"
                                   inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
                                   placeholder="000000" required autocomplete="off">
                        </div>

                        <button type="submit" class="btn btn-primary">Verify &amp; Enable</button>
                        <a href="/admin/settings" class="btn btn-outline-secondary ms-2">Cancel</a>
                    </form>
                </div>
            </div>
            <?php else: ?>
            <div class="card mb-4">
                <div class="card-body">
                    <p class="text-muted">Click the button below to generate a new TOTP secret and set up two-factor authentication.</p>

                    <form method="POST" action="/admin/mfa/enroll">
                        <input type="hidden" name="_csrf" value="<?= \App\Auth\Session::csrfToken() ?>">
                        <button type="submit" name="action" value="enroll" class="btn btn-primary" id="generateBtn">Generate Secret</button>
                        <a href="/admin/settings" class="btn btn-outline-secondary ms-2">Cancel</a>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<?php if ($secret): ?>
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
<?php endif; ?>
