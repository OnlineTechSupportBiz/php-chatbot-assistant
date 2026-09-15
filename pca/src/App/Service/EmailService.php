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

declare(strict_types=1);

namespace App\Service;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Email service using PHPMailer.
 *
 * Reads SMTP configuration from environment / config and
 * provides convenience methods for app-specific emails.
 */
class EmailService
{
    private PHPMailer $mailer;
    private string $brandName;

    public function __construct(?string $brandName = null, ?PHPMailer $mailer = null)
    {
        $this->brandName = $brandName ?? env('MAIL_FROM_NAME', 'Chatbot Assistant');

        $this->mailer = $mailer ?? $this->createMailer();

        // Default sender
        $fromEmail = env('MAIL_FROM_ADDRESS', 'noreply@onlinetechsupport.biz');
        $this->mailer->setFrom($fromEmail, $this->brandName);
    }

    /**
     * Get the underlying PHPMailer instance (for testing/inspection).
     */
    public function getMailer(): PHPMailer
    {
        return $this->mailer;
    }

    /**
     * Create and configure a new PHPMailer instance.
     */
    private function createMailer(): PHPMailer
    {
        $mailer = new PHPMailer(true); // true = exceptions on errors

        // SMTP config
        $mailer->isSMTP();
        $mailer->Host       = env('SMTP_HOST', 'localhost');
        $mailer->Port       = (int) env('SMTP_PORT', 587);
        $mailer->SMTPAuth   = (bool) env('SMTP_AUTH', true);
        $mailer->Username   = env('SMTP_USER', '');
        $mailer->Password   = env('SMTP_PASS', '');
        $mailer->SMTPSecure = env('SMTP_ENCRYPTION', PHPMailer::ENCRYPTION_STARTTLS);
        $mailer->CharSet    = 'UTF-8';

        return $mailer;
    }

    /**
     * Send a plain-text or HTML email.
     */
    public function send(string $to, string $subject, string $body, bool $isHtml = true): bool
    {
        try {
            $this->mailer->addAddress($to);

            if ($isHtml) {
                $this->mailer->isHTML(true);
                $this->mailer->Subject = $subject;
                $this->mailer->Body    = $body;
                $this->mailer->AltBody = strip_tags($body);
            } else {
                $this->mailer->isHTML(false);
                $this->mailer->Subject = $subject;
                $this->mailer->Body    = $body;
            }

            $this->mailer->send();
            $this->mailer->clearAddresses();
            return true;
        } catch (Exception $e) {
            if (env('APP_ENV') !== 'testing') {
                error_log('EmailService send failed: ' . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * Send a password reset email.
     */
    public function sendPasswordReset(string $to, string $token): bool
    {
        $appUrl  = env('APP_URL', 'https://example.com');
        $link    = $appUrl . '/reset-password?token=' . urlencode($token);
        $subject = 'Reset Your Password';

        $body = <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family: Arial, sans-serif; background: #f4f4f4; padding: 20px;">
    <div style="max-width: 500px; margin: auto; background: #fff; border-radius: 8px; padding: 30px;">
        <h2 style="color: #333;">Password Reset Request</h2>
        <p>You recently requested to reset your password. Click the button below to set a new one:</p>
        <p style="text-align: center; margin: 30px 0;">
            <a href="{$link}"
               style="background: #0d6efd; color: #fff; padding: 12px 24px; border-radius: 6px;
                      text-decoration: none; display: inline-block;">
                Reset Password
            </a>
        </p>
        <p>If you didn't request this, you can safely ignore this email.</p>
        <p>This link expires in 60 minutes.</p>
        <hr style="border: none; border-top: 1px solid #eee;">
        <p style="color: #888; font-size: 12px;">{$this->brandName} Platform</p>
    </div>
</body>
</html>
HTML;

        return $this->send($to, $subject, $body);
    }

    /**
     * Send a magic link email for password-less login.
     */
    public function sendMagicLink(string $to, string $token): bool
    {
        $appUrl  = env('APP_URL', 'https://example.com');
        $link    = $appUrl . '/magic-login?token=' . urlencode($token);
        $subject = 'Sign in to ' . $this->brandName;

        $body = <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family: Arial, sans-serif; background: #f4f4f4; padding: 20px;">
    <div style="max-width: 500px; margin: auto; background: #fff; border-radius: 8px; padding: 30px;">
        <h2 style="color: #333;">Sign in to {$this->brandName}</h2>
        <p>Click the button below to sign in instantly:</p>
        <p style="text-align: center; margin: 30px 0;">
            <a href="{$link}"
               style="background: #0d6efd; color: #fff; padding: 12px 24px; border-radius: 6px;
                      text-decoration: none; display: inline-block;">
                Sign In
            </a>
        </p>
        <p>This link expires in 15 minutes and can only be used once.</p>
        <p>If you didn't request this, you can safely ignore this email.</p>
        <hr style="border: none; border-top: 1px solid #eee;">
        <p style="color: #888; font-size: 12px;">{$this->brandName} Platform</p>
    </div>
</body>
</html>
HTML;

        return $this->send($to, $subject, $body);
    }

    /**
     * Send an email verification email.
     */
    public function sendVerification(string $to, string $token): bool
    {
        $appUrl  = env('APP_URL', 'https://example.com');
        $link    = $appUrl . '/verify-email?token=' . urlencode($token);
        $subject = 'Verify Your Email Address';

        $body = <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family: Arial, sans-serif; background: #f4f4f4; padding: 20px;">
    <div style="max-width: 500px; margin: auto; background: #fff; border-radius: 8px; padding: 30px;">
        <h2 style="color: #333;">Welcome!</h2>
        <p>Please verify your email address by clicking the button below:</p>
        <p style="text-align: center; margin: 30px 0;">
            <a href="{$link}"
               style="background: #0d6efd; color: #fff; padding: 12px 24px; border-radius: 6px;
                      text-decoration: none; display: inline-block;">
                Verify Email
            </a>
        </p>
        <p>If you didn't create an account, you can safely ignore this email.</p>
        <hr style="border: none; border-top: 1px solid #eee;">
        <p style="color: #888; font-size: 12px;">{$this->brandName} Platform</p>
    </div>
</body>
</html>
HTML;

        return $this->send($to, $subject, $body);
    }
}
