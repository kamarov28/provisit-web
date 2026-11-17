<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerPic;
use App\Models\Principal;
use App\Models\Product;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;


class VisitController extends Controller
{
    public function index(Request $request)
    {
        $query = Visit::with([
            'customer:id,name,customer_code,city,sector',
            'customerPic:id,name,position,phone',
            'primarySalesUser:id,name,email,team',
            'principalReviews.products',
            'actionPlans.owner:id,name',
        ])
        ->where('is_archived', false)
        ->orderBy('visit_date', 'desc')
        ->orderBy('start_time', 'asc');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('team') && $request->team !== 'ALL') {
            $query->whereHas('primarySalesUser', function ($q) use ($request) {
                $q->where('team', $request->team);
            });
        }

        $visits = $query->get();

        $stats = [
            'total' => Visit::where('is_archived', false)->count(),
            'planned' => Visit::where('is_archived', false)->where('status', 'Planned')->count(),
            'confirmed' => Visit::where('is_archived', false)->where('status', 'Confirmed')->count(),
            'completed' => Visit::where('is_archived', false)->where('status', 'Completed')->count(),
        ];

        $principals = Principal::where('is_active', true)->select('id', 'name')->orderBy('name')->get();
        $customers = Customer::where('is_active', true)
            ->with(['pics' => function ($q) {
                $q->select('id', 'customer_id', 'name', 'position', 'phone', 'email');
            }])
            ->select('id', 'customer_code', 'name', 'sector', 'sales_initial')
            ->orderBy('name')
            ->get();

        $salesUsers = User::where('is_active', true)
            ->whereIn('role', [
                'sales',
                'admin',
                'manager',
                'management',
                'product_specialist',
                'sales_coordinator',
            ])
            ->select('id', 'name', 'email', 'team', 'initial', 'role')
            ->orderBy('name')
            ->get();

        $supportMarketingUsers = User::where('is_active', true)
            ->whereNotNull('initial')
            ->where('initial', '!=', '')
            ->where('role', '!=', 'sales')
            ->select('id', 'initial', 'name', 'role')
            ->orderBy('id')
            ->get();

        return Inertia::render('Visits/Index', [
            'visits' => $visits,
            'stats' => $stats,
            'principals' => $principals,
            'customers' => $customers,
            'salesUsers' => $salesUsers,
            'supportMarketingUsers' => $supportMarketingUsers,
            'filters' => $request->only(['status', 'team', 'search']),
        ]);
    }


    public function productSearch(Request $request)
    {
        $validated = $request->validate([
            'principal_id' => 'required|integer|exists:principals,id',
            'q' => 'required|string|min:2|max:100',
        ]);

        $term = trim($validated['q']);

        $products = Product::query()
            ->where('principal_id', $validated['principal_id'])
            ->where('is_active', true)
            ->where(function ($query) use ($term) {
                $query->where('catalogue', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%");
            })
            ->orderBy('catalogue')
            ->limit(20)
            ->get(['id', 'principal_id', 'catalogue', 'name', 'description']);

        return response()->json($products);
    }

    public function store(Request $request)
    {
        $validated = $this->validateVisitPayload($request, false);

        if ($validated['status'] === 'Completed') {
            if (trim((string) ($validated['postvisit_result'] ?? '')) === '') {
                return back()->withErrors([
                    'postvisit_result' => 'Post-visit Result wajib dilengkapi jika status kunjungan Completed.',
                ]);
            }

            if (empty($validated['action_plans'])) {
                return back()->withErrors([
                    'action_plans' => 'Action Plan wajib diisi jika status kunjungan Completed.',
                ]);
            }
        }

        $customer = Customer::findOrFail($validated['customer_id']);
        $pic = $this->resolveCustomerPic($customer, $validated['customer_pic_id'] ?? null);
        $salesUserId = $this->resolveSalesUserId($customer);
        $supportMarketing = $this->normalizeSupportMarketing($validated['support_marketing'] ?? []);

        $visit = DB::transaction(function () use (
            $validated,
            $customer,
            $pic,
            $salesUserId,
            $supportMarketing
        ) {
            [$productsByReview, $principalNames] = $this->validateAndLoadReviews($validated['principal_reviews']);

            $countThisYear = Visit::whereYear('created_at', now()->year)->count() + 1;

            $visitCode = 'VST-' . now()->year . '-' .
                str_pad($countThisYear, 6, '0', STR_PAD_LEFT);

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
                'visit_type' => $validated['visit_type'],
                'visit_purpose' => $validated['visit_purpose'] ?? 'Product Introduction / Presentation',
                'agenda' => $validated['agenda'] ?? null,
                'previsit_notes' => $validated['previsit_notes'] ?? null,
                'postvisit_result' => $validated['postvisit_result'] ?? null,
                'status' => $validated['status'],
                'report_completed_at' => $validated['status'] === 'Completed' ? now() : null,
            ]);

            $this->syncVisitRelations($visit, $validated, $productsByReview, $principalNames, $salesUserId);

            return $visit;
        });

        return redirect()
            ->route('visits.index')
            ->with('success', "Visit {$visit->visit_code} berhasil disimpan.");
    }

    public function update(Request $request, Visit $visit)
    {
        $validated = $this->validateVisitPayload($request, true);

        if ($validated['status'] === 'Completed') {
            if (trim((string) ($validated['postvisit_result'] ?? '')) === '') {
                return back()->withErrors([
                    'postvisit_result' => 'Post-visit Result wajib dilengkapi jika status kunjungan Completed.',
                ]);
            }

            if (empty($validated['action_plans'])) {
                return back()->withErrors([
                    'action_plans' => 'Action Plan wajib diisi jika status kunjungan Completed.',
                ]);
            }
        }

        $customer = Customer::findOrFail($validated['customer_id']);
        $pic = $this->resolveCustomerPic($customer, $validated['customer_pic_id'] ?? null);
        $salesUserId = $this->resolveSalesUserId($customer);
        $supportMarketing = $this->normalizeSupportMarketing($validated['support_marketing'] ?? []);

        DB::transaction(function () use (
            $visit,
            $validated,
            $customer,
            $pic,
            $salesUserId,
            $supportMarketing
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
                'visit_type' => $validated['visit_type'],
                'visit_purpose' => $validated['visit_purpose'] ?? 'Product Introduction / Presentation',
                'agenda' => $validated['agenda'] ?? null,
                'previsit_notes' => $validated['previsit_notes'] ?? null,
                'postvisit_result' => $validated['postvisit_result'] ?? null,
                'status' => $validated['status'],
                'report_completed_at' => $completedAt,
            ]);

            $visit->principalReviews()->each(function ($review) {
                $review->products()->delete();
                $review->delete();
            });
            $visit->actionPlans()->delete();

            $this->syncVisitRelations($visit, $validated, $productsByReview, $principalNames, $salesUserId);
        });

        return redirect()
            ->route('visits.index')
            ->with('success', "Visit {$visit->visit_code} berhasil diperbarui.");
    }

    private function validateVisitPayload(Request $request, bool $isUpdate): array
    {
        $allowedStatuses = $isUpdate
            ? ['Draft', 'Planned', 'Confirmed', 'Completed', 'Cancelled']
            : ['Draft', 'Planned'];

        $allowedSupportInitials = User::where('is_active', true)
            ->whereNotNull('initial')
            ->where('initial', '!=', '')
            ->where('role', '!=', 'sales')
            ->pluck('initial')
            ->map(fn ($init) => strtoupper(trim((string) $init)))
            ->unique()
            ->values()
            ->all();

        return $request->validate([
            'title' => 'required|string|max:255',
            'visit_date' => 'required|date',
            'start_time' => 'required|string',
            'visit_type' => 'required|string',
            'visit_purpose' => 'nullable|string',
            'customer_id' => 'required|exists:customers,id',
            'customer_pic_id' => 'nullable|exists:customer_pics,id',
            'support_marketing' => 'nullable|array',
            'support_marketing.*' => [
                'string',
                Rule::in($allowedSupportInitials),
            ],
            'agenda' => 'nullable|string',
            'previsit_notes' => 'nullable|string',
            'status' => [
                'required',
                Rule::in($allowedStatuses),
            ],
            'postvisit_result' => 'nullable|string',

            'principal_reviews' => 'required|array|min:1',
            'principal_reviews.*.principal_id' => [
                'required',
                'integer',
                Rule::exists('principals', 'id')->where('is_active', true),
            ],
            'principal_reviews.*.principal_name' => 'nullable|string',
            'principal_reviews.*.exposure_type' => 'nullable|string',
            'principal_reviews.*.opportunity_status' => 'nullable|string',
            'principal_reviews.*.notes' => 'nullable|string',
            'principal_reviews.*.products' => 'nullable|array',
            'principal_reviews.*.products.*.product_id' => [
                'required',
                'integer',
                'exists:products,id',
            ],

            'action_plans' => 'nullable|array',
            'action_plans.*.task_description' => 'required|string',
            'action_plans.*.owner_user_id' => 'nullable|exists:users,id',
            'action_plans.*.due_date' => 'nullable|date',
            'action_plans.*.priority' => 'nullable|string',
        ]);
    }

    private function normalizeSupportMarketing(array $rawList): array
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

        if (!$pic) {
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

        if (!$salesUser) {
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

            if (!$principal) {
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

        if (!empty($validated['action_plans'])) {
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