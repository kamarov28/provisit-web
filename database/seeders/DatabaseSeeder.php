<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\CustomerPic;
use App\Models\Principal;
use App\Models\Product;
use App\Models\User;
use App\Models\Visit;
use App\Models\PrincipalReview;
use App\Models\PrincipalReviewProduct;
use App\Models\ActionPlan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Users
        $admin = User::firstOrCreate(
            ['email' => 'admin@prolabios.com'],
            [
                'name' => 'Administrator',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'team' => 'MANAGEMENT',
                'phone' => '021-3874-1447',
                'is_active' => true,
            ]
        );

        $sales1 = User::firstOrCreate(
            ['email' => 'sales.yes@prolabios.com'],
            [
                'name' => 'Sales YES Team',
                'password' => Hash::make('password'),
                'role' => 'sales',
                'team' => 'YES',
                'phone' => '081234567890',
                'is_active' => true,
            ]
        );

        $sales2 = User::firstOrCreate(
            ['email' => 'sales.mou@prolabios.com'],
            [
                'name' => 'Sales MOU Team',
                'password' => Hash::make('password'),
                'role' => 'sales',
                'team' => 'MOU',
                'phone' => '081298765432',
                'is_active' => true,
            ]
        );

        // 2. Principals & Products
        $principalsData = [
            'MICA' => [
                ['catalogue' => 'MICA-ADV-01', 'name' => 'MICA Advance Microplate Reader', 'category' => 'Instrument'],
                ['catalogue' => 'MICA-WASH-02', 'name' => 'MICA Washer 96-well', 'category' => 'Instrument'],
            ],
            'Biowest' => [
                ['catalogue' => 'BW-FBS-500', 'name' => 'Fetal Bovine Serum 500ml', 'category' => 'Reagent'],
                ['catalogue' => 'BW-DMEM-01', 'name' => 'DMEM High Glucose Media', 'category' => 'Reagent'],
            ],
            'SPL Life Sciences' => [
                ['catalogue' => 'SPL-TC-96', 'name' => 'Tissue Culture Plate 96 well', 'category' => 'Consumables'],
                ['catalogue' => 'SPL-TUBE-15', 'name' => 'Conical Tube 15ml', 'category' => 'Consumables'],
            ],
        ];

        foreach ($principalsData as $prinName => $prods) {
            $p = Principal::firstOrCreate(
                ['name' => $prinName],
                ['country' => 'Global', 'description' => "Principal $prinName", 'is_active' => true]
            );

            foreach ($prods as $prod) {
                Product::firstOrCreate(
                    ['catalogue' => $prod['catalogue']],
                    [
                        'principal_id' => $p->id,
                        'name' => $prod['name'],
                        'category' => $prod['category'],
                        'unit' => 'Unit',
                        'is_active' => true,
                    ]
                );
            }
        }

        // 3. Customers & PICs
        $c1 = Customer::firstOrCreate(
            ['name' => 'PT Bio Farma (Persero)'],
            [
                'customer_code' => 'CUST-001',
                'sector' => 'Pharma / Vaccine',
                'city' => 'Bandung',
                'address' => 'Jl. Pasteur No. 28, Bandung',
                'phone' => '022-2033755',
                'is_active' => true,
            ]
        );

        $pic1 = CustomerPic::firstOrCreate(
            ['customer_id' => $c1->id, 'name' => 'Dr. Bambang S.'],
            [
                'position' => 'Head of QC Laboratory',
                'department' => 'Quality Control',
                'phone' => '081122334455',
                'is_primary' => true,
            ]
        );

        $c2 = Customer::firstOrCreate(
            ['name' => 'Pusat Riset Biomedis BRIN'],
            [
                'customer_code' => 'CUST-002',
                'sector' => 'Research / Government',
                'city' => 'Cibinong',
                'address' => 'KST Soekarno, Cibinong, Bogor',
                'phone' => '021-8754587',
                'is_active' => true,
            ]
        );

        $pic2 = CustomerPic::firstOrCreate(
            ['customer_id' => $c2->id, 'name' => 'Siti Rahma, M.Si.'],
            [
                'position' => 'Peneliti Ahli Muda',
                'department' => 'Laboratorium Sel Punca',
                'phone' => '081399887766',
                'is_primary' => true,
            ]
        );

        // 4. Sample Visit
        $visit = Visit::firstOrCreate(
            ['visit_code' => 'VST-202611-001'],
            [
                'title' => 'Kunjungan Rutin & Pengenalan Reagen Biowest',
                'visit_date' => now()->format('Y-m-d'),
                'start_time' => '10:00:00',
                'end_time' => '12:00:00',
                'customer_id' => $c1->id,
                'customer_name_snapshot' => $c1->name,
                'customer_pic_id' => $pic1->id,
                'pic_name_snapshot' => $pic1->name,
                'pic_position_snapshot' => $pic1->position,
                'primary_sales_user_id' => $sales1->id,
                'visit_type' => 'On-site',
                'visit_purpose' => 'Prospect & Introduction',
                'agenda' => 'Presentasi spesifikasi FBS Biowest dan demo plate SPL',
                'previsit_notes' => 'Siapkan brosur dan certificate of analysis (CoA)',
                'status' => 'Planned',
                'created_by' => $sales1->id,
            ]
        );

        $prinBiowest = Principal::where('name', 'Biowest')->first();
        if ($prinBiowest) {
            $rev = PrincipalReview::firstOrCreate(
                ['visit_id' => $visit->id, 'principal_id' => $prinBiowest->id],
                [
                    'principal_name_snapshot' => $prinBiowest->name,
                    'exposure_type' => 'Introduction',
                    'opportunity_status' => 'Interested',
                    'client_key' => 'REV-001',
                ]
            );

            $prodBiowest = Product::where('principal_id', $prinBiowest->id)->first();
            if ($prodBiowest) {
                PrincipalReviewProduct::firstOrCreate(
                    ['principal_review_id' => $rev->id, 'product_id' => $prodBiowest->id],
                    [
                        'product_catalogue' => $prodBiowest->catalogue,
                        'product_description_snapshot' => $prodBiowest->name,
                    ]
                );
            }
        }

        ActionPlan::firstOrCreate(
            ['visit_id' => $visit->id, 'task_description' => 'Kirimkan penawaran harga resmi (RFQ) Biowest FBS'],
            [
                'owner_user_id' => $sales1->id,
                'due_date' => now()->addDays(3)->format('Y-m-d'),
                'priority' => 'High',
                'status' => 'Pending',
                'client_key' => 'ACT-001',
            ]
        );
    }
}
