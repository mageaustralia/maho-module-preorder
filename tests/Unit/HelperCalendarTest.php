<?php
declare(strict_types=1);

namespace Mageaustralia\Preorder\Tests\Unit;

use PHPUnit\Framework\TestCase;

class HelperCalendarTest extends TestCase
{
    public function test_generateIcs_returns_valid_VCALENDAR(): void
    {
        $helper = new \Mageaustralia_Preorder_Helper_Calendar();
        $ics = $helper->buildEvent(
            uid: 'preorder-100000123-42@mercasystems.com',
            summary: 'Pre-order ships: Widget',
            date: new \DateTimeImmutable('2026-05-15'),
            description: 'Your pre-ordered Widget will ship around this date.',
        );
        $this->assertStringContainsString('BEGIN:VCALENDAR', $ics);
        $this->assertStringContainsString('VERSION:2.0', $ics);
        $this->assertStringContainsString('PRODID:-//Mageaustralia//Preorder//EN', $ics);
        $this->assertStringContainsString('BEGIN:VEVENT', $ics);
        $this->assertStringContainsString('UID:preorder-100000123-42@mercasystems.com', $ics);
        $this->assertStringContainsString('SUMMARY:Pre-order ships: Widget', $ics);
        $this->assertStringContainsString('DTSTART;VALUE=DATE:20260515', $ics);
        $this->assertStringContainsString('END:VEVENT', $ics);
        $this->assertStringContainsString('END:VCALENDAR', $ics);
    }

    public function test_generateIcs_escapes_special_chars(): void
    {
        $helper = new \Mageaustralia_Preorder_Helper_Calendar();
        $ics = $helper->buildEvent(
            uid: 'test@x',
            summary: 'Order #100,000,123 — special "quote"',
            date: new \DateTimeImmutable('2026-01-01'),
            description: "Line one;\nLine two,\nLine three.",
        );
        // Per RFC 5545: , and ; and \ must be escaped with backslash; newlines as \n
        $this->assertStringContainsString('SUMMARY:Order #100\\,000\\,123 — special "quote"', $ics);
        $this->assertStringContainsString('DESCRIPTION:Line one\\;\\nLine two\\,\\nLine three.', $ics);
    }

    public function test_generateIcs_uses_utc_timestamp_for_dtstamp(): void
    {
        $helper = new \Mageaustralia_Preorder_Helper_Calendar();
        $ics = $helper->buildEvent(
            uid: 'test@x',
            summary: 'Test',
            date: new \DateTimeImmutable('2026-01-01'),
            description: 'Test',
        );
        // DTSTAMP must be UTC, format YYYYMMDDTHHMMSSZ
        $this->assertMatchesRegularExpression('/DTSTAMP:\d{8}T\d{6}Z/', $ics);
    }
}
