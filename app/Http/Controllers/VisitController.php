<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Principal;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Http\Request;
use Inertia\Inertia;

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
            ->select('id', 'customer_code', 'name', 'sector')
            ->orderBy('name')
            ->get();

        $salesUsers = User::where('is_active', true)
            ->whereIn('role', ['sales', 'admin', 'manager'])
            ->select('id', 'name', 'email', 'team')
            ->orderBy('name')
            ->get();

        return Inertia::render('Visits/Index', [
            'visits' => $visits,
            'stats' => $stats,
            'principals' => $principals,
            'customers' => $customers,
            'salesUsers' => $salesUsers,
            'filters' => $request->only(['status', 'team', 'search']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'visit_date' => 'required|date',
            'start_time' => 'required|string',
            'visit_type' => 'required|string',
            'visit_purpose' => 'nullable|string',
            'customer_id' => 'required|exists:customers,id',
            'customer_pic_id' => 'nullable|exists:customer_pics,id',
            'primary_sales_user_id' => 'nullable|exists:users,id',
            'agenda' => 'nullable|string',
            'previsit_notes' => 'nullable|string',
            'status' => 'required|string',
            'postvisit_result' => 'nullable|string',
            'principal_reviews' => 'required|array|min:1',
            'principal_reviews.*.principal_id' => 'nullable|exists:principals,id',
            'principal_reviews.*.principal_name' => 'required|string',
            'principal_reviews.*.exposure_type' => 'nullable|string',
            'principal_reviews.*.opportunity_status' => 'nullable|string',
            'principal_reviews.*.products' => 'nullable|array',
            'action_plans' => 'nullable|array',
            'action_plans.*.task_description' => 'required|string',
            'action_plans.*.owner_user_id' => 'nullable|exists:users,id',
            'action_plans.*.due_date' => 'nullable|date',
            'action_plans.*.priority' => 'nullable|string',
        ]);

        if ($validated['status'] === 'Completed' && empty($validated['action_plans'])) {
            return back()->withErrors(['action_plans' => 'Action Plan wajib diisi jika status kunjungan Completed.']);
        }

        $customer = Customer::findOrFail($validated['customer_id']);
        $pic = !empty($validated['customer_pic_id']) ? CustomerPic::find($validated['customer_pic_id']) : null;

        $countThisYear = Visit::whereYear('created_at', now()->year)->count() + 1;
        $visitCode = 'VST-' . now()->year . '-' . str_pad($countThisYear, 6, '0', STR_PAD_LEFT);

        $salesUserId = $validated['primary_sales_user_id'] ?? User::where('role', 'sales')->value('id');

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
            'visit_type' => $validated['visit_type'],
            'visit_purpose' => $validated['visit_purpose'] ?? 'Product Introduction / Presentation',
            'agenda' => $validated['agenda'] ?? null,
            'previsit_notes' => $validated['previsit_notes'] ?? null,
            'postvisit_result' => $validated['postvisit_result'] ?? null,
            'status' => $validated['status'],
            'report_completed_at' => $validated['status'] === 'Completed' ? now() : null,
        ]);

        foreach ($validated['principal_reviews'] as $revData) {
            $pId = $revData['principal_id'] ?? null;
            if (!$pId && !empty($revData['principal_name'])) {
                $p = Principal::firstOrCreate(['name' => $revData['principal_name']], ['is_active' => true]);
                $pId = $p->id;
            }

            $rev = $visit->principalReviews()->create([
                'principal_id' => $pId,
                'principal_name_snapshot' => $revData['principal_name'],
                'exposure_type' => $revData['exposure_type'] ?? null,
                'opportunity_status' => $revData['opportunity_status'] ?? null,
            ]);

            if (!empty($revData['products'])) {
                foreach ($revData['products'] as $prod) {
                    $rev->products()->create([
                        'product_catalogue' => $prod['catalogue'] ?? '-',
                        'product_description_snapshot' => $prod['description'] ?? '-',
                    ]);
                }
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

        return redirect()->route('visits.index')->with('success', "Visit {$visitCode} berhasil disimpan.");
    }
}
