<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerPic;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MasterDataController extends Controller
{
    public function storeCustomer(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'customer_code' => 'nullable|string|max:50|unique:customers,customer_code',
            'sales_initial' => 'required|string|max:20',
            'sector' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:50',
        ]);

        $code = trim((string) ($validated['customer_code'] ?? ''));
        if ($code === '') {
            $nextNum = Customer::count() + 1;
            $code = 'CUST-'.str_pad((string) $nextNum, 5, '0', STR_PAD_LEFT);
        }

        $customer = Customer::create([
            'customer_code' => strtoupper($code),
            'name' => trim($validated['name']),
            'sales_initial' => strtoupper(trim($validated['sales_initial'])),
            'sector' => $validated['sector'] ?? null,
            'city' => $validated['city'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'is_active' => true,
        ]);

        return redirect()
            ->route('visits.index')
            ->with('success', "Customer {$customer->name} berhasil ditambahkan.");
    }

    public function storeCustomerPic(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|integer|exists:customers,id',
            'name' => 'required|string|max:255',
            'position' => 'nullable|string|max:150',
            'department' => 'nullable|string|max:150',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:150',
        ]);

        $pic = CustomerPic::create([
            'customer_id' => $validated['customer_id'],
            'name' => trim($validated['name']),
            'position' => $validated['position'] ?? null,
            'department' => $validated['department'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'is_primary' => false,
        ]);

        return redirect()
            ->route('visits.index')
            ->with('success', "PIC {$pic->name} berhasil ditambahkan.");
    }

    public function storeProduct(Request $request)
    {
        $validated = $request->validate([
            'principal_id' => [
                'required',
                'integer',
                Rule::exists('principals', 'id')->where('is_active', true),
            ],
            'catalogue' => 'required|string|max:100',
            'description' => 'required|string|max:255',
            'category' => 'nullable|string|max:100',
            'unit' => 'nullable|string|max:50',
        ]);

        $product = Product::create([
            'principal_id' => $validated['principal_id'],
            'catalogue' => strtoupper(trim($validated['catalogue'])),
            'name' => trim($validated['description']),
            'description' => trim($validated['description']),
            'category' => $validated['category'] ?? null,
            'unit' => $validated['unit'] ?? null,
            'is_active' => true,
        ]);

        return redirect()
            ->route('visits.index')
            ->with('success', "Produk {$product->catalogue} berhasil ditambahkan.");
    }
}
