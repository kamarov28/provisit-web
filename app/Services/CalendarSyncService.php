<?php

namespace App\Services;

use App\Models\Visit;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class CalendarSyncService
{
    /**
     * Sinkronisasi status & event ID kalender setiap kali Visit dibuat, diedit, atau di-reschedule.
     * Siap terhubung ke Google Calendar API apabila kredensial service account tersedia.
     */
    public function syncVisit(Visit $visit): void
    {
        if (in_array($visit->status, ['Draft', 'Cancelled'], true)) {
            if ($visit->calendar_event_id) {
                Log::info("CalendarSync: Event {$visit->calendar_event_id} untuk {$visit->visit_code} ditandai {$visit->status}.");
            }
            return;
        }

        if (!$visit->calendar_event_id) {
            $eventUid = strtolower($visit->visit_code) . '@provisit.mitraanalitika.local';
            $visit->forceFill(['calendar_event_id' => $eventUid])->saveQuietly();
        }
    }

    /**
     * Bangun payload iCalendar (.ics) standar RFC 5545 untuk satu atau banyak kunjungan.
     */
    public function buildIcsFeed(iterable $visits, string $calendarName = 'PROvisit Sales Schedule'): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Mitra Analitika//PROvisit Calendar//ID',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:' . $this->escapeIcsText($calendarName),
            'X-WR-TIMEZONE:Asia/Jakarta',
        ];

        foreach ($visits as $visit) {
            if ($visit->status === 'Cancelled') {
                continue;
            }

            $dateStr = $visit->visit_date ? $visit->visit_date->format('Y-m-d') : now()->format('Y-m-d');
            $startTimeStr = $visit->start_time ? substr((string) $visit->start_time, 0, 5) : '09:00';
            $startDt = Carbon::parse("{$dateStr} {$startTimeStr}", 'Asia/Jakarta');
            $endDt = $visit->end_time
                ? Carbon::parse("{$dateStr} " . substr((string) $visit->end_time, 0, 5), 'Asia/Jakarta')
                : $startDt->copy()->addHour();

            $uid = $visit->calendar_event_id ?: (strtolower($visit->visit_code) . '@provisit.mitraanalitika.local');
            $summary = "[{$visit->visit_code}] {$visit->customer_name_snapshot} - {$visit->title}";

            $descParts = [
                "Kode Visit: {$visit->visit_code}",
                "Customer: {$visit->customer_name_snapshot}",
                "Status: {$visit->status}",
                "Tipe: {$visit->visit_type} ({$visit->visit_purpose})",
            ];

            if ($visit->primarySalesUser) {
                $descParts[] = "PIC Sales: {$visit->primarySalesUser->name}";
            }
            if ($visit->pic_name_snapshot) {
                $descParts[] = "PIC Customer: {$visit->pic_name_snapshot}";
            }
            if (!empty($visit->support_marketing)) {
                $descParts[] = "Support Marketing: " . implode(', ', $visit->support_marketing);
            }
            if ($visit->principalReviews && $visit->principalReviews->isNotEmpty()) {
                $principals = $visit->principalReviews->pluck('principal_name_snapshot')->filter()->implode(', ');
                if ($principals !== '') {
                    $descParts[] = "Principal: {$principals}";
                }
            }
            if ($visit->agenda) {
                $descParts[] = "Agenda: {$visit->agenda}";
            }

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:' . $this->escapeIcsText($uid);
            $lines[] = 'DTSTAMP:' . now('UTC')->format('Ymd\THis\Z');
            $lines[] = 'DTSTART:' . $startDt->copy()->utc()->format('Ymd\THis\Z');
            $lines[] = 'DTEND:' . $endDt->copy()->utc()->format('Ymd\THis\Z');
            $lines[] = 'SUMMARY:' . $this->escapeIcsText($summary);
            $lines[] = 'DESCRIPTION:' . $this->escapeIcsText(implode("\n", $descParts));
            $lines[] = 'LOCATION:' . $this->escapeIcsText($visit->customer_name_snapshot);
            $lines[] = 'STATUS:' . ($visit->status === 'Confirmed' || $visit->status === 'Completed' ? 'CONFIRMED' : 'TENTATIVE');
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines) . "\r\n";
    }

    private function escapeIcsText(string $text): string
    {
        $text = str_replace(["\r\n", "\r", "\n"], '\n', $text);
        $text = str_replace(['\\', ';', ','], ['\\\\', '\;', '\,'], $text);
        return $text;
    }
}