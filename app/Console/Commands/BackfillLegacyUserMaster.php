<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

class BackfillLegacyUserMaster extends Command
{
    protected $signature = 'provisit:backfill-user-master {--apply : Apply changes to SQL database}';

    protected $description = 'Backfill SQL users from legacy USER_MASTER safely';

    public function handle(): int
    {
        $file = 'C:/laragon/www/PROvisit/PROVisit_Backend_DB_v1.0.xlsx';

        if (!file_exists($file)) {
            $this->error("Workbook not found: {$file}");
            return self::FAILURE;
        }

        $sheet = IOFactory::load($file)->getSheetByName('USER_MASTER');

        if (!$sheet) {
            $this->error('Sheet USER_MASTER not found.');
            return self::FAILURE;
        }

        $rows = $sheet->toArray(null, true, true, false);

        if (empty($rows)) {
            $this->error('USER_MASTER is empty.');
            return self::FAILURE;
        }

        $headers = array_map(
            fn ($header) => strtolower(trim((string) $header)),
            $rows[0]
        );

        $required = [
            'email',
            'initial',
            'full_name',
            'jabatan',
            'team',
            'role',
            'active',
            'can_view_all_visits',
            'can_edit_all_visits',
            'can_archive_visit',
            'can_delete_visit',
            'notes',
        ];

        $indexes = [];

        foreach ($required as $column) {
            $index = array_search($column, $headers, true);

            if ($index === false) {
                $this->error("Missing USER_MASTER column: {$column}");
                return self::FAILURE;
            }

            $indexes[$column] = $index;
        }

        $roleMapping = [
            'sales' => 'sales',
            'management' => 'management',
            'admin / product manager' => 'admin',
            'product specialist' => 'product_specialist',
            'sales coordinator' => 'sales_coordinator',
        ];

        $updates = [];
        $unmatched = [];
        $unsupportedRoles = [];
        $invalidRows = [];

        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];

            $email = strtolower(trim((string) ($row[$indexes['email']] ?? '')));

            if ($email === '') {
                continue;
            }

            $legacyName = trim((string) ($row[$indexes['full_name']] ?? ''));

            if ($legacyName === '') {
                $invalidRows[] = $email;
                continue;
            }

            $user = DB::table('users')
                ->whereRaw('LOWER(email) = ?', [$email])
                ->first();

            if (!$user) {
                $unmatched[] = $email;
                continue;
            }

            $legacyRole = strtolower(trim((string) ($row[$indexes['role']] ?? '')));
            $mappedRole = $roleMapping[$legacyRole] ?? null;

            if ($mappedRole === null) {
                $unsupportedRoles[] = [
                    $legacyName,
                    $legacyRole,
                    $user->role,
                ];
            }

            $updates[] = [
                'id' => $user->id,
                'name' => $legacyName,
                'email' => $email,
                'initial' => trim((string) ($row[$indexes['initial']] ?? '')) ?: null,
                'position' => trim((string) ($row[$indexes['jabatan']] ?? '')) ?: null,
                'team' => trim((string) ($row[$indexes['team']] ?? '')) ?: null,
                'role' => $mappedRole,
                'is_active' => $this->parseBoolean($row[$indexes['active']] ?? null),
                'can_view_all_visits' => $this->parseBoolean($row[$indexes['can_view_all_visits']] ?? null),
                'can_edit_all_visits' => $this->parseBoolean($row[$indexes['can_edit_all_visits']] ?? null),
                'can_archive_visit' => $this->parseBoolean($row[$indexes['can_archive_visit']] ?? null),
                'can_delete_visit' => $this->parseBoolean($row[$indexes['can_delete_visit']] ?? null),
                'notes' => trim((string) ($row[$indexes['notes']] ?? '')) ?: null,
            ];
        }

        $this->info('===== USER MASTER BACKFILL PREVIEW =====');

        $this->table(
            ['SQL ID', 'Name', 'Email', 'Initial', 'Position', 'Team', 'Current Role', 'Mapped Role'],
            collect($updates)->map(function ($item) {
                $currentRole = DB::table('users')->where('id', $item['id'])->value('role');

                return [
                    $item['id'],
                    $item['name'],
                    $item['email'],
                    $item['initial'],
                    $item['position'],
                    $item['team'],
                    $currentRole,
                    $item['role'] ?? '(preserve)',
                ];
            })->all()
        );

        $this->newLine();
        $this->info('Matched users: ' . count($updates));
        $this->info('Unmatched emails: ' . count($unmatched));
        $this->info('Invalid rows: ' . count($invalidRows));
        $this->info('Unsupported roles: ' . count($unsupportedRoles));

        if ($unsupportedRoles) {
            $this->warn('===== ROLES PRESERVED WITHOUT MAPPING =====');

            $this->table(
                ['Name', 'Legacy Role', 'Existing SQL Role'],
                $unsupportedRoles
            );
        }

        if ($unmatched) {
            $this->warn('Unmatched emails: ' . implode(', ', $unmatched));
        }

        if (!$this->option('apply')) {
            $this->warn('DRY-RUN ONLY. No database changes were made.');
            $this->comment('Run with --apply after reviewing the preview.');
            return self::SUCCESS;
        }

        try {
            DB::transaction(function () use ($updates) {
                foreach ($updates as $item) {
                    $data = [
                        'name' => $item['name'],
                        'initial' => $item['initial'],
                        'position' => $item['position'],
                        'team' => $item['team'],
                        'is_active' => $item['is_active'],
                        'can_view_all_visits' => $item['can_view_all_visits'],
                        'can_edit_all_visits' => $item['can_edit_all_visits'],
                        'can_archive_visit' => $item['can_archive_visit'],
                        'can_delete_visit' => $item['can_delete_visit'],
                        'notes' => $item['notes'],
                        'updated_at' => now(),
                    ];

                    // Preserve existing role when legacy role is not mapped.
                    if ($item['role'] !== null) {
                        $data['role'] = $item['role'];
                    }

                    DB::table('users')
                        ->where('id', $item['id'])
                        ->update($data);
                }
            });
        } catch (Throwable $e) {
            $this->error('Backfill failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->info('User Master backfill completed successfully.');
        $this->info('Updated users: ' . count($updates));

        return self::SUCCESS;
    }

    private function parseBoolean(mixed $value): ?bool
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return in_array(
            strtolower(trim((string) $value)),
            ['true', '1', 'yes', 'active'],
            true
        );
    }
}
