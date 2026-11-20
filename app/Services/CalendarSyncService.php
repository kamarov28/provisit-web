<?php

namespace App\Services;

use App\Models\User;
use App\Models\Visit;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class CalendarSyncService
{
    private const GOOGLE_TOKEN_URI = 'https://oauth2.googleapis.com/token';
    private const GOOGLE_CALENDAR_BASE = 'https://www.googleapis.com/calendar/v3';
    private const GOOGLE_SCOPE = 'https://www.googleapis.com/auth/calendar';

    public function syncVisit(Visit $visit): void
    {
        $visit->loadMissing(['primarySalesUser:id,name,email,team,initial', 'principalReviews']);

        if (in_array($visit->status, ['Draft', 'Cancelled'], true)) {
            if ($this->isGoogleApiEnabled() && $this->isGoogleEventId($visit->calendar_event_id)) {
                $this->deleteGoogleEvent($visit);
            } elseif ($visit->calendar_event_id) {
                Log::info("CalendarSync: Event {$visit->calendar_event_id} untuk {$visit->visit_code} ditandai {$visit->status}.");
            }
            return;
        }

        if ($this->isGoogleApiEnabled()) {
            if ($this->upsertGoogleEvent($visit)) {
                return;
            }
        }

        if (!$visit->calendar_event_id) {
            $eventUid = strtolower($visit->visit_code) . '@provisit.mitraanalitika.local';
            $visit->forceFill(['calendar_event_id' => $eventUid])->saveQuietly();
        }
    }

    public function buildIcsFeed(iterable $visits, string $calendarName = 'PROvisit - Jadwal Kunjungan Sales'): string
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
            if ($visit->status === 'Cancelled') continue;
            [$startDt, $endDt] = $this->resolveTimeWindow($visit);
            $uid = $visit->calendar_event_id ?: (strtolower($visit->visit_code) . '@provisit.mitraanalitika.local');
            $sequence = $visit->updated_at ? $visit->updated_at->timestamp : 0;

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:' . $this->escapeIcsText($uid);
            $lines[] = 'SEQUENCE:' . $sequence;
            $lines[] = 'DTSTAMP:' . now('UTC')->format('Ymd\THis\Z');
            $lines[] = 'DTSTART:' . $startDt->copy()->utc()->format('Ymd\THis\Z');
            $lines[] = 'DTEND:' . $endDt->copy()->utc()->format('Ymd\THis\Z');
            $lines[] = 'SUMMARY:' . $this->escapeIcsText($this->buildSummary($visit));
            $lines[] = 'DESCRIPTION:' . $this->escapeIcsText($this->buildDescription($visit));
            $lines[] = 'LOCATION:' . $this->escapeIcsText((string) $visit->customer_name_snapshot);
            $lines[] = 'STATUS:' . (in_array($visit->status, ['Confirmed', 'Completed'], true) ? 'CONFIRMED' : 'TENTATIVE');
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';
        return implode("\r\n", $lines) . "\r\n";
    }

    private function isGoogleApiEnabled(): bool
    {
        if (!config('services.google_calendar.enabled')) return false;
        $credPath = (string) config('services.google_calendar.credentials_path');
        return $credPath !== '' && is_file($credPath);
    }

    private function isGoogleEventId(?string $eventId): bool
    {
        return $eventId && !str_contains($eventId, '@provisit.mitraanalitika.local');
    }

    private function resolveTargetCalendarId(Visit $visit): ?string
    {
        $team = strtoupper(trim((string) ($visit->primarySalesUser?->team ?? '')));
        $teamCalendars = config('services.google_calendar.team_calendars', []);
        if ($team !== '' && !empty($teamCalendars[$team])) return $teamCalendars[$team];
        return config('services.google_calendar.default_calendar_id') ?: null;
    }

    private function upsertGoogleEvent(Visit $visit): bool
    {
        $calendarId = $this->resolveTargetCalendarId($visit);
        $token = $calendarId ? $this->getGoogleAccessToken() : null;
        if (!$calendarId || !$token) return false;

        [$startDt, $endDt] = $this->resolveTimeWindow($visit);
        $payload = [
            'summary' => $this->buildSummary($visit),
            'location' => (string) $visit->customer_name_snapshot,
            'description' => $this->buildDescription($visit),
            'start' => ['dateTime' => $startDt->toIso8601String(), 'timeZone' => 'Asia/Jakarta'],
            'end' => ['dateTime' => $endDt->toIso8601String(), 'timeZone' => 'Asia/Jakarta'],
            'colorId' => match ($visit->status) { 'Confirmed' => '9', 'Completed' => '10', 'In Progress' => '5', default => '8' },
            'status' => 'confirmed',
            'extendedProperties' => ['private' => ['provisit_id' => (string) $visit->id, 'visit_code' => (string) $visit->visit_code]],
        ];

        if (config('services.google_calendar.send_invites')) {
            $attendees = $this->collectAttendees($visit);
            if (!empty($attendees)) $payload['attendees'] = $attendees;
        }

        $encodedCal = rawurlencode($calendarId);
        $sendUpdates = config('services.google_calendar.send_invites') ? 'all' : 'none';

        try {
            if ($this->isGoogleEventId($visit->calendar_event_id)) {
                $encodedEv = rawurlencode($visit->calendar_event_id);
                $res = Http::withToken($token)->patch(self::GOOGLE_CALENDAR_BASE . "/calendars/{$encodedCal}/events/{$encodedEv}?sendUpdates={$sendUpdates}", $payload);
                if ($res->successful()) return true;
                if (!in_array($res->status(), [404, 410], true)) {
                    Log::error("CalendarSync update failed {$visit->visit_code}", ['status' => $res->status(), 'body' => $res->body()]);
                    return false;
                }
            }

            $res = Http::withToken($token)->post(self::GOOGLE_CALENDAR_BASE . "/calendars/{$encodedCal}/events?sendUpdates={$sendUpdates}", $payload);
            if ($res->successful()) {
                if ($id = $res->json('id')) $visit->forceFill(['calendar_event_id' => $id])->saveQuietly();
                return true;
            }
            Log::error("CalendarSync insert failed {$visit->visit_code}", ['status' => $res->status(), 'body' => $res->body()]);
            return false;
        } catch (Throwable $e) {
            Log::error("CalendarSync Exception ({$visit->visit_code}): " . $e->getMessage());
            return false;
        }
    }

    private function deleteGoogleEvent(Visit $visit): void
    {
        $calendarId = $this->resolveTargetCalendarId($visit);
        $token = ($calendarId && $this->isGoogleEventId($visit->calendar_event_id)) ? $this->getGoogleAccessToken() : null;
        if (!$token) return;

        try {
            $res = Http::withToken($token)->delete(self::GOOGLE_CALENDAR_BASE . '/calendars/' . rawurlencode($calendarId) . '/events/' . rawurlencode($visit->calendar_event_id));
            if ($res->successful() || in_array($res->status(), [404, 410], true)) {
                $visit->forceFill(['calendar_event_id' => null])->saveQuietly();
            }
        } catch (Throwable $e) {
            Log::error("CalendarSync Delete Exception ({$visit->visit_code}): " . $e->getMessage());
        }
    }

    private function getGoogleAccessToken(): ?string
    {
        return Cache::remember('provisit_google_calendar_token', 3300, function () {
            $creds = json_decode((string) @file_get_contents((string) config('services.google_calendar.credentials_path')), true);
            if (empty($creds['client_email']) || empty($creds['private_key'])) return null;

            $now = time();
            $header = rtrim(strtr(base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])), '+/', '-_'), '=');
            $claims = rtrim(strtr(base64_encode(json_encode(['iss' => $creds['client_email'], 'scope' => self::GOOGLE_SCOPE, 'aud' => self::GOOGLE_TOKEN_URI, 'iat' => $now, 'exp' => $now + 3600])), '+/', '-_'), '=');
            $unsigned = "{$header}.{$claims}";
            $sig = '';
            if (!openssl_sign($unsigned, $sig, $creds['private_key'], OPENSSL_ALGO_SHA256)) return null;

            $jwt = $unsigned . '.' . rtrim(strtr(base64_encode($sig), '+/', '-_'), '=');
            $res = Http::asForm()->post(self::GOOGLE_TOKEN_URI, ['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $jwt]);
            return $res->successful() ? $res->json('access_token') : null;
        });
    }

    private function collectAttendees(Visit $visit): array
    {
        $emails = array_filter([$visit->primarySalesUser?->email]);
        if (!empty($visit->support_marketing) && is_array($visit->support_marketing)) {
            $initials = array_map(fn ($i) => strtoupper(trim((string) $i)), $visit->support_marketing);
            $emails = array_merge($emails, User::where('is_active', true)->whereIn(DB::raw('UPPER(TRIM(initial))'), $initials)->whereNotNull('email')->pluck('email')->all());
        }
        return collect($emails)->map('trim')->filter(fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))->unique()->map(fn ($e) => ['email' => $e])->values()->all();
    }

    private function resolveTimeWindow(Visit $visit): array
    {
        $dateStr = $visit->visit_date ? $visit->visit_date->format('Y-m-d') : now()->format('Y-m-d');
        $startTimeStr = $visit->start_time ? substr((string) $visit->start_time, 0, 5) : '09:00';
        $startDt = Carbon::parse("{$dateStr} {$startTimeStr}", 'Asia/Jakarta');
        $endDt = $visit->end_time ? Carbon::parse("{$dateStr} " . substr((string) $visit->end_time, 0, 5), 'Asia/Jakarta') : $startDt->copy()->addHour();
        return [$startDt, $endDt];
    }

    private function buildSummary(Visit $visit): string
    {
        return ($visit->status === 'Completed' ? '[DONE] ' : '') . "[{$visit->visit_code}] {$visit->customer_name_snapshot} - {$visit->title}";
    }

    private function buildDescription(Visit $visit): string
    {
        $parts = ["Kode Visit: {$visit->visit_code}", "Customer: {$visit->customer_name_snapshot}", "Status: {$visit->status}", "Tipe: {$visit->visit_type} ({$visit->visit_purpose})"];
        if ($visit->primarySalesUser) $parts[] = "PIC Sales: {$visit->primarySalesUser->name}";
        if ($visit->pic_name_snapshot) $parts[] = "PIC Customer: {$visit->pic_name_snapshot}";
        if (!empty($visit->support_marketing)) $parts[] = 'Support Marketing: ' . implode(', ', $visit->support_marketing);
        if ($visit->principalReviews?->isNotEmpty()) {
            $p = $visit->principalReviews->pluck('principal_name_snapshot')->filter()->implode(', ');
            if ($p !== '') $parts[] = "Principal: {$p}";
        }
        if ($visit->agenda) $parts[] = "\nAgenda:\n{$visit->agenda}";
        return implode("\n", $parts);
    }

    private function escapeIcsText(string $text): string
    {
        $text = str_replace(["\r\n", "\r", "\n"], '\n', $text);
        return str_replace(['\\', ';', ','], ['\\\\', '\;', '\,'], $text);
    }
}