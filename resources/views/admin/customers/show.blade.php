@extends('layouts.admin')

@section('title', 'Customer: ' . $customer->name)

@section('content')
<div class="px-6 py-8 max-w-3xl">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900">{{ $customer->name }}</h1>
        <form action="{{ route('admin.customers.toggle-active', $customer) }}" method="POST">
            @csrf
            @method('PATCH')
            <button type="submit"
                    class="inline-flex items-center gap-2 px-4 py-2 border rounded-lg text-sm font-medium
                           {{ $customer->is_active ? 'border-red-300 text-red-700 hover:bg-red-50' : 'border-green-300 text-green-700 hover:bg-green-50' }}">
                {{ $customer->is_active ? 'Deactivate' : 'Activate' }}
            </button>
        </form>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100 mb-6">
        @foreach([
            ['Name', $customer->name],
            ['Email', $customer->email],
            ['Phone', $customer->phone ?? '—'],
            ['Registered', $customer->created_at->format('M d, Y H:i')],
            ['Email Verified', $customer->email_verified_at ? $customer->email_verified_at->format('M d, Y') : 'Not verified'],
        ] as [$label, $value])
        <div class="flex px-6 py-4">
            <span class="w-40 text-sm font-medium text-gray-500">{{ $label }}</span>
            <span class="text-sm text-gray-900">{{ $value }}</span>
        </div>
        @endforeach
        <div class="flex px-6 py-4">
            <span class="w-40 text-sm font-medium text-gray-500">Status</span>
            @if($customer->is_active)
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Active</span>
            @else
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500">Inactive</span>
            @endif
        </div>
    </div>

    {{-- Recent Orders --}}
    <h2 class="text-lg font-semibold text-gray-900 mb-4">Recent Orders</h2>
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden mb-6">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Order</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($customer->orders as $order)
                    <tr>
                        <td class="px-6 py-3 text-sm font-mono text-[#0B3A68]">
                            <a href="{{ route('admin.orders.show', $order) }}">{{ $order->order_number }}</a>
                        </td>
                        <td class="px-6 py-3 text-sm text-gray-900">€{{ number_format($order->grand_total, 2) }}</td>
                        <td class="px-6 py-3 text-sm text-gray-500">{{ $order->status->value }}</td>
                        <td class="px-6 py-3 text-sm text-gray-500">{{ $order->created_at->format('M d, Y') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-6 py-6 text-center text-sm text-gray-400">No orders yet</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <a href="{{ route('admin.customers.index') }}" class="text-sm text-[#0B3A68] hover:underline">
        ← Back to Customers
    </a>
</div>
@endsection
