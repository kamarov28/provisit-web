<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private User $marketingUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->marketingUser = User::create([
            'name' => 'Marketing Prolabios',
            'email' => 'marketing@prolabios.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'team' => 'MANAGEMENT',
            'initial' => 'MKT',
            'is_active' => true,
            'can_view_all_visits' => true,
            'can_edit_all_visits' => true,
            'can_archive_visit' => true,
            'can_delete_visit' => true,
        ]);
    }

    public function test_unauthenticated_guests_are_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->post('/visits', [])->assertRedirect('/login');
        $this->post('/master/customers', [])->assertRedirect('/login');
    }

    public function test_marketing_email_can_request_and_verify_otp_to_login(): void
    {
        $this->get('/login')->assertStatus(200);

        $sendRes = $this->post('/login/send-code', [
            'email' => 'marketing@prolabios.com',
        ]);
        $sendRes->assertRedirect();

        // Simulasikan OTP yang tersimpan di cache
        $cacheKey = 'provisit_login_otp:'.sha1('marketing@prolabios.com');
        $this->assertTrue(Cache::has($cacheKey));

        Cache::put($cacheKey, [
            'hash' => Hash::make('123456'),
            'user_id' => $this->marketingUser->id,
            'attempts' => 0,
        ], now()->addMinutes(10));

        // Kode salah ditolak
        $this->post('/login/verify', [
            'email' => 'marketing@prolabios.com',
            'code' => '999999',
        ])->assertSessionHasErrors('code');
        $this->assertGuest();

        // Kode benar berhasil login & redirect ke dashboard
        $verifyRes = $this->post('/login/verify', [
            'email' => 'marketing@prolabios.com',
            'code' => '123456',
        ]);
        $verifyRes->assertRedirect('/');
        $this->assertAuthenticatedAs($this->marketingUser);

        // Logout mengakhiri sesi
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_unregistered_or_inactive_email_cannot_request_login_code(): void
    {
        $this->post('/login/send-code', [
            'email' => 'orang.luar@example.com',
        ])->assertSessionHasErrors('email');

        $inactive = User::create([
            'name' => 'Ex Sales',
            'email' => 'ex@prolabios.com',
            'password' => bcrypt('password'),
            'role' => 'sales',
            'initial' => 'EX',
            'is_active' => false,
        ]);

        $this->post('/login/send-code', [
            'email' => $inactive->email,
        ])->assertSessionHasErrors('email');
    }

    public function test_visit_policy_restricts_regular_sales_while_allowing_marketing_master(): void
    {
        $salesA = User::create([
            'name' => 'Sales A',
            'email' => 'sales.a@prolabios.com',
            'password' => bcrypt('password'),
            'role' => 'sales',
            'initial' => 'SA1',
            'is_active' => true,
            'can_view_all_visits' => false,
            'can_edit_all_visits' => false,
        ]);

        $salesB = User::create([
            'name' => 'Sales B',
            'email' => 'sales.b@prolabios.com',
            'password' => bcrypt('password'),
            'role' => 'sales',
            'initial' => 'SB1',
            'is_active' => true,
            'can_view_all_visits' => false,
            'can_edit_all_visits' => false,
        ]);

        $customer = Customer::create([
            'name' => 'PT Kimia Farma',
            'sales_initial' => 'SA1',
            'is_active' => true,
        ]);

        $visitA = Visit::create([
            'visit_code' => 'VST-2026-000099',
            'title' => 'Visit Rahasia Sales A',
            'visit_date' => '2026-11-25',
            'start_time' => '10:00',
            'customer_id' => $customer->id,
            'customer_name_snapshot' => $customer->name,
            'primary_sales_user_id' => $salesA->id,
            'visit_type' => 'Offline Visit',
            'status' => 'Planned',
        ]);

        // Sales B tidak boleh mengubah status visit milik Sales A (403 Forbidden)
        $this->actingAs($salesB)
            ->patch("/visits/{$visitA->id}/status", ['status' => 'Confirmed'])
            ->assertStatus(403);

        // Akun utama marketing@prolabios.com boleh mengelola semua visit
        $this->actingAs($this->marketingUser)
            ->patch("/visits/{$visitA->id}/status", ['status' => 'Confirmed'])
            ->assertRedirect(route('visits.index'));

        $this->assertEquals('Confirmed', $visitA->fresh()->status);
    }
}
