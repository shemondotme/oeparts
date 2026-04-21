@extends('layouts.admin')

@section('title', 'Inquiry #' . $inquiry->id)

@section('content')
<div class="px-6 py-8">
    {{-- Header --}}
    <div class="flex items-center justify-between mb-8">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.cms.inquiries.index') }}" class="text-gray-500 hover:text-gray-700">
                <x-heroicon-o-arrow-left class="w-5 h-5" />
            </a>
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Part Inquiry #{{ $inquiry->id }}</h1>
                <p class="text-gray-600 mt-1">Submitted {{ $inquiry->created_at->format('F j, Y \a\t H:i') }}</p>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                @if($inquiry->status->value === 'new') bg-blue-100 text-blue-800
                @elseif($inquiry->status->value === 'reviewing') bg-yellow-100 text-yellow-800
                @elseif($inquiry->status->value === 'sourced') bg-green-100 text-green-800
                @else bg-gray-100 text-gray-800
                @endif">
                {{ ucfirst($inquiry->status->value) }}
            </span>
            <form action="{{ route('admin.cms.inquiries.destroy', $inquiry) }}" method="POST"
                  onsubmit="return confirm('Delete this inquiry? This cannot be undone.');">
                @csrf
                @method('DELETE')
                <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 border border-red-300 rounded-lg text-sm font-medium text-red-700 bg-white hover:bg-red-50">
                    <x-heroicon-o-trash class="w-4 h-4" />
                    Delete
                </button>
            </form>
        </div>
    </div>

    {{-- Flash Message --}}
    @if(session('success'))
        <div class="mb-6 flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">
            <x-heroicon-o-check-circle class="w-5 h-5 shrink-0" />
            <span class="text-sm">{{ session('success') }}</span>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        {{-- Left: Inquiry Details --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Part Information --}}
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Part Information</h2>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">OEM Number</dt>
                        <dd class="mt-1 text-sm font-mono font-semibold text-gray-900">{{ $inquiry->oem_number }}</dd>
                    </div>
                    @if($inquiry->manufacturer)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Manufacturer</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $inquiry->manufacturer }}</dd>
                        </div>
                    @endif
                    @if($inquiry->car_model)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Car Model</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $inquiry->car_model }}</dd>
                        </div>
                    @endif
                    @if($inquiry->year)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Year</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $inquiry->year }}</dd>
                        </div>
                    @endif
                    @if($inquiry->vin_number)
                        <div class="sm:col-span-2">
                            <dt class="text-sm font-medium text-gray-500">VIN Number</dt>
                            <dd class="mt-1 text-sm font-mono text-gray-900">{{ $inquiry->vin_number }}</dd>
                        </div>
                    @endif
                </dl>
            </div>

            {{-- Customer Notes --}}
            @if($inquiry->notes)
                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-3">Customer Notes</h2>
                    <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ $inquiry->notes }}</p>
                </div>
            @endif

            {{-- Reply Form --}}
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Reply to Customer</h2>
                <form method="POST" action="{{ route('admin.cms.inquiries.add-reply', $inquiry) }}">
                    @csrf
                    @if($errors->any())
                        <div class="mb-4 bg-red-50 border border-red-200 rounded-lg p-4">
                            <ul class="text-sm text-red-700 space-y-1">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    <div class="space-y-4">
                        <div>
                            <label for="message" class="block text-sm font-medium text-gray-700 mb-1">
                                Message <span class="text-red-500">*</span>
                            </label>
                            <textarea id="message" name="message" rows="5" required
                                      class="w-full rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500"
                                      placeholder="Type your reply to the customer...">{{ old('message') }}</textarea>
                            @error('message')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="checkbox" id="send_email" name="send_email" value="1"
                                   class="rounded border-gray-300 text-[#0B3A68] focus:ring-[#0B3A68]" checked>
                            <label for="send_email" class="text-sm text-gray-700">Send reply via email to {{ $inquiry->email }}</label>
                        </div>
                        <button type="submit"
                                class="inline-flex items-center gap-2 px-4 py-2 bg-[#0B3A68] text-white rounded-lg text-sm font-medium hover:bg-blue-900">
                            <x-heroicon-o-paper-airplane class="w-4 h-4" />
                            Send Reply
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Right: Actions & Meta --}}
        <div class="space-y-6">
            {{-- Update Status --}}
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Update Status</h2>
                <form method="POST" action="{{ route('admin.cms.inquiries.update-status', $inquiry) }}">
                    @csrf
                    <div class="space-y-4">
                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <select id="status" name="status" required
                                    class="w-full rounded-lg border-gray-300 text-sm">
                                @foreach(\App\Enums\PartInquiryStatus::cases() as $status)
                                    <option value="{{ $status->value }}" {{ $inquiry->status->value === $status->value ? 'selected' : '' }}>
                                        {{ ucfirst($status->value) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="admin_note" class="block text-sm font-medium text-gray-700 mb-1">Admin Note</label>
                            <textarea id="admin_note" name="admin_note" rows="3"
                                      class="w-full rounded-lg border-gray-300 text-sm"
                                      placeholder="Internal note about this inquiry...">{{ old('admin_note', $inquiry->admin_note) }}</textarea>
                        </div>
                        <button type="submit"
                                class="w-full py-2.5 bg-[#0B3A68] text-white rounded-lg text-sm font-medium hover:bg-blue-900">
                            Save Status
                        </button>
                    </div>
                </form>
            </div>

            {{-- Customer Info --}}
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Customer</h2>
                <dl class="space-y-3">
                    <div>
                        <dt class="text-xs font-medium text-gray-500 uppercase">Email</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            <a href="mailto:{{ $inquiry->email }}" class="text-[#0B3A68] hover:underline">{{ $inquiry->email }}</a>
                        </dd>
                    </div>
                    @if($inquiry->ip_address)
                        <div>
                            <dt class="text-xs font-medium text-gray-500 uppercase">IP Address</dt>
                            <dd class="mt-1 text-sm text-gray-900 font-mono">{{ $inquiry->ip_address }}</dd>
                        </div>
                    @endif
                    <div>
                        <dt class="text-xs font-medium text-gray-500 uppercase">Submitted</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $inquiry->created_at->format('M d, Y H:i') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-gray-500 uppercase">Last Updated</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $inquiry->updated_at->diffForHumans() }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>
</div>
@endsection
