<?php

namespace Tests\Feature;

use App\Models\ActionPlan;
use App\Models\Customer;
use App\Models\CustomerPic;
use App\Models\Principal;
use App\Models\Product;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class VisitWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $salesUser;
    private User $supportUser;
    private Customer $customer;
    private CustomerPic $pic;
    private Principal $principal;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->salesUser = User::create([
            'name' => 'Budi Santoso',
            'email' => 'budi@mitraanalitika.local',
            'password' => bcrypt('password'),
            'role' => 'sales',
            'team' => 'YES',
            'initial' => 'BS',
            'is_active' => true,
        ]);

        $this->supportUser = User::create([
            'name' => 'Siti Aminah',
            'email' => 'siti@mitraanalitika.local',
            'password' => bcrypt('password'),
            'role' => 'product_specialist',
            'team' => 'YES',
            'initial' => 'SA',
            'is_active' => true,
        ]);

        $this->customer = Customer::create([
            'customer_code' => 'CUST-00001',
            'name' => 'PT Kalbe Farma Tbk',
            'sales_initial' => 'BS',
            'sector' => 'Farmasi',
            'city' => 'Jakarta',
            'is_active' => true,
        ]);

        $this->pic = CustomerPic::create([
            'customer_id' => $this->customer->id,
            'name' => 'Dr. Hendra',
            'position' => 'QC Manager',
            'is_primary' => true,
        ]);

        $this->principal = Principal::create([
            'name' => 'bioMÃƒÂ©rieux',
            'country' => 'France',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'principal_id' => $this->principal->id,
            'catalogue' => '410851',
            'name' => 'VITEK 2 Compact',
            'description' => 'VITEK 2 Compact Automated ID/AST System',
            'is_active' => true,
        ]);
    }

    public function test_index_renders_inertia_page_with_expected_props(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Visits/Index')
            ->has('visits')
            ->has('stats')
            ->has('principals', 1)
            ->has('customers', 1)
            ->has('salesUsers', 2)
            ->has('supportMarketingUsers', 1)
        );
    }

    public function test_can_create_planned_visit_with_principal_products_and_audit_log(): void
    {
        $payload = [
            'title' => 'Presentasi VITEK 2 Compact',
            'visit_date' => '2026-11-25',
            'start_time' => '10:00',
            'visit_type' => 'Offline Visit',
            'visit_purpose' => 'Product Introduction / Presentation',
            'customer_id' => $this->customer->id,
            'customer_pic_id' => $this->pic->id,
            'support_marketing' => ['SA'],
            'agenda' => 'Demo sistem identifikasi mikroba otomatis',
            'previsit_notes' => 'Bawa brosur dan contoh kartu VITEK',
            'status' => 'Planned',
            'principal_reviews' => [
                [
                    'principal_id' => $this->principal->id,
                    'exposure_type' => 'Introduction',
                    'opportunity_status' => 'Interested',
                    'notes' => 'Customer tertarik uji coba sampel',
                    'products' => [
                        ['product_id' => $this->product->id],
                    ],
                ],
            ],
            'action_plans' => [
                [
                    'task_description' => 'Kirim penawaran harga VITEK 2',
                    'owner_user_id' => $this->salesUser->id,
                    'due_date' => '2026-11-30',
                    'priority' => 'High',
                ],
            ],
        ];

        $response = $this->post('/visits', $payload);

        $response->assertRedirect(route('visits.index'));

        $visit = Visit::first();
        $this->assertNotNull($visit);
        $this->assertMatchesRegularExpression('/^VST-\d{4}-\d{6}$/', $visit->visit_code);
        $this->assertEquals($this->salesUser->id, $visit->primary_sales_user_id);
        $this->assertEquals('PT Kalbe Farma Tbk', $visit->customer_name_snapshot);
        $this->assertEquals('Dr. Hendra', $visit->pic_name_snapshot);
        $this->assertEquals(['SA'], $visit->support_marketing);
        $this->assertNotNull($visit->calendar_event_id);

        $this->assertDatabaseHas('principal_reviews', [
            'visit_id' => $visit->id,
            'principal_id' => $this->principal->id,
            'principal_name_snapshot' => 'bioMÃƒÂ©rieux',
        ]);

        $this->assertDatabaseHas('principal_review_products', [
            'product_id' => $this->product->id,
            'product_catalogue' => '410851',
        ]);

        $this->assertDatabaseHas('action_plans', [
            'visit_id' => $visit->id,
            'task_description' => 'Kirim penawaran harga VITEK 2',
            'status' => 'Open',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'CREATE',
            'auditable_type' => Visit::class,
            'auditable_id' => $visit->id,
        ]);
    }

    public function test_new_visit_cannot_be_created_directly_as_confirmed_or_completed(): void
    {
        $response = $this->post('/visits', [
            'title' => 'Visit Langsung Selesai',
            'visit_date' => '2026-11-25',
            'start_time' => '10:00',
            'visit_type' => 'Offline Visit',
            'customer_id' => $this->customer->id,
            'status' => 'Completed',
            'postvisit_result' => 'Selesai',
            'principal_reviews' => [
                ['principal_id' => $this->principal->id],
            ],
            'action_plans' => [
                ['task_description' => 'Follow up'],
            ],
        ]);

        $response->assertSessionHasErrors('status');
        $this->assertDatabaseCount('visits', 0);
    }

    public function test_rejects_mismatched_product_principal_or_customer_pic(): void
    {
        $otherPrincipal = Principal::create(['name' => 'Thermo Fisher', 'is_active' => true]);
        $otherCustomer = Customer::create(['name' => 'PT Kimia Farma', 'sales_initial' => 'BS', 'is_active' => true]);
        $otherPic = CustomerPic::create(['customer_id' => $otherCustomer->id, 'name' => 'Pak Andi']);

        // Produk tidak sesuai principal
        $res1 = $this->post('/visits', [
            'title' => 'Mismatch Product',
            'visit_date' => '2026-11-25',
            'start_time' => '10:00',
            'visit_type' => 'Offline Visit',
            'customer_id' => $this->customer->id,
            'status' => 'Planned',
            'principal_reviews' => [
                [
                    'principal_id' => $otherPrincipal->id,
                    'products' => [['product_id' => $this->product->id]],
                ],
            ],
        ]);
        $res1->assertSessionHasErrors('principal_reviews');

        // PIC bukan milik customer terpilih
        $res2 = $this->post('/visits', [
            'title' => 'Mismatch PIC',
            'visit_date' => '2026-11-25',
            'start_time' => '10:00',
            'visit_type' => 'Offline Visit',
            'customer_id' => $this->customer->id,
            'customer_pic_id' => $otherPic->id,
            'status' => 'Planned',
            'principal_reviews' => [
                ['principal_id' => $this->principal->id],
            ],
        ]);
        $res2->assertSessionHasErrors('customer_pic_id');
    }

    public function test_completed_status_requires_postvisit_result_and_action_plan(): void
    {
        $visit = Visit::create([
            'visit_code' => 'VST-2026-000001',
            'title' => 'Kunjungan Evaluasi',
            'visit_date' => '2026-11-20',
            'start_time' => '09:00',
            'customer_id' => $this->customer->id,
            'customer_name_snapshot' => $this->customer->name,
            'primary_sales_user_id' => $this->salesUser->id,
            'visit_type' => 'Offline Visit',
            'status' => 'Confirmed',
        ]);

        // Gagal jika belum ada postvisit_result & action_plan
        $failRes = $this->patch("/visits/{$visit->id}/status", ['status' => 'Completed']);
        $failRes->assertSessionHasErrors('status');
        $this->assertEquals('Confirmed', $visit->fresh()->status);

        // Lengkapi lewat PUT /visits/{visit} -> berhasil Completed
        $okRes = $this->put("/visits/{$visit->id}", [
            'title' => 'Kunjungan Evaluasi',
            'visit_date' => '2026-11-20',
            'start_time' => '09:00',
            'visit_type' => 'Offline Visit',
            'customer_id' => $this->customer->id,
            'status' => 'Completed',
            'postvisit_result' => 'Customer menyetujui spesifikasi alat.',
            'principal_reviews' => [
                ['principal_id' => $this->principal->id],
            ],
            'action_plans' => [
                ['task_description' => 'Terbitkan quotation resmi'],
            ],
        ]);

        $okRes->assertRedirect(route('visits.index'));
        $freshVisit = $visit->fresh();
        $this->assertEquals('Completed', $freshVisit->status);
        $this->assertNotNull($freshVisit->report_completed_at);
    }

    public function test_reschedule_works_for_active_visit_and_blocks_completed_visit(): void
    {
        $visit = Visit::create([
            'visit_code' => 'VST-2026-000002',
            'title' => 'Jadwal Demo',
            'visit_date' => '2026-11-22',
            'start_time' => '10:00',
            'customer_id' => $this->customer->id,
            'customer_name_snapshot' => $this->customer->name,
            'primary_sales_user_id' => $this->salesUser->id,
            'visit_type' => 'Offline Visit',
            'status' => 'Planned',
        ]);

        $res = $this->patch("/visits/{$visit->id}/reschedule", [
            'visit_date' => '2026-11-28',
            'start_time' => '14:30',
        ]);
        $res->assertRedirect(route('visits.index'));
        $this->assertEquals('2026-11-28', $visit->fresh()->visit_date->format('Y-m-d'));
        $this->assertEquals('14:30', substr((string) $visit->fresh()->start_time, 0, 5));

        // Kunci status menjadi Completed lalu coba reschedule lagi
        $visit->update(['status' => 'Completed']);
        $blocked = $this->patch("/visits/{$visit->id}/reschedule", [
            'visit_date' => '2026-12-01',
        ]);
        $blocked->assertSessionHasErrors('visit_date');
        $this->assertEquals('2026-11-28', $visit->fresh()->visit_date->format('Y-m-d'));
    }

    public function test_can_toggle_action_plan_completion_status(): void
    {
        $visit = Visit::create([
            'visit_code' => 'VST-2026-000003',
            'title' => 'Follow-up Penawaran',
            'visit_date' => '2026-11-21',
            'start_time' => '11:00',
            'customer_id' => $this->customer->id,
            'customer_name_snapshot' => $this->customer->name,
            'primary_sales_user_id' => $this->salesUser->id,
            'visit_type' => 'Online Visit',
            'status' => 'Confirmed',
        ]);

        $plan = ActionPlan::create([
            'visit_id' => $visit->id,
            'task_description' => 'Kirim CoA produk',
            'owner_user_id' => $this->salesUser->id,
            'status' => 'Open',
        ]);

        // Toggle Open -> Completed
        $this->patch("/action-plans/{$plan->id}/toggle")->assertRedirect(route('visits.index'));
        $this->assertEquals('Completed', $plan->fresh()->status);
        $this->assertNotNull($plan->fresh()->completed_at);

        // Toggle Completed -> Open
        $this->patch("/action-plans/{$plan->id}/toggle")->assertRedirect(route('visits.index'));
        $this->assertEquals('Open', $plan->fresh()->status);
        $this->assertNull($plan->fresh()->completed_at);
    }
}