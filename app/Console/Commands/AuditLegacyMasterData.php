<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

class AuditLegacyMasterData extends Command
{
    protected $signature = 'provisit:audit-legacy-master-data';

    protected $description = 'Audit legacy User Master and Customer Master against SQL data';

    public function handle(): int
    {
        $backendFile = 'C:/laragon/www/PROvisit/PROVisit_Backend_DB_v1.0.xlsx';
        $customerFile = 'C:/laragon/www/PROvisit/DATABASE_CUSTOMER_v1.0.xlsx';

        foreach ([$backendFile, $customerFile] as $file) {
            if (! file_exists($file)) {
                $this->error("Workbook not found: {$file}");

                return self::FAILURE;
            }
        }

        try {
            $backend = IOFactory::load($backendFile);
            $customerWorkbook = IOFactory::load($customerFile);

            $userRows = $backend
                ->getSheetByName('USER_MASTER')
                ?->toArray(null, true, true, false);

            $customerRows = $customerWorkbook
                ->getSheetByName('Customer_by_Company')
                ?->toArray(null, true, true, false);

            if (! $userRows || ! $customerRows) {
                $this->error('Required worksheet is missing.');

                return self::FAILURE;
            }

            $this->auditUsers($userRows);
            $this->auditCustomers($customerRows);

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    private function auditUsers(array $rows): void
    {
        $headers = array_map(
            fn ($value) => strtolower(trim((string) $value)),
            array_shift($rows)
        );

        $emailIndex = array_search('email', $headers);
        $initialIndex = array_search('initial', $headers);
        $nameIndex = array_search('full_name', $headers);

        if ($emailIndex === false || $initialIndex === false || $nameIndex === false) {
            $this->error('USER_MASTER headers are incomplete.');

            return;
        }

        $seen = [];
        $matched = 0;
        $unmatched = [];
        $invalid = 0;

        foreach ($rows as $row) {
            $email = strtolower(trim((string) ($row[$emailIndex] ?? '')));
            $initial = trim((string) ($row[$initialIndex] ?? ''));
            $name = trim((string) ($row[$nameIndex] ?? ''));

            if ($email === '' || $initial === '' || $name === '') {
                $invalid++;

                continue;
            }

            if (isset($seen[$email])) {
                $this->warn("Duplicate USER_MASTER email: {$email}");

                continue;
            }

            $seen[$email] = true;

            $user = DB::table('users')
                ->whereRaw('LOWER(email) = ?', [$email])
                ->first();

            if ($user) {
                $matched++;
            } else {
                $unmatched[] = $email;
            }
        }

        $this->newLine();
        $this->info('===== USER MASTER AUDIT =====');

        $this->table(
            ['Metric', 'Result'],
            [
                ['Valid legacy users', count($seen)],
                ['Matched in SQL', $matched],
                ['Unmatched in SQL', count($unmatched)],
                ['Invalid/incomplete rows', $invalid],
            ]
        );

        foreach ($unmatched as $email) {
            $this->warn("SQL user not found: {$email}");
        }
    }

    private function auditCustomers(array $rows): void
    {
        $headers = array_map(
            fn ($value) => strtolower(trim((string) $value)),
            array_shift($rows)
        );

        $codeIndex = array_search('customer id', $headers);
        $realIdIndex = array_search('customer id real', $headers);
        $nameIndex = array_search('nama customer', $headers);
        $salesIndex = array_search('pic sales', $headers);
        $groupIndex = array_search('pic group', $headers);
        $statusIndex = array_search('account status', $headers);

        if (
            $codeIndex === false ||
            $realIdIndex === false ||
            $nameIndex === false ||
            $salesIndex === false ||
            $groupIndex === false ||
            $statusIndex === false
        ) {
            $this->error('Customer_by_Company headers are incomplete.');

            return;
        }

        $seen = [];
        $matched = 0;
        $unmatched = [];
        $incomplete = 0;
        $withoutSales = 0;
        $salesInitials = [];
        $accountStatuses = [];

        foreach ($rows as $row) {
            $code = trim((string) ($row[$codeIndex] ?? ''));
            $name = trim((string) ($row[$nameIndex] ?? ''));

            if ($code === '') {
                continue;
            }

            if (isset($seen[$code])) {
                $this->warn("Duplicate customer ID: {$code}");

                continue;
            }

            $seen[$code] = true;

            if ($name === '') {
                $incomplete++;

                continue;
            }

            $salesInitial = strtoupper(
                trim((string) ($row[$salesIndex] ?? ''))
            );

            if ($salesInitial === '') {
                $withoutSales++;
            } else {
                $salesInitials[$salesInitial] =
                    ($salesInitials[$salesInitial] ?? 0) + 1;
            }

            $customer = DB::table('customers')
                ->where('customer_code', $code)
                ->first();

            if ($customer) {
                $matched++;
            } else {
                $unmatched[] = [
                    'code' => $code,
                    'name' => $name,
                    'sales' => $salesInitial,
                ];
            }
            $status = trim((string) ($row[$statusIndex] ?? ''));

            if ($status !== '') {
                $accountStatuses[$status] =
                    ($accountStatuses[$status] ?? 0) + 1;
            }
        }

        $this->newLine();
        $this->info('===== CUSTOMER MASTER AUDIT =====');

        $this->table(
            ['Metric', 'Result'],
            [
                ['Unique legacy customer IDs', count($seen)],
                ['Matched in SQL', $matched],
                ['Unmatched in SQL', count($unmatched)],
                ['Incomplete legacy rows', $incomplete],
                ['Named customers without PIC Sales', $withoutSales],
            ]
        );

        if ($unmatched) {
            $this->warn('Customers missing from SQL:');

            $this->table(
                ['Customer ID', 'Name', 'PIC Sales'],
                $unmatched
            );
        }

        $this->newLine();
        $this->info('===== PIC SALES INITIAL DISTRIBUTION =====');

        ksort($salesInitials);

        $this->table(
            ['Initial', 'Customer Count'],
            collect($salesInitials)
                ->map(fn ($count, $initial) => [$initial, $count])
                ->values()
                ->all()
        );
        $this->newLine();
        $this->info('===== ACCOUNT STATUS DISTRIBUTION =====');

        ksort($accountStatuses);

        $this->table(
            ['Account Status', 'Customer Count'],
            collect($accountStatuses)
                ->map(fn ($count, $status) => [$status, $count])
                ->values()
                ->all()
        );
    }
}
