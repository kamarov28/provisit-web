<?php

namespace App\Console\Commands;

use App\Models\ActionPlan;
use App\Models\Customer;
use App\Models\CustomerPic;
use App\Models\Principal;
use App\Models\Product;
use App\Models\User;
use App\Models\Visit;
use App\Models\PrincipalReview;
use App\Models\PrincipalReviewProduct;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ImportGoogleSheetsData extends Command
{
    protected $signature = 'provisit:import-sheets';
    protected $description = 'Import master data and visit logs from Google Sheets XLSX files';

    public function handle()
    {
        $autoload = 'C:/laragon/www/mockup-prolabios/vendor/autoload.php';
        if (!file_exists($autoload)) {
            $this->error('Composer vendor autoload not found in mockup-prolabios');
            return 1;
        }
        require_once $autoload;
        spl_autoload_register(function ($class) {
            if (str_starts_with($class, 'App\\Models\\')) {
                $file = 'C:/laragon/www/provisit-web/' . str_replace('\\', '/', $class) . '.php';
                if (file_exists($file)) {
                    require_once $file;
                }
            }
        }, true, true);

        $backendFile = 'C:/laragon/www/PROvisit/PROVisit_Backend_DB_v1.0.xlsx';
        $customerFile = 'C:/laragon/www/PROvisit/DATABASE_CUSTOMER_v1.0.xlsx';
        $productFile = 'C:/laragon/www/PROvisit/DATABASE_PRODUCT_v1.0.xlsx';

        if (!file_exists($backendFile) || !file_exists($customerFile) || !file_exists($productFile)) {
            $this->error('One or more XLSX files are missing in C:/laragon/www/PROvisit/');
            return 1;
        }

        $this->info('Starting migration from Google Sheets...');

        // 1. IMPORT USERS
        $this->info('1/5 Importing Users...');
        $ssBackend = \PhpOffice\PhpSpreadsheet\IOFactory::load($backendFile);
        $userSheet = $ssBackend->getSheetByName('USER_MASTER');
        if ($userSheet) {
            $userRows = $userSheet->toArray();
            // Headers: User_ID, Email, Initial, Full_Name, Jabatan, Team, Role, Active
            for ($i = 1; $i < count($userRows); $i++) {
                $r = $userRows[$i];
                $email = trim($r[1] ?? '');
                $name = trim($r[3] ?? '');
                if (!$email || !$name) continue;

                $role = strtolower(trim($r[6] ?? 'sales'));
                $team = trim($r[5] ?? '');
                $isActive = strtolower(trim($r[7] ?? 'true')) === 'true';

                User::updateOrCreate(
                    ['email' => $email],
                    [
                        'name' => $name,
                        'password' => Hash::make('password'),
                        'role' => in_array($role, ['admin', 'manager', 'sales']) ? $role : 'sales',
                        'team' => $team ?: null,
                        'is_active' => $isActive,
                    ]
                );
            }
        }
        $this->info('Users imported: ' . User::count());

        // 2. IMPORT CUSTOMERS & PICS
        $this->info('2/5 Importing Customers & PICs...');
        $ssCust = \PhpOffice\PhpSpreadsheet\IOFactory::load($customerFile);
        $compSheet = $ssCust->getSheetByName('Customer_by_Company');
        if ($compSheet) {
            $compRows = $compSheet->toArray();
            // A: Customer ID, B: Customer ID Real, C: Nama Customer, D: Nama by NPWP, E: Type, F: PIC Sales, G: PIC Group
            $customersData = [];
            for ($i = 1; $i < count($compRows); $i++) {
                $r = $compRows[$i];
                $code = trim($r[0] ?? '');
                $name = trim($r[2] ?? '');
                if (!$code || !$name) continue;

                Customer::updateOrCreate(
                    ['customer_code' => $code],
                    [
                        'name' => $name,
                        'sector' => trim($r[4] ?? '') ?: null,
                        'is_active' => true,
                    ]
                );
            }
        }
        $this->info('Customers imported: ' . Customer::count());

        $picSheet = $ssCust->getSheetByName('Customer_by_PIC');
        if ($picSheet) {
            $picRows = $picSheet->toArray();
            // A: Customer ID, B: Nama Customer, C: PIC Sales, D: PIC Customer, E: Position, F: Location Type, G: Phone, H: Email
            $custMap = Customer::pluck('id', 'customer_code')->toArray();
            for ($i = 1; $i < count($picRows); $i++) {
                $r = $picRows[$i];
                $code = trim($r[0] ?? '');
                $picName = trim($r[3] ?? '');
                if (!$code || !$picName || !isset($custMap[$code])) continue;

                CustomerPic::updateOrCreate(
                    [
                        'customer_id' => $custMap[$code],
                        'name' => $picName,
                    ],
                    [
                        'position' => trim($r[4] ?? '') ?: null,
                        'phone' => trim($r[6] ?? '') ?: null,
                        'email' => trim($r[7] ?? '') ?: null,
                    ]
                );
            }
        }
        $this->info('Customer PICs imported: ' . CustomerPic::count());

        // 3. IMPORT PRINCIPALS & PRODUCTS
        $this->info('3/5 Importing Principals & Products...');
        $ssProd = \PhpOffice\PhpSpreadsheet\IOFactory::load($productFile);
        $prodSheet = $ssProd->getSheetByName('Product_Details');
        if ($prodSheet) {
            $prodRows = $prodSheet->toArray();
            // A: CATALOGUE, B: DESCRIPTION, C: KEMASAN, D: PRINCIPAL, E: PRINCIPAL GROUPING, F: PRODUCT CATEGORY, G: FUNCTION, H: TYPE
            $principalMap = [];
            for ($i = 1; $i < count($prodRows); $i++) {
                $r = $prodRows[$i];
                $catalogue = trim($r[0] ?? '');
                $desc = trim($r[1] ?? '');
                $prinName = trim($r[3] ?? '');
                if (!$catalogue || !$prinName) continue;

                if (!isset($principalMap[$prinName])) {
                    $p = Principal::firstOrCreate(
                        ['name' => $prinName],
                        ['country' => 'Global', 'is_active' => true]
                    );
                    $principalMap[$prinName] = $p->id;
                }

                Product::updateOrCreate(
                    ['catalogue' => $catalogue],
                    [
                        'principal_id' => $principalMap[$prinName],
                        'name' => $catalogue . ' - ' . (mb_substr($desc, 0, 100)),
                        'description' => $desc,
                        'category' => trim($r[5] ?? '') ?: null,
                        'unit' => trim($r[2] ?? '') ?: 'Unit',
                        'is_active' => true,
                    ]
                );
            }
        }
        $this->info('Principals: ' . Principal::count() . ', Products: ' . Product::count());

        // 4. IMPORT VISITS
        $this->info('4/5 Importing Visits...');
        $visitSheet = $ssBackend->getSheetByName('VISIT_DB');
        $custByCodeMap = Customer::pluck('id', 'customer_code')->toArray();
        $userMap = User::pluck('id', 'name')->toArray();
        $userByInitialMap = []; // we can check by sales user
        
        if ($visitSheet) {
            $vRows = $visitSheet->toArray();
            for ($i = 1; $i < count($vRows); $i++) {
                $r = $vRows[$i];
                $vId = trim($r[0] ?? '');
                $title = trim($r[1] ?? '');
                if (!$vId || !$title) continue;

                $rawDate = trim($r[2] ?? '');
                $visitDate = null;
                if ($rawDate) {
                    try {
                        $visitDate = date('Y-m-d', strtotime($rawDate));
                    } catch (\Exception $e) {
                        $visitDate = now()->format('Y-m-d');
                    }
                } else {
                    $visitDate = now()->format('Y-m-d');
                }

                $rawTime = trim($r[3] ?? '');
                $startTime = $rawTime ? date('H:i:s', strtotime($rawTime)) : null;

                $rawEndTime = trim($r[4] ?? '');
                $endTime = $rawEndTime ? date('H:i:s', strtotime($rawEndTime)) : null;

                $status = trim($r[14] ?? 'Planned');
                $statusFormatted = match(strtolower($status)) {
                    'completed' => 'Completed',
                    'confirmed' => 'Confirmed',
                    'cancelled' => 'Cancelled',
                    'in progress' => 'In Progress',
                    default => 'Planned',
                };

                $cCode = trim($r[5] ?? '');
                $cId = $custByCodeMap[$cCode] ?? null;

                Visit::updateOrCreate(
                    ['visit_code' => $vId],
                    [
                        'title' => $title,
                        'visit_date' => $visitDate,
                        'start_time' => $startTime,
                        'end_time' => $endTime,
                        'customer_id' => $cId,
                        'customer_name_snapshot' => trim($r[6] ?? '') ?: ($title),
                        'pic_name_snapshot' => trim($r[7] ?? '') ?: null,
                        'pic_position_snapshot' => trim($r[8] ?? '') ?: null,
                        'visit_type' => trim($r[10] ?? 'Offline Visit') ?: 'Offline Visit',
                        'visit_purpose' => trim($r[11] ?? 'Regular') ?: 'Regular',
                        'agenda' => trim($r[12] ?? '') ?: null,
                        'previsit_notes' => trim($r[13] ?? '') ?: null,
                        'postvisit_result' => trim($r[15] ?? '') ?: null,
                        'status' => $statusFormatted,
                    ]
                );
            }
        }
        $this->info('Visits imported: ' . Visit::count());

        // 5. IMPORT PRINCIPAL REVIEWS & ACTION PLANS
        $this->info('5/5 Importing Reviews & Action Plans...');
        $revSheet = $ssBackend->getSheetByName('PRINCIPAL_REVIEW_DB');
        $visitByCodeMap = Visit::pluck('id', 'visit_code')->toArray();
        $principalByNameMap = Principal::pluck('id', 'name')->toArray();

        if ($revSheet) {
            $revRows = $revSheet->toArray();
            // A: Review_ID, B: Visit_ID, C: Principal_Name, D: Product_Catalogue, E: Description, F: Exposure_Type, G: Notes, H: Feedback, I: Opportunity, J: Next_Step
            for ($i = 1; $i < count($revRows); $i++) {
                $r = $revRows[$i];
                $vCode = trim($r[1] ?? '');
                $prinName = trim($r[2] ?? '');
                if (!$vCode || !$prinName || !isset($visitByCodeMap[$vCode])) continue;

                $pId = $principalByNameMap[$prinName] ?? null;
                if (!$pId) {
                    $p = Principal::firstOrCreate(['name' => $prinName], ['is_active' => true]);
                    $pId = $p->id;
                    $principalByNameMap[$prinName] = $pId;
                }

                $rev = PrincipalReview::updateOrCreate(
                    [
                        'visit_id' => $visitByCodeMap[$vCode],
                        'principal_name_snapshot' => $prinName,
                    ],
                    [
                        'principal_id' => $pId,
                        'exposure_type' => trim($r[5] ?? '') ?: null,
                        'notes' => trim($r[6] ?? '') ?: null,
                        'customer_feedback' => trim($r[7] ?? '') ?: null,
                        'opportunity_status' => trim($r[8] ?? '') ?: null,
                        'next_step' => trim($r[9] ?? '') ?: null,
                    ]
                );

                $cat = trim($r[3] ?? '');
                $desc = trim($r[4] ?? '');
                if ($cat || $desc) {
                    PrincipalReviewProduct::firstOrCreate(
                        [
                            'principal_review_id' => $rev->id,
                            'product_catalogue' => $cat ?: '-',
                        ],
                        [
                            'product_description_snapshot' => $desc ?: '-',
                        ]
                    );
                }
            }
        }

        $actSheet = $ssBackend->getSheetByName('ACTION_DB');
        if ($actSheet) {
            $actRows = $actSheet->toArray();
            // A: Action_ID, B: Visit_ID, C: Task_Description, D: Owner_User_ID, E: Due_Date, F: Priority, G: Status
            for ($i = 1; $i < count($actRows); $i++) {
                $r = $actRows[$i];
                $vCode = trim($r[1] ?? '');
                $task = trim($r[2] ?? '');
                if (!$vCode || !$task || !isset($visitByCodeMap[$vCode])) continue;

                $rawDueDate = trim($r[4] ?? '');
                $dueDate = null;
                if ($rawDueDate) {
                    try {
                        $dueDate = date('Y-m-d', strtotime($rawDueDate));
                    } catch (\Exception $e) {
                        $dueDate = null;
                    }
                }

                ActionPlan::updateOrCreate(
                    [
                        'visit_id' => $visitByCodeMap[$vCode],
                        'task_description' => $task,
                    ],
                    [
                        'due_date' => $dueDate,
                        'priority' => trim($r[5] ?? 'Normal') ?: 'Normal',
                        'status' => trim($r[6] ?? 'Open') ?: 'Open',
                    ]
                );
            }
        }

        $this->info('Migration completed successfully!');
        return 0;
    }
}
