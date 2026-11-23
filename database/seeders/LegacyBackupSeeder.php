<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\CustomerPic;
use App\Models\Principal;
use App\Models\PrincipalReview;
use App\Models\PrincipalReviewProduct;
use App\Models\Product;
use App\Models\User;
use App\Models\Visit;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class LegacyBackupSeeder extends Seeder
{
    public function run(): void
    {
        $path = storage_path('app/provisit_backup_20261123_0927.json');
        if (! file_exists($path)) {
            $this->command->error("File tidak ditemukan: {$path}");
            return;
        }

        $data = json_decode(file_get_contents($path), true);

        // Bersihkan data dummy dari DatabaseSeeder awal
        Visit::whereIn('visit_code', ['VST-202611-001'])->delete();
        Customer::whereIn('customer_code', ['CUST-001', 'CUST-002'])->delete();

        DB::transaction(function () use ($data) {
            // 1. USERS
            $userIdMap = [];
            $userInitialMap = [];
            foreach ($data['users'] ?? [] as $u) {
                $email = trim($u['Email'] ?? '');
                if (! $email) continue;

                $initial = strtoupper(trim($u['Initial'] ?? '')) ?: null;

                $user = User::updateOrCreate(
                    ['email' => $email],
                    [
                        'name' => trim($u['Full_Name'] ?? $email),
                        'password' => Hash::make(env('DEFAULT_MARKETING_PASSWORD', 'Prolabios#2026!')),
                        'role' => strtolower(trim($u['Role'] ?? 'sales')),
                        'team' => trim($u['Team'] ?? '') ?: null,
                        'initial' => $initial,
                        'position' => trim($u['Jabatan'] ?? '') ?: null,
                        'notes' => trim($u['Notes'] ?? '') ?: null,
                        'is_active' => $this->toBool($u['Active'] ?? true),
                        'can_view_all_visits' => $this->toBool($u['Can_View_All_Visits'] ?? false),
                        'can_edit_all_visits' => $this->toBool($u['Can_Edit_All_Visits'] ?? false),
                        'can_archive_visit' => $this->toBool($u['Can_Archive_Visit'] ?? false),
                        'can_delete_visit' => $this->toBool($u['Can_Delete_Visit'] ?? false),
                    ]
                );

                $legacyUserId = trim($u['User_ID'] ?? '');
                if ($legacyUserId) {
                    $userIdMap[$legacyUserId] = $user->id;
                    if ($initial) {
                        $userInitialMap[$legacyUserId] = $initial;
                    }
                }
            }
            $this->command->info('Users imported: ' . count($userIdMap));

            // 2. CUSTOMERS
            $customerCodeMap = [];
            foreach ($data['customers'] ?? [] as $c) {
                $code = trim($c['Customer ID'] ?? '');
                $name = trim($c['Nama Customer'] ?? '');
                if (! $code || ! $name) continue;

                $customer = Customer::updateOrCreate(
                    ['customer_code' => $code],
                    [
                        'customer_id_real' => trim($c['Customer ID Real'] ?? '') ?: null,
                        'name' => $name,
                        'sector' => trim($c['Kategori'] ?? $c['Type'] ?? '') ?: null,
                        'sales_initial' => strtoupper(trim($c['PIC Sales'] ?? '')) ?: null,
                        'pic_group' => trim($c['PIC Group'] ?? '') ?: null,
                        'account_status' => trim($c['Account Status'] ?? '') ?: null,
                        'lead_since' => $this->parseDate($c['Lead Since'] ?? null),
                        'is_active' => strtolower(trim($c['Account Status'] ?? 'active')) !== 'inactive',
                    ]
                );

                $customerCodeMap[$code] = $customer->id;
            }
            $this->command->info('Customers imported: ' . count($customerCodeMap));

            // 3. CUSTOMER PICS
            $picCount = 0;
            $seenPrimaryCustomer = [];
            foreach ($data['customer_pics'] ?? [] as $p) {
                $custCode = trim($p['Customer ID'] ?? '');
                $picName = trim($p['PIC Customer'] ?? '');
                $customerId = $customerCodeMap[$custCode] ?? null;
                if (! $customerId || ! $picName) continue;

                $alamat = trim($p['Alamat'] ?? '');
                if ($alamat) {
                    Customer::where('id', $customerId)->whereNull('address')->update(['address' => $alamat]);
                }

                $isPrimary = ! isset($seenPrimaryCustomer[$customerId]);
                $seenPrimaryCustomer[$customerId] = true;

                CustomerPic::updateOrCreate(
                    ['customer_id' => $customerId, 'name' => $picName],
                    [
                        'position' => trim($p['Position'] ?? '') ?: null,
                        'department' => trim($p['Location Type'] ?? '') ?: null,
                        'phone' => trim($p['Phone Number'] ?? '') ?: null,
                        'email' => trim($p['Email'] ?? '') ?: null,
                        'is_primary' => $isPrimary,
                    ]
                );
                $picCount++;
            }
            $this->command->info('Customer PICs imported: ' . $picCount);

            // 4. PRINCIPALS
            $principalMap = [];
            foreach ($data['principals'] ?? [] as $pr) {
                $name = trim($pr['Name'] ?? '');
                if (! $name) continue;

                $principal = Principal::updateOrCreate(
                    ['name' => $name],
                    [
                        'country' => trim($pr['Country of Origin'] ?? '') ?: null,
                        'description' => trim($pr['Main Products'] ?? '') ?: null,
                        'is_active' => true,
                    ]
                );
                $principalMap[$name] = $principal->id;
            }
            $this->command->info('Principals imported: ' . count($principalMap));

            // 5. PRODUCTS
            Product::query()->delete();
            $now = now();
            $productRows = [];
            foreach ($data['products'] ?? [] as $prod) {
                $cat = trim($prod['CATALOGUE'] ?? '');
                $desc = trim($prod['DESCRIPTION'] ?? '');
                $prinName = trim($prod['PRINCIPAL'] ?? '');
                if (! $cat && ! $desc) continue;

                if ($prinName && ! isset($principalMap[$prinName])) {
                    $newPrin = Principal::firstOrCreate(['name' => $prinName], ['is_active' => true]);
                    $principalMap[$prinName] = $newPrin->id;
                }

                $rawName = $desc ?: $cat;
                $firstLine = trim(strtok($rawName, "\r\n"));
                $shortName = mb_strimwidth($firstLine ?: $rawName, 0, 250, '...');
                $func = trim($prod['FUNCTION'] ?? '');
                $fullDesc = $func && $desc ? "{$desc}\n\nFunction: {$func}" : ($desc ?: ($func ?: null));

                $productRows[] = [
                    'principal_id' => $principalMap[$prinName] ?? null,
                    'catalogue' => mb_strimwidth($cat ?: 'NO-CAT', 0, 100, ''),
                    'name' => $shortName,
                    'description' => $fullDesc,
                    'category' => mb_strimwidth(trim($prod['PRODUCT CATEGORY'] ?? $prod['TYPE'] ?? ''), 0, 255, '') ?: null,
                    'unit' => trim($prod['KEMASAN'] ?? '') ?: null,
                    'is_active' => strtolower(trim($prod['PRODUCT STATUS'] ?? 'active')) !== 'discontinued',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($productRows, 500) as $chunk) {
                Product::insert($chunk);
            }
            $this->command->info('Products imported: ' . count($productRows));

            // Kelompokkan support_marketing berdasarkan Visit_ID
            $supportsByVisit = [];
            foreach ($data['supports'] ?? [] as $s) {
                $vCode = trim($s['Visit_ID'] ?? '');
                $uId = trim($s['User_ID'] ?? '');
                $initial = $userInitialMap[$uId] ?? $uId;
                if ($vCode && $initial) {
                    $supportsByVisit[$vCode][] = $initial;
                }
            }

            // 6. VISITS
            $visitCodeMap = [];
            foreach ($data['visits'] ?? [] as $v) {
                $vCode = trim($v['Visit_ID'] ?? '');
                if (! $vCode) continue;

                $custId = $customerCodeMap[trim($v['Customer_ID'] ?? '')] ?? null;
                $picName = trim($v['PIC_Customer'] ?? '');
                $picRecord = ($custId && $picName)
                    ? CustomerPic::where('customer_id', $custId)->where('name', $picName)->first()
                    : null;

                $supportList = array_values(array_unique($supportsByVisit[$vCode] ?? []));

                $visit = Visit::updateOrCreate(
                    ['visit_code' => $vCode],
                    [
                        'title' => trim($v['Visit_Title'] ?? '(Untitled Visit)'),
                        'visit_date' => $this->parseDate($v['Visit_Date'] ?? null) ?? now()->format('Y-m-d'),
                        'start_time' => $this->parseTime($v['Start_Time'] ?? null),
                        'end_time' => $this->parseTime($v['End_Time'] ?? null),
                        'customer_id' => $custId,
                        'customer_name_snapshot' => trim($v['Customer_Name_Snapshot'] ?? ''),
                        'customer_pic_id' => $picRecord?->id,
                        'pic_name_snapshot' => $picName ?: null,
                        'pic_position_snapshot' => trim($v['PIC_Position_Snapshot'] ?? '') ?: null,
                        'primary_sales_user_id' => $userIdMap[trim($v['Primary_Sales_User_ID'] ?? '')] ?? null,
                        'support_marketing' => ! empty($supportList) ? $supportList : null,
                        'visit_type' => trim($v['Visit_Type'] ?? 'Offline Visit'),
                        'visit_purpose' => trim($v['Visit_Purpose'] ?? 'Regular'),
                        'agenda' => trim($v['Agenda'] ?? '') ?: null,
                        'previsit_notes' => trim($v['Previsit_Notes'] ?? '') ?: null,
                        'postvisit_result' => trim($v['Postvisit_Result'] ?? '') ?: null,
                        'status' => trim($v['Status'] ?? 'Draft'),
                        'calendar_event_id' => trim($v['Calendar_Event_ID_LEGACY'] ?? '') ?: null,
                        'report_completed_at' => $this->parseDateTime($v['Report_Completed_At'] ?? null),
                        'created_by' => $userIdMap[trim($v['Created_By'] ?? '')] ?? null,
                        'updated_by' => $userIdMap[trim($v['Updated_By'] ?? '')] ?? null,
                        'is_archived' => $this->toBool($v['Is_Archived'] ?? false),
                    ]
                );

                $visitCodeMap[$vCode] = $visit->id;
            }
            $this->command->info('Visits imported: ' . count($visitCodeMap));

            // 7. PRINCIPAL REVIEWS & PRODUCTS
            $reviewIdMap = [];
            foreach ($data['reviews'] ?? [] as $r) {
                $vId = $visitCodeMap[trim($r['Visit_ID'] ?? '')] ?? null;
                if (! $vId) continue;

                $prinName = trim($r['Principal_Name'] ?? '');
                $review = PrincipalReview::updateOrCreate(
                    [
                        'visit_id' => $vId,
                        'client_key' => trim($r['Client_Key'] ?? '') ?: trim($r['Review_ID'] ?? ''),
                    ],
                    [
                        'principal_id' => $principalMap[$prinName] ?? null,
                        'principal_name_snapshot' => $prinName,
                        'exposure_type' => trim($r['Exposure_Type'] ?? '') ?: null,
                        'opportunity_status' => trim($r['Opportunity_Status'] ?? '') ?: null,
                        'notes' => trim($r['Notes'] ?? '') ?: null,
                        'customer_feedback' => trim($r['Customer_Feedback'] ?? '') ?: null,
                        'next_step' => trim($r['Next_Step'] ?? '') ?: null,
                    ]
                );

                if (! empty($r['Review_ID'])) {
                    $reviewIdMap[trim($r['Review_ID'])] = $review->id;
                }
            }

            foreach ($data['review_products'] ?? [] as $rp) {
                $revId = $reviewIdMap[trim($rp['Review_ID'] ?? '')] ?? null;
                if (! $revId) continue;

                $cat = trim($rp['Product_Catalogue'] ?? '');
                $prod = $cat ? Product::where('catalogue', $cat)->first() : null;

                PrincipalReviewProduct::firstOrCreate(
                    [
                        'principal_review_id' => $revId,
                        'product_catalogue' => $cat ?: null,
                        'product_description_snapshot' => trim($rp['Product_Description_Snapshot'] ?? '') ?: null,
                    ],
                    [
                        'product_id' => $prod?->id,
                    ]
                );
            }
        });
    }

    private function toBool($val): bool
    {
        if (is_bool($val)) return $val;
        return in_array(strtoupper(trim((string) $val)), ['TRUE', '1', 'YES', 'YA'], true);
    }

    private function parseDate($val): ?string
    {
        $v = trim((string) $val);
        if (! $v) return null;
        try {
            return Carbon::parse($v)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function parseTime($val): ?string
    {
        $v = trim((string) $val);
        if (! $v) return null;
        if (preg_match('/(\d{1,2}):(\d{2})/', $v, $m)) {
            return sprintf('%02d:%02d:00', $m[1], $m[2]);
        }
        return null;
    }

    private function parseDateTime($val): ?string
    {
        $v = trim((string) $val);
        if (! $v) return null;
        try {
            return Carbon::parse($v)->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            return null;
        }
    }
}
