@extends('layouts.admin')

@section('title', 'Bulk Update')
@section('page_title', 'Bulk Update')

@section('header_actions')
    <div class="flex gap-2">
        <a href="{{ route('admin.catalog.bulk-update.logs') }}" class="bp-btn-outline">
            <x-heroicon-o-clock class="w-4 h-4" />
            Logs
        </a>
        <a href="{{ route('admin.catalog.products.index') }}" class="bp-btn-ghost">
            <x-heroicon-o-arrow-left class="w-4 h-4" />
            Products
        </a>
    </div>
@endsection

@section('content')
<div
    class="grid grid-cols-1 gap-6 lg:grid-cols-3"
    x-data="bulkUpdateConsole()"
>
    <section class="bp-card lg:col-span-2">
        <header class="bp-card-header">
            <p class="bp-spec text-amber-ink">§ Catalog · Controlled Write</p>
            <h2 class="mt-1 font-display text-xl font-bold tracking-[-0.02em] text-ink">
                Bulk update workspace<span class="text-amber">.</span>
            </h2>
            <p class="mt-2 text-sm text-ink-muted">
                Preview every matched record before execution. Confirmation requires typing <span class="font-mono text-ink">CONFIRM</span>.
            </p>
        </header>

        <form class="space-y-6 p-5" @submit.prevent="preview">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <x-admin.form-field label="Entity Type" name="entity_type">
                    <select id="entity_type" x-model="entityType" class="bp-select">
                        <option value="products">Products</option>
                        <option value="manufacturers">Manufacturers</option>
                        <option value="car_models">Car Models</option>
                    </select>
                </x-admin.form-field>

                <x-admin.form-field label="Manufacturer" name="manufacturer_id">
                    <select id="manufacturer_id" x-model="filters.manufacturer_id" class="bp-select">
                        <option value="">All manufacturers</option>
                        @foreach($manufacturers as $manufacturer)
                            <option value="{{ $manufacturer->id }}">{{ trans_field($manufacturer->name) }}</option>
                        @endforeach
                    </select>
                </x-admin.form-field>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-3" x-show="entityType === 'products'">
                <x-admin.form-field label="OEM Number" name="oem_number">
                    <input id="oem_number" x-model="filters.oem_number" type="text" class="bp-input-mono" placeholder="A000000">
                </x-admin.form-field>

                <x-admin.form-field label="Condition" name="condition">
                    <select id="condition" x-model="filters.condition" class="bp-select">
                        <option value="">Any condition</option>
                        @foreach($conditions as $condition)
                            <option value="{{ $condition->value }}">{{ $condition->label() }}</option>
                        @endforeach
                    </select>
                </x-admin.form-field>

                <x-admin.form-field label="Stock Status" name="stock_status">
                    <select id="stock_status" x-model="filters.stock_status" class="bp-select">
                        <option value="">Any stock status</option>
                        <option value="in_stock">In stock</option>
                        <option value="out_of_stock">Out of stock</option>
                    </select>
                </x-admin.form-field>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <x-admin.form-field label="Active State" name="filter_is_active">
                    <select id="filter_is_active" x-model="filters.is_active" class="bp-select">
                        <option value="">Any state</option>
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </x-admin.form-field>

                <x-admin.form-field label="Name Contains" name="name">
                    <input id="name" x-model="filters.name" type="text" class="bp-input" placeholder="Manufacturer or model name">
                </x-admin.form-field>

                <x-admin.form-field label="Country Code" name="country_code">
                    <input id="country_code" x-model="filters.country_code" type="text" maxlength="2" class="bp-input-mono" placeholder="DE">
                </x-admin.form-field>
            </div>

            <div class="border border-rule bg-ivory-alt p-4">
                <p class="bp-spec text-amber-ink">Updates</p>
                <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-3">
                    <x-admin.form-field label="Price" name="price">
                        <input id="price" x-model="updates.price" type="number" step="0.01" min="0" class="bp-input" placeholder="149.99">
                    </x-admin.form-field>

                    <x-admin.form-field label="Stock" name="is_in_stock">
                        <select id="is_in_stock" x-model="updates.is_in_stock" class="bp-select">
                            <option value="">No change</option>
                            <option value="1">In stock</option>
                            <option value="0">Out of stock</option>
                        </select>
                    </x-admin.form-field>

                    <x-admin.form-field label="Active" name="is_active">
                        <select id="is_active" x-model="updates.is_active" class="bp-select">
                            <option value="">No change</option>
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </x-admin.form-field>

                    <x-admin.form-field label="Condition" name="update_condition">
                        <select id="update_condition" x-model="updates.condition" class="bp-select">
                            <option value="">No change</option>
                            @foreach($conditions as $condition)
                                <option value="{{ $condition->value }}">{{ $condition->label() }}</option>
                            @endforeach
                        </select>
                    </x-admin.form-field>

                    <x-admin.form-field label="Verified OEM" name="is_verified_oem">
                        <select id="is_verified_oem" x-model="updates.is_verified_oem" class="bp-select">
                            <option value="">No change</option>
                            <option value="1">Verified</option>
                            <option value="0">Not verified</option>
                        </select>
                    </x-admin.form-field>

                    <x-admin.form-field label="Year Range" name="year_from">
                        <div class="grid grid-cols-2 gap-2">
                            <input x-model="updates.year_from" type="number" class="bp-input" placeholder="From">
                            <input x-model="updates.year_to" type="number" class="bp-input" placeholder="To">
                        </div>
                    </x-admin.form-field>
                </div>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <button type="button" class="bp-btn-outline" @click="resetForm">Reset</button>
                <button type="submit" class="bp-btn-primary" :disabled="loading">
                    <x-heroicon-o-eye class="w-4 h-4" />
                    Preview Changes
                </button>
            </div>
        </form>
    </section>

    <aside class="space-y-6">
        <section class="bp-card">
            <header class="bp-card-header">
                <p class="bp-spec text-amber-ink">§ Workflow</p>
            </header>
            <ol class="space-y-3 p-5 text-sm text-ink-muted">
                <li><span class="font-mono text-ink">01</span> Select entity and filters.</li>
                <li><span class="font-mono text-ink">02</span> Choose the fields to update.</li>
                <li><span class="font-mono text-ink">03</span> Preview affected rows.</li>
                <li><span class="font-mono text-ink">04</span> Type CONFIRM and execute.</li>
            </ol>
        </section>

        <section class="bp-card" x-show="previewResult">
            <header class="bp-card-header">
                <p class="bp-spec text-amber-ink">§ Preview</p>
            </header>
            <div class="space-y-4 p-5">
                <p class="font-mono text-3xl font-bold text-ink" x-text="previewResult?.total_count ?? 0"></p>
                <p class="text-sm text-ink-muted" x-text="previewResult?.preview_summary"></p>
                <input x-model="confirmation" class="bp-input-mono" placeholder="Type CONFIRM">
                <button type="button" class="bp-btn-amber w-full" @click="execute" :disabled="confirmation !== 'CONFIRM' || loading">
                    Execute Update
                </button>
            </div>
        </section>

        <section class="bp-card" x-show="message">
            <div class="p-5">
                <p class="font-mono text-sm text-ink" x-text="message"></p>
            </div>
        </section>
    </aside>
</div>
@endsection

@push('scripts')
<script>
function bulkUpdateConsole() {
    return {
        entityType: '{{ $entityType }}',
        filters: {},
        updates: {},
        previewResult: null,
        confirmation: '',
        loading: false,
        message: '',
        clean(object) {
            return Object.fromEntries(Object.entries(object).filter(([, value]) => value !== '' && value !== null && value !== undefined));
        },
        params(payload) {
            const params = new URLSearchParams();
            Object.entries(payload).forEach(([key, value]) => {
                if (value && typeof value === 'object' && !Array.isArray(value)) {
                    Object.entries(value).forEach(([childKey, childValue]) => params.append(`${key}[${childKey}]`, childValue));
                } else {
                    params.append(key, value);
                }
            });
            return params;
        },
        async preview() {
            this.loading = true;
            this.message = '';
            this.previewResult = null;

            const response = await fetch(`{{ route('admin.catalog.bulk-update.preview') }}?${this.params({
                entity_type: this.entityType,
                filters: this.clean(this.filters),
                updates: this.clean(this.updates),
            })}`, { headers: { Accept: 'application/json' } });

            const data = await response.json();
            this.loading = false;

            if (!response.ok || !data.success) {
                this.message = data.message || 'Preview failed. Check selected filters and updates.';
                return;
            }

            this.previewResult = data;
        },
        async execute() {
            this.loading = true;
            this.message = '';

            const response = await fetch('{{ route('admin.catalog.bulk-update.execute') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({
                    entity_type: this.entityType,
                    filters: this.clean(this.filters),
                    updates: this.clean(this.updates),
                    confirmation: this.confirmation,
                }),
            });

            const data = await response.json();
            this.loading = false;
            this.message = data.message || (response.ok ? 'Bulk update completed.' : 'Bulk update failed.');

            if (response.ok && data.success) {
                this.previewResult = null;
                this.confirmation = '';
            }
        },
        resetForm() {
            this.filters = {};
            this.updates = {};
            this.previewResult = null;
            this.confirmation = '';
            this.message = '';
        },
    };
}
</script>
@endpush
