@extends('layouts.admin')

@section('title', 'Edit Manufacturer')
@section('page_title', 'Edit Manufacturer')

@section('header_actions')
    <a href="{{ route('admin.catalog.manufacturers.index') }}" class="bp-btn-ghost gap-1">
        <x-heroicon-o-arrow-left class="w-4 h-4" />
        Back to Manufacturers
    </a>
@endsection

@section('content')
<div class="max-w-3xl space-y-6">

    <section class="bp-card overflow-hidden">
        <header class="bp-card-header">
            <p class="bp-spec text-amber-ink">§ Catalog · Edit Manufacturer</p>
            <h2 class="mt-1 font-display text-xl font-bold text-ink tracking-[-0.02em]">
                {{ trans_field($manufacturer->name) }}<span class="text-amber">.</span>
            </h2>
        </header>

        <form action="{{ route('admin.catalog.manufacturers.update', $manufacturer) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            <div class="divide-y divide-rule">

                {{-- Brand Name --}}
                <div class="p-5 space-y-4">
                    <p class="bp-spec text-ink-muted">§ Brand · Name</p>
                    <div>
                        <label for="name" class="block bp-spec mb-2">§ Name <span class="text-red-500">*</span></label>
                        <input type="text" name="name" id="name"
                               value="{{ old('name', trans_field($manufacturer->name, 'en')) }}"
                               required maxlength="255"
                               placeholder="e.g. Bosch"
                               class="bp-input w-full">
                        @error('name')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- Details --}}
                <div class="p-5 space-y-4">
                    <p class="bp-spec text-ink-muted">§ Details</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="country_code" class="block bp-spec mb-2">§ Country of Origin</label>
                            <select name="country_code" id="country_code" class="bp-select">
                                <option value="">Select Country</option>
                                @foreach($countries as $code => $name)
                                    <option value="{{ $code }}" {{ old('country_code', $manufacturer->country_code) == $code ? 'selected' : '' }}>
                                        {{ $name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('country_code')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="slug" class="block bp-spec mb-2">§ Slug <span class="text-ink-muted font-normal normal-case">(used in CSV imports)</span></label>
                            <input type="text" name="slug" id="slug"
                                   value="{{ old('slug', $manufacturer->slug) }}"
                                   class="bp-input-mono w-full">
                            @error('slug')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="sort_order" class="block bp-spec mb-2">§ Sort Order <span class="text-ink-muted font-normal normal-case">(higher = first)</span></label>
                            <input type="number" name="sort_order" id="sort_order"
                                   value="{{ old('sort_order', $manufacturer->sort_order) }}" min="0"
                                   class="bp-input w-full">
                            @error('sort_order')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="md:col-span-2">
                            <label class="block bp-spec mb-2">§ Logo</label>
                            @if($manufacturer->logo)
                                <div class="mb-3 flex items-center gap-3 p-3 bg-ivory-alt border border-rule">
                                    <img src="{{ $manufacturer->logo->file_url }}"
                                         alt="{{ trans_field($manufacturer->name) }}"
                                         class="h-10 w-10 object-contain border border-rule bg-paper p-0.5">
                                    <span class="font-mono text-xs text-ink-muted truncate">{{ $manufacturer->logo->file_name }}</span>
                                    <label class="ml-auto flex items-center gap-1.5 text-xs text-red-600 cursor-pointer">
                                        <input type="checkbox" name="remove_logo" value="1"
                                               class="rounded-none border-rule">
                                        Remove logo
                                    </label>
                                </div>
                            @endif
                            <input type="file" name="logo" id="logo"
                                   accept="image/jpeg,image/png,image/webp"
                                   class="w-full text-sm text-ink-muted border border-rule cursor-pointer
                                          file:mr-3 file:py-2 file:px-4 file:border-0 file:text-xs file:font-mono
                                          file:uppercase file:tracking-wider file:bg-ivory-alt file:text-ink
                                          hover:file:bg-rule">
                            <p class="mt-1 font-mono text-xs text-ink-muted">
                                {{ $manufacturer->logo ? 'Upload a new logo to replace.' : 'PNG, JPG or WebP — max 2 MB.' }}
                            </p>
                            @error('logo')
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
                               {{ old('is_active', $manufacturer->is_active) ? 'checked' : '' }}
                               class="rounded-none border-rule">
                        Active — visible to customers
                    </label>
                    <label class="flex items-center gap-2 text-sm text-ink cursor-pointer">
                        <input type="checkbox" name="is_verified_oem" id="is_verified_oem" value="1"
                               {{ old('is_verified_oem', $manufacturer->is_verified_oem) ? 'checked' : '' }}
                               class="rounded-none border-rule">
                        OEM Verified — official / licenced manufacturer
                    </label>
                </div>

            </div>

            <div class="px-5 py-4 bg-ivory-alt border-t border-rule flex items-center justify-between">
                <button type="button"
                        onclick="if(confirm('Delete this manufacturer? This cannot be undone.')) { document.getElementById('delete-form').submit(); }"
                        class="bp-btn-ghost text-red-600 hover:text-red-700 gap-1">
                    <x-heroicon-o-trash class="w-4 h-4" />
                    Delete
                </button>
                <div class="flex items-center gap-3">
                    <a href="{{ route('admin.catalog.manufacturers.index') }}" class="bp-btn-ghost">Cancel</a>
                    <button type="submit" class="bp-btn-primary">Update Manufacturer</button>
                </div>
            </div>
        </form>

        <form id="delete-form" action="{{ route('admin.catalog.manufacturers.destroy', $manufacturer) }}" method="POST" class="hidden">
            @csrf
            @method('DELETE')
        </form>
    </section>

</div>
@endsection
