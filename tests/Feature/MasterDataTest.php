<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Principal;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::create(['name' => 'Marketing', 'email' => 'marketing@prolabios.com', 'password' => bcrypt('password'), 'role' => 'admin', 'initial' => 'MKT', 'is_active' => true]);
        $this->actingAs($user);
    }

    public function test_can_store_customer_with_auto_generated_code(): void
    {
        $response = $this->post('/master/customers', [
            'name' => 'PT Bio Farma (Persero)',
            'sales_initial' => 'bs',
            'sector' => 'Vaksin & Farmasi',
            'city' => 'Bandung',
        ]);

        $response->assertRedirect(route('visits.index'));
        $this->assertDatabaseHas('customers', [
            'customer_code' => 'CUST-00001',
            'name' => 'PT Bio Farma (Persero)',
            'sales_initial' => 'BS',
            'city' => 'Bandung',
            'is_active' => true,
        ]);
    }

    public function test_can_store_customer_pic(): void
    {
        $customer = Customer::create([
            'name' => 'RSUPN Dr. Cipto Mangunkusumo',
            'sales_initial' => 'BS',
            'is_active' => true,
        ]);

        $response = $this->post('/master/customer-pics', [
            'customer_id' => $customer->id,
            'name' => 'Ibu Rina Kusuma',
            'position' => 'Kepala Lab Mikrobiologi',
            'phone' => '08123456789',
            'email' => 'rina@rscm.co.id',
        ]);

        $response->assertRedirect(route('visits.index'));
        $this->assertDatabaseHas('customer_pics', [
            'customer_id' => $customer->id,
            'name' => 'Ibu Rina Kusuma',
            'position' => 'Kepala Lab Mikrobiologi',
        ]);
    }

    public function test_can_store_and_search_products_by_principal(): void
    {
        $activePrincipal = Principal::create(['name' => 'Merck', 'is_active' => true]);
        $inactivePrincipal = Principal::create(['name' => 'Legacy Brand', 'is_active' => false]);

        // Gagal jika principal tidak aktif
        $this->post('/master/products', [
            'principal_id' => $inactivePrincipal->id,
            'catalogue' => '999999',
            'description' => 'Produk Nonaktif',
        ])->assertSessionHasErrors('principal_id');

        // Berhasil untuk principal aktif
        $this->post('/master/products', [
            'principal_id' => $activePrincipal->id,
            'catalogue' => '105463',
            'description' => 'Tryptic Soy Agar (TSA) 500g',
            'category' => 'Culture Media',
        ])->assertRedirect(route('visits.index'));

        $this->assertDatabaseHas('products', [
            'principal_id' => $activePrincipal->id,
            'catalogue' => '105463',
            'description' => 'Tryptic Soy Agar (TSA) 500g',
        ]);

        Product::create([
            'principal_id' => $activePrincipal->id,
            'catalogue' => '105458',
            'name' => 'MacConkey Agar',
            'description' => 'MacConkey Agar 500g',
            'is_active' => true,
        ]);

        $searchRes = $this->getJson("/products/search?principal_id={$activePrincipal->id}&q=Tryptic");
        $searchRes->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonFragment(['catalogue' => '105463']);

        $wildcardRes = $this->getJson("/products/search?principal_id={$activePrincipal->id}&q=" . urlencode('%%'));
        $wildcardRes->assertStatus(200)
            ->assertJsonCount(0);
    }
}
