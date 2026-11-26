<?php

namespace App\Services;

use App\Models\ActionPlan;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\CustomerPic;
use App\Models\Principal;
use App\Models\Product;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VisitMutationService
{
    public function __construct(
        private CalendarSyncService $calendarSync
    ) {}

    public function createVisit(array $validated, ?User $actor, ?string $ipAddress): Visit
    {
        $customer = Customer::findOrFail($validated['customer_id']);
        $pic = $this->resolveCustomerPic($customer, $validated['customer_pic_id'] ?? null);
        $salesUserId = $this->resolveSalesUserId($customer);
        $supportMarketing = $this->normalizeInitialList($validated['support_marketing'] ?? []);
        $companionSales = $this->normalizeInitialList($validated['companion_sales'] ?? []);

        return DB::transaction(function () use (
            $validated,
            $customer,
            $pic,
            $salesUserId,
            $supportMarketing,
            $companionSales,
            $actor,
            $ipAddress
        ) {
            [$productsByReview, $principalNames] = $this->validateAndLoadReviews($validated['principal_reviews']);

            $countThisYear = Visit::whereYear('created_at', now()->year)->count() + 1;
            $visitCode = 'VST-'.now()->year.'-'.str_pad($countThisYear, 6, '0', STR_PAD_LEFT);

            $visit = Visit::create([
                'visit_code' => $visitCode,
                'title' => $validated['title'],
                'visit_date' => $validated['visit_date'],
                'start_time' => $validated['start_time'],
                'customer_id' => $customer->id,
                'customer_name_snapshot' => $customer->name,
                'customer_pic_id' => $pic?->id,
                'pic_name_snapshot' => $pic?->name,
                'pic_position_snapshot' => $pic?->position,
                'primary_sales_user_id' => $salesUserId,
                'support_marketing' => $supportMarketing,
                'companion_sales' => $companionSales,
                'visit_type' => $validated['visit_type'],
                'visit_purpose' => $validated['visit_purpose'] ?? 'Product Introduction / Presentation',
                'agenda' => $validated['agenda'] ?? null,
                'previsit_notes' => $validated['previsit_notes'] ?? null,
                'postvisit_result' => $validated['postvisit_result'] ?? null,
                'status' => $validated['status'],
                'report_completed_at' => $validated['status'] === 'Completed' ? now() : null,
                'created_by' => $actor?->id,
                'updated_by' => $actor?->id,
            ]);

            $this->syncVisitRelations($visit, $validated, $productsByReview, $principalNames, $salesUserId);
            $this->calendarSync->syncVisit($visit);

            AuditLog::create([
                'user_id' => $actor?->id,
                'action' => 'CREATE',
                'auditable_type' => Visit::class,
                'auditable_id' => $visit->id,
                'old_values' => null,
                'new_values' => [
                    'visit_code' => $visit->visit_code,
                    'visit_date' => $visit->visit_date?->format('Y-m-d'),
                    'status' => $visit->status,
                    'customer' => $customer->name,
                ],
                'ip_address' => $ipAddress,
            ]);

            return $visit;
        });
    }

    public function updateVisit(Visit $visit, array $validated, ?User $actor, ?string $ipAddress): Visit
    {
        $customer = Customer::findOrFail($validated['customer_id']);
        $pic = $this->resolveCustomerPic($customer, $validated['customer_pic_id'] ?? null);
        $salesUserId = $this->resolveSalesUserId($customer);
        $supportMarketing = $this->normalizeInitialList($validated['support_marketing'] ?? []);
        $companionSales = $this->normalizeInitialList($validated['companion_sales'] ?? []);

        $oldSnapshot = [
            'title' => $visit->title,
            'visit_date' => $visit->visit_date?->format('Y-m-d'),
            'start_time' => $visit->start_time ? substr((string) $visit->start_time, 0, 5) : null,
            'status' => $visit->status,
        ];

        return DB::transaction(function () use (
            $visit,
            $validated,
            $customer,
            $pic,
            $salesUserId,
            $supportMarketing,
            $companionSales,
            $oldSnapshot,
            $actor,
            $ipAddress
        ) {
            [$productsByReview, $principalNames] = $this->validateAndLoadReviews($validated['principal_reviews']);

            $completedAt = null;
            if ($validated['status'] === 'Completed') {
                $completedAt = $visit->report_completed_at ?? now();
            }

            $visit->update([
                'title' => $validated['title'],
                'visit_date' => $validated['visit_date'],
                'start_time' => $validated['start_time'],
                'customer_id' => $customer->id,
                'customer_name_snapshot' => $customer->name,
                'customer_pic_id' => $pic?->id,
                'pic_name_snapshot' => $pic?->name,
                'pic_position_snapshot' => $pic?->position,
                'primary_sales_user_id' => $salesUserId,
                'support_marketing' => $supportMarketing,
                'companion_sales' => $companionSales,
                'visit_type' => $validated['visit_type'],
                'visit_purpose' => $validated['visit_purpose'] ?? 'Product Introduction / Presentation',
                'agenda' => $validated['agenda'] ?? null,
                'previsit_notes' => $validated['previsit_notes'] ?? null,
                'postvisit_result' => $validated['postvisit_result'] ?? null,
                'status' => $validated['status'],
                'report_completed_at' => $completedAt,
                'updated_by' => $actor?->id,
            ]);

            $visit->principalReviews()->each(function ($review) {
                $review->products()->delete();
                $review->delete();
            });
            $visit->actionPlans()->delete();

            $this->syncVisitRelations($visit, $validated, $productsByReview, $principalNames, $salesUserId);
            $this->calendarSync->syncVisit($visit);

            $newSnapshot = [
                'title' => $visit->title,
                'visit_date' => $visit->visit_date?->format('Y-m-d'),
                'start_time' => $visit->start_time ? substr((string) $visit->start_time, 0, 5) : null,
                'status' => $visit->status,
            ];

            AuditLog::create([
                'user_id' => $actor?->id,
                'action' => $oldSnapshot['status'] !== $newSnapshot['status'] ? 'STATUS_CHANGE' : 'UPDATE',
                'auditable_type' => Visit::class,
                'auditable_id' => $visit->id,
                'old_values' => $oldSnapshot,
                'new_values' => $newSnapshot,
                'ip_address' => $ipAddress,
            ]);

            return $visit;
        });
    }

    public function rescheduleVisit(Visit $visit, string $newDate, ?string $newTime, ?User $actor, ?string $ipAddress): void
    {
        $oldDate = $visit->visit_date ? $visit->visit_date->format('Y-m-d') : null;
        $oldTime = $visit->start_time ? substr((string) $visit->start_time, 0, 5) : null;

        DB::transaction(function () use ($visit, $oldDate, $newDate, $oldTime, $newTime, $actor, $ipAddress) {
            $visit->update([
                'visit_date' => $newDate,
                'start_time' => $newTime,
                'updated_by' => $actor?->id,
            ]);

            $this->calendarSync->syncVisit($visit);

            AuditLog::create([
                'user_id' => $actor?->id,
                'action' => 'RESCHEDULE',
                'auditable_type' => Visit::class,
                'auditable_id' => $visit->id,
                'old_values' => ['visit_date' => $oldDate, 'start_time' => $oldTime],
                'new_values' => ['visit_date' => $newDate, 'start_time' => $newTime],
                'ip_address' => $ipAddress,
            ]);
        });
    }

    public function updateStatus(Visit $visit, string $newStatus, ?User $actor, ?string $ipAddress): void
    {
        $oldStatus = $visit->status;

        DB::transaction(function () use ($visit, $oldStatus, $newStatus, $actor, $ipAddress) {
            $visit->update([
                'status' => $newStatus,
                'report_completed_at' => $newStatus === 'Completed'
                    ? ($visit->report_completed_at ?? now())
                    : null,
                'updated_by' => $actor?->id,
            ]);

            $this->calendarSync->syncVisit($visit);

            AuditLog::create([
                'user_id' => $actor?->id,
                'action' => 'STATUS_CHANGE',
                'auditable_type' => Visit::class,
                'auditable_id' => $visit->id,
                'old_values' => ['status' => $oldStatus],
                'new_values' => ['status' => $newStatus],
                'ip_address' => $ipAddress,
            ]);
        });
    }

    public function toggleActionPlan(ActionPlan $actionPlan, ?User $actor, ?string $ipAddress): string
    {
        $isCompleting = $actionPlan->status !== 'Completed';
        $oldStatus = $actionPlan->status;
        $newStatus = $isCompleting ? 'Completed' : 'Open';

        DB::transaction(function () use ($actionPlan, $isCompleting, $oldStatus, $newStatus, $actor, $ipAddress) {
            $actionPlan->update([
                'status' => $newStatus,
                'completed_at' => $isCompleting ? now() : null,
            ]);

            if ($actionPlan->visit_id) {
                AuditLog::create([
                    'user_id' => $actor?->id,
                    'action' => 'ACTION_PLAN_TOGGLE',
                    'auditable_type' => Visit::class,
                    'auditable_id' => $actionPlan->visit_id,
                    'old_values' => ['task' => $actionPlan->task_description, 'status' => $oldStatus],
                    'new_values' => ['task' => $actionPlan->task_description, 'status' => $newStatus],
                    'ip_address' => $ipAddress,
                ]);
            }
        });

        return $newStatus;
    }

    private function normalizeInitialList(array $rawList): array
    {
        return collect($rawList)
            ->map(fn ($item) => strtoupper(trim((string) $item)))
            ->filter(fn ($item) => $item !== '' && $item !== 'NONE')
            ->unique()
            ->values()
            ->all();
    }

    private function resolveCustomerPic(Customer $customer, $customerPicId): ?CustomerPic
    {
        if (empty($customerPicId)) {
            return null;
        }

        $pic = $customer->pics()
            ->whereKey($customerPicId)
            ->first();

        if (! $pic) {
            throw ValidationException::withMessages([
                'customer_pic_id' => 'PIC Customer tidak terdaftar pada Customer yang dipilih.',
            ]);
        }

        return $pic;
    }

    private function resolveSalesUserId(Customer $customer): ?int
    {
        $salesInitial = strtoupper(trim((string) $customer->sales_initial));

        if ($salesInitial === '') {
            return null;
        }

        $salesUser = User::where('is_active', true)
            ->whereRaw('UPPER(TRIM(initial)) = ?', [$salesInitial])
            ->select('id')
            ->first();

        if (! $salesUser) {
            throw ValidationException::withMessages([
                'customer_id' => "PIC Sales dengan initial {$salesInitial} tidak ditemukan atau tidak aktif.",
            ]);
        }

        return $salesUser->id;
    }

    private function validateAndLoadReviews(array $principalReviews): array
    {
        $productsByReview = [];
        $principalNames = [];

        foreach ($principalReviews as $index => $revData) {
            $principal = Principal::where('is_active', true)
                ->find($revData['principal_id']);

            if (! $principal) {
                throw ValidationException::withMessages([
                    'principal_reviews' => 'Principal tidak ditemukan atau sudah tidak aktif.',
                ]);
            }

            $principalNames[$index] = $principal->name;

            $productIds = collect($revData['products'] ?? [])
                ->pluck('product_id')
                ->unique()
                ->values();

            $products = collect();

            if ($productIds->isNotEmpty()) {
                $products = Product::query()
                    ->whereIn('id', $productIds)
                    ->where('principal_id', $principal->id)
                    ->where('is_active', true)
                    ->get()
                    ->keyBy('id');

                if ($products->count() !== $productIds->count()) {
                    throw ValidationException::withMessages([
                        'principal_reviews' => 'Salah satu produk tidak sesuai dengan Principal yang dipilih atau sudah tidak aktif.',
                    ]);
                }
            }

            $productsByReview[$index] = $products;
        }

        return [$productsByReview, $principalNames];
    }

    private function syncVisitRelations(
        Visit $visit,
        array $validated,
        array $productsByReview,
        array $principalNames,
        ?int $salesUserId
    ): void {
        foreach ($validated['principal_reviews'] as $index => $revData) {
            $rev = $visit->principalReviews()->create([
                'principal_id' => $revData['principal_id'],
                'principal_name_snapshot' => $principalNames[$index],
                'exposure_type' => $revData['exposure_type'] ?? null,
                'opportunity_status' => $revData['opportunity_status'] ?? null,
                'notes' => $revData['notes'] ?? null,
            ]);

            foreach ($revData['products'] ?? [] as $prod) {
                $product = $productsByReview[$index]->get($prod['product_id']);

                $rev->products()->create([
                    'product_id' => $product->id,
                    'product_catalogue' => $product->catalogue,
                    'product_description_snapshot' => $product->description ?? $product->name,
                ]);
            }
        }

        if (! empty($validated['action_plans'])) {
            foreach ($validated['action_plans'] as $actData) {
                $visit->actionPlans()->create([
                    'task_description' => $actData['task_description'],
                    'owner_user_id' => $actData['owner_user_id'] ?? $salesUserId,
                    'due_date' => $actData['due_date'] ?? null,
                    'priority' => $actData['priority'] ?? 'Normal',
                    'status' => 'Open',
                ]);
            }
        }
    }
}