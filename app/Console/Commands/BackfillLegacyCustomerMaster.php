<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use DateTimeImmutable;
use Throwable;

class BackfillLegacyCustomerMaster extends Command
{
    protected $signature = 'provisit:backfill-customer-master {--apply : Apply changes to SQL database}';

    protected $description = 'Backfill SQL customers from legacy Customer Master safely';

    public function handle(): int
    {
        $file = 'C:/laragon/www/PROvisit/DATABASE_CUSTOMER_v1.0.xlsx';

        if (!file_exists($file)) {
            $this->error("Workbook not found: {$file}");
            return self::FAILURE;
        }

        try {
            $spreadsheet = IOFactory::load($file);
            $sheet = $spreadsheet->getSheetByName('Customer_by_Company');

            if (!$sheet) {
                $this->error('Sheet Customer_by_Company not found.');
                return self::FAILURE;
            }

            $headers = $sheet->rangeToArray('A1:Z1', null, true, true, false)[0];

            $headerMap = [];
            foreach ($headers as $index => $header) {
                $headerMap[strtolower(trim((string) $header))] = $index + 1;
            }

            $required = [
                'customer id',
                'customer id real',
                'nama customer',
                'pic sales',
                'pic group',
                'account status',
                'lead since',
            ];

            foreach ($required as $header) {
                if (!isset($headerMap[$header])) {
                    $this->error("Missing Excel column: {$header}");
                    return self::FAILURE;
                }
            }

            $customers = DB::table('customers')
                ->select('id', 'customer_code')
                ->get()
                ->filter(fn ($customer) => trim((string) $customer->customer_code) !== '')
                ->keyBy(fn ($customer) => strtolower(trim($customer->customer_code)));

            $updates = [];
            $unmatched = [];
            $incomplete = 0;
            $seen = [];

            for ($rowNumber = 2; $rowNumber <= $sheet->getHighestDataRow(); $rowNumber++) {
                $get = function (string $header) use ($sheet, $headerMap, $rowNumber) {
                    $value = $sheet->getCell([$headerMap[$header], $rowNumber])->getValue();

                    if (is_string($value) && str_starts_with($value, '=')) {
                        if (preg_match('/,\s*"((?:[^"]|"")*)"\s*\)\s*$/', $value, $matches)) {
                            return str_replace('""', '"', $matches[1]);
                        }
                    }

                    return $value;
                };

                $customerCode = trim((string) ($get('customer id') ?? ''));
                $name = trim((string) ($get('nama customer') ?? ''));

                if ($customerCode === '') {
                    continue;
                }

                if ($name === '') {
                    $incomplete++;
                    continue;
                }

                $key = strtolower($customerCode);

                if (isset($seen[$key])) {
                    $this->warn("Duplicate Customer ID in Excel: {$customerCode}");
                    continue;
                }

                $seen[$key] = true;

                if (!$customers->has($key)) {
                    $unmatched[] = $customerCode;
                    continue;
                }

                $leadCell = $sheet->getCell([$headerMap['lead since'], $rowNumber]);

                $leadValue = $leadCell->getValue();
                $leadSince = null;

                if ($leadValue !== null && $leadValue !== '') {
                    try {
                        if (Date::isDateTime($leadCell) && is_numeric($leadValue)) {
                            $leadSince = Date::excelToDateTimeObject(
                                (float) $leadValue
                            )->format('Y-m-d');
                        } else {
                            $leadSince = (new DateTimeImmutable(
                                (string) $leadValue
                            ))->format('Y-m-d');
                        }
                    } catch (Throwable) {
                        $this->warn("Invalid Lead Since at Excel row {$rowNumber}");
                    }
                }

                $updates[] = [
                    'id' => $customers[$key]->id,
                    'customer_code' => $customerCode,
                    'customer_id_real' => trim((string) ($get('customer id real') ?? '')) ?: null,
                    'sales_initial' => strtoupper(trim((string) ($get('pic sales') ?? ''))) ?: null,
                    'pic_group' => trim((string) ($get('pic group') ?? '')) ?: null,
                    'account_status' => trim((string) ($get('account status') ?? '')) ?: null,
                    'lead_since' => $leadSince,
                ];
            }

            $this->newLine();
            $this->info('===== CUSTOMER MASTER BACKFILL PREVIEW =====');

            $this->table(
                ['SQL ID', 'Customer ID', 'Real ID', 'PIC Sales', 'PIC Group', 'Account Status', 'Lead Since'],
                collect($updates)->take(15)->map(fn ($row) => [
                    $row['id'],
                    $row['customer_code'],
                    $row['customer_id_real'] ?? '-',
                    $row['sales_initial'] ?? '-',
                    $row['pic_group'] ?? '-',
                    $row['account_status'] ?? '-',
                    $row['lead_since'] ?? '-',
                ])->all()
            );

            $this->newLine();
            $this->line('Matched customers: ' . count($updates));
            $this->line('Unmatched customer IDs: ' . count($unmatched));
            $this->line('Incomplete rows (no customer name): ' . $incomplete);

            if (count($unmatched) > 0) {
                $this->warn('Unmatched IDs: ' . implode(', ', $unmatched));
            }

            if (!$this->option('apply')) {
                $this->warn('DRY-RUN ONLY. No database changes were made.');
                return self::SUCCESS;
            }

            DB::transaction(function () use ($updates) {
                foreach ($updates as $row) {
                    $id = $row['id'];
                    unset($row['id'], $row['customer_code']);

                    DB::table('customers')
                        ->where('id', $id)
                        ->update(array_merge($row, [
                            'updated_at' => now(),
                        ]));
                }
            });

            $this->info('Customer Master backfill completed successfully.');
            $this->info('Updated customers: ' . count($updates));

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }
}
