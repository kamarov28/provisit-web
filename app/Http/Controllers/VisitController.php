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
            'actionPlans',
        ])
        ->where('is_archived', false)
        ->orderBy('visit_date', 'desc')
        ->orderBy('start_time', 'asc');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('team')) {
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

        $principals = Principal::where('is_active', true)->select('id', 'name')->get();
        $customers = Customer::where('is_active', true)->with('pics')->get();
        $salesUsers = User::where('is_active', true)->whereIn('role', ['sales', 'admin'])->select('id', 'name', 'email', 'team')->get();

        return Inertia::render('Visits/Index', [
            'visits' => $visits,
            'stats' => $stats,
            'principals' => $principals,
            'customers' => $customers,
            'salesUsers' => $salesUsers,
            'filters' => $request->only(['status', 'team', 'search']),
        ]);
    }
}
