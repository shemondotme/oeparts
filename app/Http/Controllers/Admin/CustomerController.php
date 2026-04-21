<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $query = User::withTrashed()->latest();

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('email', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->filled('is_active') && $request->is_active !== 'all') {
            $query->where('is_active', $request->is_active === 'active');
        }

        $customers = $query->paginate(30)->withQueryString();

        return view('admin.customers.index', [
            'customers' => $customers,
        ]);
    }

    public function show(User $user)
    {
        $user->load(['orders' => fn($q) => $q->latest()->limit(10)]);

        return view('admin.customers.show', ['customer' => $user]);
    }

    public function toggleActive(Request $request, User $user)
    {
        $user->update(['is_active' => !$user->is_active]);

        return back()->with('success', 'Customer status updated.');
    }
}
