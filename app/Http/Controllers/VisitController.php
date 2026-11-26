<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpsertVisitRequest;
use App\Models\ActionPlan;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\CustomerPic;
use App\Models\Principal;
use App\Models\Product;
use App\Models\User;
use App\Models\Visit;
use App\Services\CalendarSyncService;
use App\Services\VisitMutationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class VisitController extends Controller
{
    public function __construct(
        private CalendarSyncService $calendarSync,
        private VisitMutationService $visitMutation
    ) {}

    public function index(Request $request)
    {
        Gate::authorize('viewAny', Visit::class);

        $user = $request->user();
        $canViewAll = in_array(strtolower((string) $user->role), ['admin', 'manager'], true)
            || strcasecmp((string) $user->email, 'marketing@prolabios.com') === 0
            || (bool) $user->can_view_all_visits;

        $query = Visit::with([
            'customer:id,name,customer_code,city,sector',
            'customerPic:id,name,position,phone',
            'primarySalesUser:id,name,email,team,initial',
            'creator:id,name,initial',
            'principalReviews.products',
            'actionPlans.owner:id,name',
            'auditLogs.user:id,name,initial',
        ])
            ->where('is_archived', false)
            ->orderBy('visit_date', 'desc')
            ->orderBy('start_time', 'asc');

        $this->applyVisibilityScope($query, $user, $canViewAll);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('team') && $request->team !== 'ALL') {
            $query->whereHas('primarySalesUser', function ($q) use ($request) {
                $q->where('team', $request->team);
            });
        }

        $visits = $query->get();

        $statsQuery = Visit::where('is_archived', false);
        $this->applyVisibilityScope($statsQuery, $user, $canViewAll);

        $statusCounts = $statsQuery
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $stats = [
            'total' => (int) $statusCounts->sum(),
            'planned' => (int) ($statusCounts['Planned'] ?? 0),
            'confirmed' => (int) ($statusCounts['Confirmed'] ?? 0),
            'completed' => (int) ($statusCounts['Completed'] ?? 0),
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

    public function calendarIcs()
    {
        $visits = Visit::with(['primarySalesUser:id,name', 'principalReviews'])
            ->where('is_archived', false)
            ->where('status', '!=', 'Cancelled')
            ->orderBy('visit_date', 'asc')
            ->get();

        $icsContent = $this->calendarSync->buildIcsFeed($visits, 'PROvisit - Jadwal Kunjungan Sales');

        return response($icsContent, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="provisit-calendar.ics"',
        ]);
    }

    public function visitIcs(Visit $visit)
    {
        Gate::authorize('view', $visit);

        $visit->load(['primarySalesUser:id,name', 'principalReviews']);
        $icsContent = $this->calendarSync->buildIcsFeed([$visit], "PROvisit - {$visit->visit_code}");

        return response($icsContent, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$visit->visit_code}.ics\"",
        ]);
    }

    public function productSearch(Request $request)
    {
        $validated = $request->validate([
            'principal_id' => 'required|integer|exists:principals,id',
            'q' => 'required|string|min:2|max:100',
        ]);

        $term = addcslashes(trim($validated['q']), '%_\\');

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

    public function store(UpsertVisitRequest $request)
    {
        Gate::authorize('create', Visit::class);

        $visit = $this->visitMutation->createVisit(
            $request->validated(),
            $request->user(),
            $request->ip()
        );

        return redirect()
            ->route('visits.index')
            ->with('success', "Visit {$visit->visit_code} berhasil disimpan.");
    }

    public function update(UpsertVisitRequest $request, Visit $visit)
    {
        Gate::authorize('update', $visit);

        $this->visitMutation->updateVisit(
            $visit,
            $request->validated(),
            $request->user(),
            $request->ip()
        );

        return redirect()
            ->route('visits.index')
            ->with('success', "Visit {$visit->visit_code} berhasil diperbarui.");
    }

    public function reschedule(Request $request, Visit $visit)
    {
        Gate::authorize('update', $visit);

        if (in_array($visit->status, ['Completed', 'Cancelled'], true)) {
            return back()->withErrors([
                'visit_date' => "Visit dengan status {$visit->status} tidak dapat dijadwalkan ulang.",
            ]);
        }

        $validated = $request->validate([
            'visit_date' => 'required|date',
            'start_time' => 'nullable|string|max:8',
        ]);

        $oldDate = $visit->visit_date ? $visit->visit_date->format('Y-m-d') : null;
        $newDate = date('Y-m-d', strtotime($validated['visit_date']));

        $oldTime = $visit->start_time ? substr((string) $visit->start_time, 0, 5) : null;
        $newTime = array_key_exists('start_time', $validated) && $validated['start_time'] !== null
            ? substr(trim((string) $validated['start_time']), 0, 5)
            : $oldTime;

        if ($oldDate === $newDate && $oldTime === $newTime) {
            return redirect()->route('visits.index');
        }

        $this->visitMutation->rescheduleVisit(
            $visit,
            $newDate,
            $newTime,
            $request->user(),
            $request->ip()
        );

        return redirect()
            ->route('visits.index')
            ->with('success', "Jadwal {$visit->visit_code} diperbarui ke {$newDate}".($newTime ? " ({$newTime})" : '').'.');
    }

    public function updateStatus(Request $request, Visit $visit)
    {
        Gate::authorize('update', $visit);

        $validated = $request->validate([
            'status' => [
                'required',
                Rule::in(['Draft', 'Planned', 'Confirmed', 'Completed', 'Cancelled']),
            ],
        ]);

        $oldStatus = $visit->status;
        $newStatus = $validated['status'];

        if ($oldStatus === $newStatus) {
            return redirect()->route('visits.index');
        }

        if ($newStatus === 'Completed') {
            $hasResult = trim((string) $visit->postvisit_result) !== '';
            $hasActionPlan = $visit->actionPlans()->exists();

            if (! $hasResult || ! $hasActionPlan) {
                return back()->withErrors([
                    'status' => 'Untuk mengubah status ke Completed, lengkapi Post-visit Result dan Action Plan terlebih dahulu.',
                ]);
            }
        }

        $this->visitMutation->updateStatus(
            $visit,
            $newStatus,
            $request->user(),
            $request->ip()
        );

        return redirect()
            ->route('visits.index')
            ->with('success', "Status {$visit->visit_code} diubah menjadi {$newStatus}.");
    }

    public function toggleActionPlan(Request $request, ActionPlan $actionPlan)
    {
        Gate::authorize('update', $actionPlan->visit);

        $newStatus = $this->visitMutation->toggleActionPlan(
            $actionPlan,
            $request->user(),
            $request->ip()
        );

        return redirect()
            ->route('visits.index')
            ->with('success', "Action Plan ditandai {$newStatus}.");
    }

    private function applyVisibilityScope($query, User $user, bool $canViewAll): void
    {
        if ($canViewAll) {
            return;
        }

        $userInitial = strtoupper(trim((string) $user->initial));
        $query->where(function ($q) use ($user, $userInitial) {
            $q->where('primary_sales_user_id', $user->id)
                ->orWhere('created_by', $user->id);

            if ($userInitial !== '') {
                $q->orWhereJsonContains('support_marketing', $userInitial)
                    ->orWhereJsonContains('companion_sales', $userInitial);
            }
        });
    }
}