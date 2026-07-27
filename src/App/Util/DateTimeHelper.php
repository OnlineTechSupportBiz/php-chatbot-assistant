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

namespace App\Util;

/**
 * DateTimeHelper — timezone-aware date/time formatting.
 *
 * All timestamps in the database are stored in UTC.
 * This helper converts them to the user's preferred timezone for display.
 */
class DateTimeHelper
{
    /** Major timezones grouped by region for the settings dropdown. */
    public const TIMEZONES = [
        'UTC'                                         => 'UTC (Coordinated Universal Time)',
        'America/New_York'                            => 'US Eastern (New York)',
        'America/Chicago'                             => 'US Central (Chicago)',
        'America/Denver'                              => 'US Mountain (Denver)',
        'America/Phoenix'                             => 'US Mountain (Phoenix — no DST)',
        'America/Los_Angeles'                         => 'US Pacific (Los Angeles)',
        'America/Anchorage'                           => 'US Alaska (Anchorage)',
        'Pacific/Honolulu'                            => 'US Hawaii (Honolulu)',
        'America/Argentina/Buenos_Aires'              => 'Argentina (Buenos Aires)',
        'America/Sao_Paulo'                           => 'Brasil (São Paulo)',
        'America/Mexico_City'                         => 'Mexico (Mexico City)',
        'America/Toronto'                             => 'Canada Eastern (Toronto)',
        'America/Vancouver'                           => 'Canada Pacific (Vancouver)',
        'Europe/London'                               => 'United Kingdom (London)',
        'Europe/Paris'                                => 'Central Europe (Paris/Berlin)',
        'Europe/Athens'                               => 'Eastern Europe (Athens/Helsinki)',
        'Europe/Moscow'                               => 'Moscow',
        'Europe/Istanbul'                             => 'Turkey (Istanbul)',
        'Asia/Dubai'                                  => 'Gulf (Dubai)',
        'Asia/Kolkata'                                => 'India (Kolkata)',
        'Asia/Dhaka'                                  => 'Bangladesh (Dhaka)',
        'Asia/Bangkok'                                => 'Indochina (Bangkok/Jakarta)',
        'Asia/Shanghai'                               => 'China (Shanghai)',
        'Asia/Singapore'                              => 'Singapore',
        'Asia/Tokyo'                                  => 'Japan (Tokyo)',
        'Asia/Seoul'                                  => 'Korea (Seoul)',
        'Australia/Sydney'                            => 'Australia Eastern (Sydney)',
        'Australia/Adelaide'                          => 'Australia Central (Adelaide)',
        'Australia/Perth'                             => 'Australia Western (Perth)',
        'Pacific/Auckland'                            => 'New Zealand (Auckland)',
        'Pacific/Fiji'                                => 'Fiji',
        'Africa/Cairo'                                => 'Egypt (Cairo)',
        'Africa/Johannesburg'                         => 'South Africa (Johannesburg)',
        'Africa/Lagos'                                => 'West Africa (Lagos)',
        'Africa/Nairobi'                              => 'East Africa (Nairobi)',
    ];

    /**
     * Format a database timestamp (UTC) for display in the user's timezone.
     *
     * @param  string $dbTimestamp  UTC timestamp from the database (e.g. '2026-07-24 14:30:00')
     * @param  string $timezone     User's timezone (e.g. 'America/New_York')
     * @param  string $format       PHP date() format
     * @return string               Formatted date string, or the original value on error
     */
    public static function format(string $dbTimestamp, string $timezone = 'UTC', string $format = 'M j, Y g:i A'): string
    {
        if ($dbTimestamp === '' || $dbTimestamp === null) {
            return '—';
        }

        try {
            $dt = new \DateTimeImmutable($dbTimestamp, new \DateTimeZone('UTC'));
            $dt = $dt->setTimezone(new \DateTimeZone($timezone));
            return $dt->format($format);
        } catch (\Throwable $e) {
            return $dbTimestamp;
        }
    }

    /**
     * Short format (date only).
     */
    public static function date(string $dbTimestamp, string $timezone = 'UTC'): string
    {
        return self::format($dbTimestamp, $timezone, 'M j, Y');
    }

    /**
     * Short time format.
     */
    public static function time(string $dbTimestamp, string $timezone = 'UTC'): string
    {
        return self::format($dbTimestamp, $timezone, 'g:i A');
    }

    /**
     * ISO-like datetime for data attributes.
     */
    public static function iso(string $dbTimestamp, string $timezone = 'UTC'): string
    {
        return self::format($dbTimestamp, $timezone, 'Y-m-d H:i');
    }

    /**
     * Get the user's timezone from the session (set after login / settings save).
     * Falls back to 'UTC'.
     */
    public static function userTimezone(): string
    {
        return $_SESSION['timezone'] ?? 'UTC';
    }

    /**
     * Set the user's timezone in the session.
     */
    public static function setUserTimezone(string $timezone): void
    {
        if (isset(self::TIMEZONES[$timezone])) {
            $_SESSION['timezone'] = $timezone;
        }
    }
}
