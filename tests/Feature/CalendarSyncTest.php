<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Principal;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CalendarSyncTest extends TestCase
{
    use RefreshDatabase;

    private User $salesYes;

    private Customer $customer;

    private Principal $principal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->salesYes = User::create([
            'name' => 'Andi Wijaya',
            'email' => 'andi@mitraanalitika.local',
            'password' => bcrypt('password'),
            'role' => 'sales',
            'team' => 'YES',
            'initial' => 'AW',
            'is_active' => true,
        ]);

        $this->customer = Customer::create([
            'name' => 'PT Indofood CBP',
            'sales_initial' => 'AW',
            'is_active' => true,
        ]);

        $this->principal = Principal::create([
            'name' => 'Neogen',
            'is_active' => true,
        ]);
    }

    public function test_ics_feed_exports_active_visits_and_excludes_cancelled(): void
    {
        $activeVisit = Visit::create([
            'visit_code' => 'VST-2026-000010',
            'title' => 'Audit Hygiene Soleris',
            'visit_date' => '2026-11-26',
            'start_time' => '09:30',
            'customer_id' => $this->customer->id,
            'customer_name_snapshot' => $this->customer->name,
            'primary_sales_user_id' => $this->salesYes->id,
            'visit_type' => 'Offline Visit',
            'status' => 'Confirmed',
        ]);

        Visit::create([
            'visit_code' => 'VST-2026-000011',
            'title' => 'Visit Dibatalkan',
            'visit_date' => '2026-11-27',
            'start_time' => '13:00',
            'customer_id' => $this->customer->id,
            'customer_name_snapshot' => $this->customer->name,
            'primary_sales_user_id' => $this->salesYes->id,
            'visit_type' => 'Online Visit',
            'status' => 'Cancelled',
        ]);

        $feedRes = $this->get('/visits/calendar.ics');
        $feedRes->assertStatus(200);
        $content = $feedRes->getContent();
        $this->assertStringContainsString('BEGIN:VCALENDAR', $content);
        $this->assertStringContainsString('VST-2026-000010', $content);
        $this->assertStringNotContainsString('VST-2026-000011', $content);

        $singleRes = $this->get("/visits/{$activeVisit->id}/calendar.ics");
        $singleRes->assertStatus(200);
        $this->assertStringContainsString('Audit Hygiene Soleris', $singleRes->getContent());
    }

    public function test_google_calendar_api_syncs_updates_and_deletes_events_when_enabled(): void
    {
        $tmpCred = tempnam(sys_get_temp_dir(), 'gcal_');
        file_put_contents($tmpCred, json_encode([
            'client_email' => 'provisit-bot@project.iam.gserviceaccount.com',
            'private_key' => 'dummy-key',
        ]));

        try {
            config([
                'services.google_calendar.enabled' => true,
                'services.google_calendar.credentials_path' => $tmpCred,
                'services.google_calendar.default_calendar_id' => 'default@group.calendar.google.com',
                'services.google_calendar.team_calendars' => [
                    'YES' => 'team-yes@group.calendar.google.com',
                ],
            ]);

            Cache::put('provisit_google_calendar_token', 'fake-oauth-token', 300);

            Http::fake([
                'https://www.googleapis.com/calendar/v3/calendars/team-yes%40group.calendar.google.com/events*' => Http::sequence()
                    ->push(['id' => 'gcal_evt_999'], 200) // Create
                    ->push(['id' => 'gcal_evt_999'], 200) // Reschedule patch
                    ->push([], 204),                      // Delete on Cancel
            ]);

            // 1. Create Visit -> Insert ke kalender tim YES
            $this->post('/visits', [
                'title' => 'Sync Google Calendar Test',
                'visit_date' => '2026-11-29',
                'start_time' => '10:00',
                'visit_type' => 'Offline Visit',
                'customer_id' => $this->customer->id,
                'status' => 'Planned',
                'principal_reviews' => [
                    ['principal_id' => $this->principal->id],
                ],
            ])->assertRedirect(route('visits.index'));

            $visit = Visit::first();
            $this->assertEquals('gcal_evt_999', $visit->calendar_event_id);

            // 2. Reschedule -> Patch event di Google Calendar
            $this->patch("/visits/{$visit->id}/reschedule", [
                'visit_date' => '2026-11-30',
            ])->assertRedirect(route('visits.index'));

            // 3. Cancel -> Hapus event dari Google Calendar & reset calendar_event_id
            $this->patch("/visits/{$visit->id}/status", [
                'status' => 'Cancelled',
            ])->assertRedirect(route('visits.index'));

            $this->assertNull($visit->fresh()->calendar_event_id);
        } finally {
            @unlink($tmpCred);
        }
    }
}
