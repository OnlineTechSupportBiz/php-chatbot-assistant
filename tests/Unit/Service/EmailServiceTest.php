<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Service\EmailService;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for EmailService — PHPMailer-based email sending.
 *
 * Covers: construction (env → PHPMailer config mapping), send wrapper
 * logic, and email template building (password reset, magic link,
 * verification).
 *
 * Tests that create a real PHPMailer verify the SMTP config mapping
 * without actually connecting to SMTP. Send tests use a mock to avoid
 * network dependencies, with an optional integration test for real SMTP.
 */
class EmailServiceTest extends TestCase
{
    /** @var array<string, string|null> Saved env to restore in tearDown */
    private array $savedEnv = [];

    protected function setUp(): void
    {
        // Save current env state so individual tests can modify freely
        $keys = ['SMTP_HOST', 'SMTP_PORT', 'SMTP_AUTH', 'SMTP_USER',
                 'SMTP_PASS', 'SMTP_ENCRYPTION', 'MAIL_FROM_ADDRESS',
                 'MAIL_FROM_NAME', 'APP_URL'];
        $this->savedEnv = [];
        foreach ($keys as $key) {
            $this->savedEnv[$key] = $_ENV[$key] ?? null;
        }

        $_ENV['APP_URL'] = 'https://example.com';
    }

    protected function tearDown(): void
    {
        // Restore env to pre-test state
        foreach ($this->savedEnv as $key => $value) {
            if ($value === null) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $value;
            }
        }
    }

    // ── Construction / config mapping (real PHPMailer, no network) ────

    public function test_constructor_reads_env_into_phpmailer_config(): void
    {
        $_ENV['SMTP_HOST'] = 'smtp.test.com';
        $_ENV['SMTP_PORT'] = '587';
        $_ENV['SMTP_AUTH'] = 'true';
        $_ENV['SMTP_USER'] = 'user@test.com';
        $_ENV['SMTP_PASS'] = 'secret';
        $_ENV['SMTP_ENCRYPTION'] = 'tls';
        $_ENV['MAIL_FROM_ADDRESS'] = 'noreply@test.com';

        $service = new EmailService('Test Brand');
        $mailer = $service->getMailer();

        $this->assertSame('smtp.test.com', $mailer->Host);
        $this->assertSame(587, $mailer->Port);
        $this->assertTrue($mailer->SMTPAuth);
        $this->assertSame('user@test.com', $mailer->Username);
        $this->assertSame('secret', $mailer->Password);
        $this->assertSame(PHPMailer::ENCRYPTION_STARTTLS, $mailer->SMTPSecure);
        $this->assertSame('UTF-8', $mailer->CharSet);
    }

    public function test_constructor_sets_from_address(): void
    {
        $_ENV['MAIL_FROM_ADDRESS'] = 'noreply@test.com';

        $service = new EmailService('Test Brand');
        $mailer = $service->getMailer();

        $this->assertSame('noreply@test.com', $mailer->From);
        $this->assertSame('Test Brand', $mailer->FromName);
    }

    public function test_constructor_uses_default_brand_name_when_null(): void
    {
        $_ENV['MAIL_FROM_NAME'] = 'Default App';
        $service = new EmailService();
        $mailer = $service->getMailer();

        $this->assertSame('Default App', $mailer->FromName);
    }

    public function test_constructor_uses_defaults_when_env_vars_missing(): void
    {
        // Clear both $_ENV and getenv() to test fallback defaults
        foreach (['SMTP_HOST', 'SMTP_PORT', 'SMTP_AUTH', 'SMTP_USER',
                  'SMTP_PASS', 'SMTP_ENCRYPTION', 'MAIL_FROM_ADDRESS'] as $key) {
            unset($_ENV[$key]);
            putenv($key);
        }

        $service = new EmailService();
        $mailer = $service->getMailer();

        $this->assertSame('localhost', $mailer->Host);
        $this->assertSame(587, $mailer->Port);
        $this->assertTrue($mailer->SMTPAuth);
    }

    // ── Send logic (mock PHPMailer, no network) ──────────────────────

    public function test_sendPasswordReset_returns_false_on_mailer_failure(): void
    {
        $mailer = $this->createStub(PHPMailer::class);
        $mailer->method('setFrom')->willReturn(true);
        $mailer->method('addAddress')->willReturn(true);
        $mailer->method('isHTML')->willReturn(true);
        $mailer->method('clearAddresses')->willReturn(true);
        $mailer->method('send')->willThrowException(
            new PHPMailerException('Mock: SMTP unavailable')
        );

        $service = new EmailService('Test Brand', $mailer);
        $result = $service->sendPasswordReset('user@test.com', 'abc123token');
        $this->assertFalse($result);
    }

    public function test_sendMagicLink_returns_false_on_mailer_failure(): void
    {
        $mailer = $this->createStub(PHPMailer::class);
        $mailer->method('setFrom')->willReturn(true);
        $mailer->method('addAddress')->willReturn(true);
        $mailer->method('isHTML')->willReturn(true);
        $mailer->method('clearAddresses')->willReturn(true);
        $mailer->method('send')->willThrowException(
            new PHPMailerException('Mock: SMTP unavailable')
        );

        $service = new EmailService('Test Brand', $mailer);
        $result = $service->sendMagicLink('user@test.com', 'magic-token-xyz');
        $this->assertFalse($result);
    }

    public function test_sendVerification_returns_false_on_mailer_failure(): void
    {
        $mailer = $this->createStub(PHPMailer::class);
        $mailer->method('setFrom')->willReturn(true);
        $mailer->method('addAddress')->willReturn(true);
        $mailer->method('isHTML')->willReturn(true);
        $mailer->method('clearAddresses')->willReturn(true);
        $mailer->method('send')->willThrowException(
            new PHPMailerException('Mock: SMTP unavailable')
        );

        $service = new EmailService('Test Brand', $mailer);
        $result = $service->sendVerification('newuser@test.com', 'verify-token-abc');
        $this->assertFalse($result);
    }

    public function test_send_returns_true_when_mailer_succeeds(): void
    {
        $mailer = $this->createStub(PHPMailer::class);
        $mailer->method('setFrom')->willReturn(true);
        $mailer->method('addAddress')->willReturn(true);
        $mailer->method('isHTML')->willReturn(true);
        $mailer->method('clearAddresses')->willReturn(true);
        $mailer->method('send')->willReturn(true);

        $service = new EmailService(null, $mailer);
        $result = $service->send('user@test.com', 'Subject', 'Body');
        $this->assertTrue($result);
    }

    public function test_send_with_invalid_email_returns_false(): void
    {
        $mailer = $this->createStub(PHPMailer::class);
        $mailer->method('setFrom')->willReturn(true);
        $mailer->method('addAddress')->willThrowException(
            new PHPMailerException('Invalid address: (to): not-an-email')
        );

        $service = new EmailService(null, $mailer);
        $result = $service->send('not-an-email', 'Subject', 'Body');
        $this->assertFalse($result);
    }

    public function test_send_text_body(): void
    {
        $mailer = $this->createStub(PHPMailer::class);
        $mailer->method('setFrom')->willReturn(true);
        $mailer->method('addAddress')->willReturn(true);
        $mailer->method('isHTML')->willReturn(true);
        $mailer->method('clearAddresses')->willReturn(true);
        $mailer->method('send')->willThrowException(
            new PHPMailerException('Mock: SMTP unavailable')
        );

        $service = new EmailService(null, $mailer);
        $result = $service->send('user@test.com', 'Subject', 'Plain text body', false);
        $this->assertFalse($result);
    }

    // ── Real SMTP integration test (opt-in) ───────────────────────────

    /**
     * Send a real test email using the configured SMTP settings.
     *
     * This test is skipped by default. Enable it by setting the
     * SMTP_REAL_TEST env var to a recipient email address:
     *
     *   SMTP_REAL_TEST=admin@example.com php vendor/bin/phpunit ...
     */
    public function test_real_smtp_send(): void
    {
        $recipient = $_ENV['SMTP_REAL_TEST'] ?? getenv('SMTP_REAL_TEST');
        if ($recipient === false || $recipient === '') {
            $this->markTestSkipped(
                'Set SMTP_REAL_TEST=you@example.com to test real SMTP delivery'
            );
        }

        $service = new EmailService('Test Brand');
        $mailer = $service->getMailer();
        $mailer->SMTPDebug = 0; // silence PHPMailer's own debug

        $result = $service->send(
            $recipient,
            'Test from EmailService ' . date('Y-m-d H:i:s'),
            '<p>This is a test email from the EmailService integration test.</p>'
        );

        if (!$result) {
            $this->fail('SMTP send failed: ' . $mailer->ErrorInfo);
        }

        $this->assertTrue($result);
    }
}
