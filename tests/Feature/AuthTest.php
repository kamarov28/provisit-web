<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            'password' => Hash::make('Prolabios#2026!'),
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

    public function test_marketing_user_can_login_with_email_and_password(): void
    {
        $this->get('/login')->assertStatus(200);

        // Password salah ditolak
        $this->post('/login', [
            'email' => 'marketing@prolabios.com',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');
        $this->assertGuest();

        // Password benar berhasil login
        $this->post('/login', [
            'email' => 'marketing@prolabios.com',
            'password' => 'Prolabios#2026!',
        ])->assertRedirect('/');
        $this->assertAuthenticatedAs($this->marketingUser);

        // Logout mengakhiri sesi
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_inactive_user_cannot_login(): void
    {
        $inactive = User::create([
            'name' => 'Ex Sales',
            'email' => 'ex@prolabios.com',
            'password' => Hash::make('Prolabios#2026!'),
            'role' => 'sales',
            'initial' => 'EX',
            'is_active' => false,
        ]);

        $this->post('/login', [
            'email' => $inactive->email,
            'password' => 'Prolabios#2026!',
        ])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_visit_policy_restricts_regular_sales_while_allowing_marketing_master(): void
    {
        $salesA = User::create([
            'name' => 'Sales A',
            'email' => 'sales.a@prolabios.com',
            'password' => Hash::make('Prolabios#2026!'),
            'role' => 'sales',
            'initial' => 'SA1',
            'is_active' => true,
            'can_view_all_visits' => false,
            'can_edit_all_visits' => false,
        ]);

        $salesB = User::create([
            'name' => 'Sales B',
            'email' => 'sales.b@prolabios.com',
            'password' => Hash::make('Prolabios#2026!'),
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

        $this->actingAs($salesB)
            ->patch("/visits/{$visitA->id}/status", ['status' => 'Confirmed'])
            ->assertRedirect(route('visits.index'));

        $this->actingAs($this->marketingUser)
            ->patch("/visits/{$visitA->id}/status", ['status' => 'Confirmed'])
            ->assertRedirect(route('visits.index'));

        $this->assertEquals('Confirmed', $visitA->fresh()->status);
    }

    public function test_companion_sales_can_view_assigned_visit_and_scoped_stats(): void
    {
        $salesA = User::create([
            'name' => 'Sales Utama',
            'email' => 'utama@prolabios.com',
            'password' => Hash::make('Prolabios#2026!'),
            'role' => 'sales',
            'initial' => 'SU1',
            'is_active' => true,
            'can_view_all_visits' => false,
        ]);

        $coSales = User::create([
            'name' => 'Sales Pendamping',
            'email' => 'pendamping@prolabios.com',
            'password' => Hash::make('Prolabios#2026!'),
            'role' => 'sales',
            'initial' => 'SP1',
            'is_active' => true,
            'can_view_all_visits' => false,
        ]);

        $unrelatedSales = User::create([
            'name' => 'Sales Lain',
            'email' => 'lain@prolabios.com',
            'password' => Hash::make('Prolabios#2026!'),
            'role' => 'sales',
            'initial' => 'SL1',
            'is_active' => true,
            'can_view_all_visits' => false,
        ]);

        $customer = Customer::create([
            'name' => 'PT Kalbe Farma',
            'sales_initial' => 'SU1',
            'is_active' => true,
        ]);

        Visit::create([
            'visit_code' => 'VST-2026-000101',
            'title' => 'Visit Bersama Co-Sales',
            'visit_date' => '2026-11-26',
            'start_time' => '09:00',
            'customer_id' => $customer->id,
            'customer_name_snapshot' => $customer->name,
            'primary_sales_user_id' => $salesA->id,
            'companion_sales' => ['SP1'],
            'visit_type' => 'On-site',
            'status' => 'Planned',
        ]);

        $this->actingAs($coSales)
            ->get('/')
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Visits/Index')
                ->has('visits', 1)
                ->where('stats.total', 1)
                ->where('stats.planned', 1)
            );

        $this->actingAs($unrelatedSales)
            ->get('/')
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Visits/Index')
                ->has('visits', 0)
                ->where('stats.total', 0)
            );
    }
}
