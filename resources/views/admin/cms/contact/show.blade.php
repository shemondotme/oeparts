@extends('layouts.admin')

@section('title', 'Message from ' . $message->name)

@section('content')
<div class="px-6 py-8">
    <div class="flex items-center justify-between mb-8">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.cms.contact.index') }}" class="text-gray-500 hover:text-gray-700">
                <x-heroicon-o-arrow-left class="w-5 h-5" />
            </a>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-2xl font-bold text-gray-900">Message from {{ $message->name }}</h1>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                        @if($message->status->value === 'unread') bg-blue-100 text-blue-800
                        @elseif($message->status->value === 'read') bg-yellow-100 text-yellow-800
                        @else bg-green-100 text-green-800
                        @endif">
                        {{ ucfirst($message->status->value) }}
                    </span>
                </div>
                <p class="text-gray-500 text-sm mt-1">{{ $message->created_at->format('F j, Y \a\t H:i') }}</p>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <form action="{{ route('admin.cms.contact.destroy', $message) }}" method="POST"
                  onsubmit="return confirm('Delete this message?');" class="inline">
                @csrf
                @method('DELETE')
                <button type="submit"
                        class="inline-flex items-center gap-2 px-4 py-2 border border-red-300 rounded-lg text-sm font-medium text-red-700 bg-white hover:bg-red-50">
                    <x-heroicon-o-trash class="w-4 h-4" />
                    Delete
                </button>
            </form>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-6 flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">
            <x-heroicon-o-check-circle class="w-5 h-5 shrink-0" />
            <span class="text-sm">{{ session('success') }}</span>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        {{-- Left: Message --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Message Body --}}
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">{{ $message->subject }}</h2>
                        <p class="text-sm text-gray-500 mt-0.5">
                            Subject type: {{ ucwords(str_replace('_', ' ', $message->subject_type->value)) }}
                        </p>
                    </div>
                </div>
                <div class="text-sm text-gray-700 whitespace-pre-wrap bg-gray-50 rounded-lg p-4">{{ $message->message }}</div>

                {{-- Extra fields --}}
                @if($message->order_number || $message->oem_number || $message->vin_number)
                    <div class="mt-4 pt-4 border-t border-gray-100 grid grid-cols-2 gap-3">
                        @if($message->order_number)
                            <div>
                                <p class="text-xs text-gray-500">Order Number</p>
                                <p class="text-sm font-mono font-medium text-gray-900">{{ $message->order_number }}</p>
                            </div>
                        @endif
                        @if($message->oem_number)
                            <div>
                                <p class="text-xs text-gray-500">OEM Number</p>
                                <p class="text-sm font-mono font-medium text-gray-900">{{ $message->oem_number }}</p>
                            </div>
                        @endif
                        @if($message->manufacturer)
                            <div>
                                <p class="text-xs text-gray-500">Manufacturer</p>
                                <p class="text-sm text-gray-900">{{ $message->manufacturer }}</p>
                            </div>
                        @endif
                        @if($message->car_model)
                            <div>
                                <p class="text-xs text-gray-500">Car Model</p>
                                <p class="text-sm text-gray-900">{{ $message->car_model }} {{ $message->year ? '('.$message->year.')' : '' }}</p>
                            </div>
                        @endif
                        @if($message->vin_number)
                            <div>
                                <p class="text-xs text-gray-500">VIN</p>
                                <p class="text-sm font-mono text-gray-900">{{ $message->vin_number }}</p>
                            </div>
                        @endif
                    </div>
                @endif
            </div>

            {{-- Reply Form --}}
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h2 class="text-base font-semibold text-gray-900 mb-4">Reply</h2>
                <form method="POST" action="{{ route('admin.cms.contact.add-reply', $message) }}">
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
                            <label for="reply_message" class="block text-sm font-medium text-gray-700 mb-1">
                                Message <span class="text-red-500">*</span>
                            </label>
                            <textarea id="reply_message" name="message" rows="5" required maxlength="2000"
                                      class="w-full rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500"
                                      placeholder="Write your reply...">{{ old('message') }}</textarea>
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="checkbox" id="send_email" name="send_email" value="1"
                                   class="rounded border-gray-300 text-[#0B3A68] focus:ring-[#0B3A68]" checked>
                            <label for="send_email" class="text-sm text-gray-700">Send reply via email to {{ $message->email }}</label>
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

        {{-- Right Sidebar --}}
        <div class="space-y-6">
            {{-- Update Status --}}
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h2 class="text-base font-semibold text-gray-900 mb-4">Update Status</h2>
                <form method="POST" action="{{ route('admin.cms.contact.update-status', $message) }}">
                    @csrf
                    <div class="space-y-3">
                        <select name="status" required class="w-full rounded-lg border-gray-300 text-sm">
                            @foreach(\App\Enums\ContactStatus::cases() as $status)
                                <option value="{{ $status->value }}"
                                    {{ $message->status->value === $status->value ? 'selected' : '' }}>
                                    {{ ucfirst($status->value) }}
                                </option>
                            @endforeach
                        </select>
                        <button type="submit"
                                class="w-full py-2 bg-[#0B3A68] text-white rounded-lg text-sm font-medium hover:bg-blue-900">
                            Save Status
                        </button>
                    </div>
                </form>
            </div>

            {{-- Sender Info --}}
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h2 class="text-base font-semibold text-gray-900 mb-4">Sender</h2>
                <dl class="space-y-3">
                    <div>
                        <dt class="text-xs font-medium text-gray-500 uppercase">Name</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $message->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-gray-500 uppercase">Email</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            <a href="mailto:{{ $message->email }}" class="text-[#0B3A68] hover:underline">{{ $message->email }}</a>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-gray-500 uppercase">OTP Verified</dt>
                        <dd class="mt-1">
                            @if($message->otp_verified)
                                <span class="inline-flex items-center gap-1 text-xs text-green-700">
                                    <x-heroicon-o-check-circle class="w-3.5 h-3.5" /> Verified
                                </span>
                            @else
                                <span class="text-xs text-gray-500">Not verified</span>
                            @endif
                        </dd>
                    </div>
                    @if($message->ip_address)
                        <div>
                            <dt class="text-xs font-medium text-gray-500 uppercase">IP Address</dt>
                            <dd class="mt-1 text-sm font-mono text-gray-900">{{ $message->ip_address }}</dd>
                        </div>
                    @endif
                    <div>
                        <dt class="text-xs font-medium text-gray-500 uppercase">Received</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $message->created_at->format('M d, Y H:i') }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>
</div>
@endsection
