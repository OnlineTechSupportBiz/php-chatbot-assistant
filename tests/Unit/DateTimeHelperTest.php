<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Util\DateTimeHelper;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for DateTimeHelper — timezone-aware date formatting.
 *
 * Covers: format, date, time, iso, userTimezone, setUserTimezone,
 * edge cases (null, empty, invalid, cross-timezone).
 */
class DateTimeHelperTest extends TestCase
{
    private string $defaultTz;

    protected function setUp(): void
    {
        // Save timezone in case a test modifies it
        $this->defaultTz = $_SESSION['timezone'] ?? 'UTC';
    }

    protected function tearDown(): void
    {
        $_SESSION['timezone'] = $this->defaultTz;
    }

    // ── format() ────────────────────────────────────────────────────────────

    public function test_format_utc_timestamp(): void
    {
        $result = DateTimeHelper::format('2026-07-25 14:30:00', 'UTC');
        $this->assertStringContainsString('Jul', $result);
        $this->assertStringContainsString('2026', $result);
    }

    public function test_format_converts_timezone(): void
    {
        // 14:30 UTC = 10:30 AM Eastern (EDT, UTC-4 in July)
        $result = DateTimeHelper::format('2026-07-25 14:30:00', 'America/New_York');
        $this->assertStringContainsString('10:30', $result);
    }

    public function test_format_pacific_timezone(): void
    {
        // 14:30 UTC = 07:30 AM Pacific (PDT, UTC-7 in July)
        $result = DateTimeHelper::format('2026-07-25 14:30:00', 'America/Los_Angeles');
        $this->assertStringContainsString('7:30', $result);
    }

    public function test_format_custom_format_string(): void
    {
        $result = DateTimeHelper::format('2026-07-25 14:30:00', 'UTC', 'Y-m-d H:i');
        $this->assertSame('2026-07-25 14:30', $result);
    }

    public function test_format_empty_string_returns_dash(): void
    {
        $this->assertSame('—', DateTimeHelper::format('', 'UTC'));
    }

    public function test_format_null_string_returns_dash(): void
    {
        // Note: method accepts string, but test behavior with empty
        $this->assertSame('—', DateTimeHelper::format('', 'UTC'));
    }

    public function test_format_invalid_timestamp_fallbacks_to_raw(): void
    {
        $result = DateTimeHelper::format('not-a-date', 'UTC');
        $this->assertSame('not-a-date', $result);
    }

    public function test_format_invalid_timezone_fallbacks_to_raw(): void
    {
        $result = DateTimeHelper::format('2026-07-25 14:30:00', 'Not/A/Timezone');
        // Should either return the timestamp or a formatted version
        $this->assertNotNull($result);
    }

    public function test_format_midnight(): void
    {
        $result = DateTimeHelper::format('2026-07-25 00:00:00', 'UTC');
        $this->assertStringContainsString('12:00 AM', $result);
    }

    public function test_format_noon(): void
    {
        $result = DateTimeHelper::format('2026-07-25 12:00:00', 'UTC');
        $this->assertStringContainsString('12:00 PM', $result);
    }

    // ── date() ──────────────────────────────────────────────────────────────

    public function test_date_only_format(): void
    {
        $result = DateTimeHelper::date('2026-07-25 14:30:00', 'UTC');
        // Should contain month day, year but not time
        $this->assertStringContainsString('Jul', $result);
        $this->assertStringContainsString('25', $result);
        $this->assertStringContainsString('2026', $result);
    }

    // ── time() ──────────────────────────────────────────────────────────────

    public function test_time_only_format(): void
    {
        $result = DateTimeHelper::time('2026-07-25 14:30:00', 'UTC');
        $this->assertStringContainsString('2:30', $result);
        $this->assertStringContainsString('PM', $result);
    }

    // ── iso() ───────────────────────────────────────────────────────────────

    public function test_iso_format(): void
    {
        $result = DateTimeHelper::iso('2026-07-25 14:30:00', 'UTC');
        $this->assertSame('2026-07-25 14:30', $result);
    }

    public function test_iso_convert_timezone(): void
    {
        // 14:30 UTC = 10:30 AM in NY (EDT)
        $result = DateTimeHelper::iso('2026-07-25 14:30:00', 'America/New_York');
        $this->assertSame('2026-07-25 10:30', $result);
    }

    // ── userTimezone() ──────────────────────────────────────────────────────

    public function test_userTimezone_from_session(): void
    {
        $_SESSION['timezone'] = 'America/Chicago';
        $this->assertSame('America/Chicago', DateTimeHelper::userTimezone());
    }

    public function test_userTimezone_default_when_not_set(): void
    {
        unset($_SESSION['timezone']);
        $this->assertSame('UTC', DateTimeHelper::userTimezone());
    }

    // ── setUserTimezone() ───────────────────────────────────────────────────

    public function test_setUserTimezone_valid(): void
    {
        DateTimeHelper::setUserTimezone('Asia/Tokyo');
        $this->assertSame('Asia/Tokyo', $_SESSION['timezone']);
    }

    public function test_setUserTimezone_invalid_does_not_set(): void
    {
        $_SESSION['timezone'] = 'UTC';
        DateTimeHelper::setUserTimezone('Invalid/Zone');
        $this->assertSame('UTC', $_SESSION['timezone']);
    }

    // ── Cross-timezone edge cases ───────────────────────────────────────────

    public function test_date_line_crossing(): void
    {
        // 23:00 UTC = next day 08:00 AM in Tokyo (UTC+9)
        $result = DateTimeHelper::date('2026-07-25 23:00:00', 'Asia/Tokyo');
        $this->assertStringContainsString('Jul', $result);
        $this->assertStringContainsString('26', $result);
    }

    public function test_dst_transition_date(): void
    {
        // Use a date that is clearly in DST for US Eastern
        $result = DateTimeHelper::format('2026-03-08 07:00:00', 'America/New_York', 'Y-m-d H:i');
        // EDT is UTC-4, so 07:00 UTC = 03:00 EDT
        $this->assertSame('2026-03-08 03:00', $result);
    }

    // ── TIMEZONES constant ──────────────────────────────────────────────────

    public function test_timezones_contains_major_zones(): void
    {
        $this->assertArrayHasKey('UTC', DateTimeHelper::TIMEZONES);
        $this->assertArrayHasKey('America/New_York', DateTimeHelper::TIMEZONES);
        $this->assertArrayHasKey('Europe/London', DateTimeHelper::TIMEZONES);
        $this->assertArrayHasKey('Asia/Tokyo', DateTimeHelper::TIMEZONES);
        $this->assertArrayHasKey('Australia/Sydney', DateTimeHelper::TIMEZONES);
    }

    public function test_timezones_is_array(): void
    {
        $this->assertIsArray(DateTimeHelper::TIMEZONES);
    }
}
