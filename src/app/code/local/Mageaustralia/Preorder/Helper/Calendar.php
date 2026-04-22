<?php
class Mageaustralia_Preorder_Helper_Calendar extends Mage_Core_Helper_Abstract
{
    /**
     * Build an iCalendar (RFC 5545) all-day VEVENT wrapped in a VCALENDAR.
     */
    public function buildEvent(
        string $uid,
        string $summary,
        \DateTimeImmutable $date,
        string $description = '',
    ): string {
        $dtstamp = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Ymd\THis\Z');
        $dtstart = $date->format('Ymd');

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Mageaustralia//Preorder//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:' . $uid,
            'DTSTAMP:' . $dtstamp,
            'DTSTART;VALUE=DATE:' . $dtstart,
            'SUMMARY:' . $this->escape($summary),
            'DESCRIPTION:' . $this->escape($description),
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        // RFC 5545 line endings are CRLF.
        return implode("\r\n", $lines) . "\r\n";
    }

    /**
     * Escape per RFC 5545 §3.3.11: \\ \, \; \n
     */
    private function escape(string $value): string
    {
        $value = str_replace('\\', '\\\\', $value);
        $value = str_replace([',', ';'], ['\\,', '\\;'], $value);
        $value = str_replace(["\r\n", "\n"], '\\n', $value);
        return $value;
    }
}
