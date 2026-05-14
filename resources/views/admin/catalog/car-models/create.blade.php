@extends('layouts.admin')

@section('title', 'Create Car Model')
@section('page_title', 'Create Car Model')

@section('header_actions')
    <a href="{{ route('admin.catalog.car-models.index') }}" class="bp-btn-ghost gap-1">
        <x-heroicon-o-arrow-left class="w-4 h-4" />
        Back to Car Models
    </a>
@endsection

@section('content')
<div class="max-w-3xl space-y-6">

    <section class="bp-card overflow-hidden">
        <header class="bp-card-header">
            <p class="bp-spec text-amber-ink">§ Catalog · New Car Model</p>
            <h2 class="mt-1 font-display text-xl font-bold text-ink tracking-[-0.02em]">
                Add Car Model<span class="text-amber">.</span>
            </h2>
        </header>

        <form action="{{ route('admin.catalog.car-models.store') }}" method="POST">
            @csrf
            <div class="divide-y divide-rule">

                {{-- Details --}}
                <div class="p-5 space-y-4">
                    <p class="bp-spec text-ink-muted">§ Details</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                        <div>
                            <label for="manufacturer_id" class="block bp-spec mb-2">§ Manufacturer <span class="text-red-500">*</span></label>
                            <select name="manufacturer_id" id="manufacturer_id" required class="bp-select">
                                <option value="">Select Manufacturer</option>
                                @foreach($manufacturers as $manufacturer)
                                    <option value="{{ $manufacturer->id }}" {{ old('manufacturer_id') == $manufacturer->id ? 'selected' : '' }}>
                                        {{ trans_field($manufacturer->name) }}
                                    </option>
                                @endforeach
                            </select>
                            @error('manufacturer_id')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="name" class="block bp-spec mb-2">§ Model Name <span class="text-red-500">*</span></label>
                            <input type="text" name="name" id="name"
                                   value="{{ old('name') }}" required
                                   placeholder="e.g. Golf VII, E90 3 Series"
                                   class="bp-input w-full">
                            @error('name')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="year_from" class="block bp-spec mb-2">§ Year From</label>
                            <input type="number" name="year_from" id="year_from"
                                   value="{{ old('year_from') }}" min="1900" max="2100"
                                   placeholder="e.g. 2012" class="bp-input w-full">
                            @error('year_from')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="year_to" class="block bp-spec mb-2">§ Year To <span class="text-ink-muted font-normal normal-case">(empty = in production)</span></label>
                            <input type="number" name="year_to" id="year_to"
                                   value="{{ old('year_to') }}" min="1900" max="2100"
                                   placeholder="e.g. 2020" class="bp-input w-full">
                            @error('year_to')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="sort_order" class="block bp-spec mb-2">§ Sort Order <span class="text-ink-muted font-normal normal-case">(higher = first)</span></label>
                            <input type="number" name="sort_order" id="sort_order"
                                   value="{{ old('sort_order', 0) }}" min="0"
                                   class="bp-input w-full">
                            @error('sort_order')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                    </div>
                </div>

                {{-- Status --}}
                <div class="p-5 space-y-3">
                    <p class="bp-spec text-ink-muted">§ Status</p>
                    <label class="flex items-center gap-2 text-sm text-ink cursor-pointer">
                        <input type="checkbox" name="is_active" id="is_active" value="1"
                               {{ old('is_active', true) ? 'checked' : '' }}
                               class="rounded-none border-rule">
                        Active — visible to customers
                    </label>
                </div>

            </div>

            <div class="px-5 py-4 bg-ivory-alt border-t border-rule flex items-center justify-end gap-3">
                <a href="{{ route('admin.catalog.car-models.index') }}" class="bp-btn-ghost">Cancel</a>
                <button type="submit" class="bp-btn-primary">Create Car Model</button>
            </div>
        </form>
    </section>

</div>
@endsection
