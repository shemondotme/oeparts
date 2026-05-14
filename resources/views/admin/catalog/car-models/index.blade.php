@extends('layouts.admin')

@section('title', 'Car Models')
@section('page_title', 'Car Model Management')

@section('header_actions')
    <a href="{{ route('admin.catalog.car-models.create') }}" class="bp-btn-primary">
        <x-heroicon-o-plus class="w-4 h-4" />
        Add Car Model
    </a>
@endsection

@section('content')
<div class="space-y-6" x-data="{ checked: [] }">

    {{-- Filters --}}
    <section class="bp-card">
        <header class="bp-card-header">
            <p class="bp-spec text-ink-muted">§ Filter · Car Models</p>
        </header>
        <form method="GET" action="{{ route('admin.catalog.car-models.index') }}"
              class="p-5 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div>
                <label for="manufacturer_id" class="block bp-spec mb-2">§ Manufacturer</label>
                <select id="manufacturer_id" name="manufacturer_id" class="bp-select">
                    <option value="">All Manufacturers</option>
                    @foreach($manufacturers as $manufacturer)
                        <option value="{{ $manufacturer->id }}" {{ request('manufacturer_id') == $manufacturer->id ? 'selected' : '' }}>
                            {{ trans_field($manufacturer->name) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="name" class="block bp-spec mb-2">§ Model Name</label>
                <input type="text" id="name" name="name" value="{{ request('name') }}"
                       placeholder="e.g. Golf VII, 3 Series"
                       class="bp-input">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="year_from" class="block bp-spec mb-2">§ Year From</label>
                    <input type="number" id="year_from" name="year_from" value="{{ request('year_from') }}"
                           placeholder="1990" class="bp-input">
                </div>
                <div>
                    <label for="year_to" class="block bp-spec mb-2">§ Year To</label>
                    <input type="number" id="year_to" name="year_to" value="{{ request('year_to') }}"
                           placeholder="2025" class="bp-input">
                </div>
            </div>
            <div>
                <label for="active_status" class="block bp-spec mb-2">§ Status</label>
                <select id="active_status" name="active_status" class="bp-select">
                    <option value="">All</option>
                    <option value="active" {{ request('active_status') == 'active' ? 'selected' : '' }}>Active</option>
                    <option value="inactive" {{ request('active_status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                </select>
            </div>
            <div class="md:col-span-2 xl:col-span-4 flex items-center justify-end gap-4 pt-2 border-t border-rule">
                <a href="{{ route('admin.catalog.car-models.index') }}" class="bp-btn-ghost">Reset</a>
                <button type="submit" class="bp-btn-primary">Apply</button>
            </div>
        </form>
    </section>

    {{-- Bulk Actions Bar --}}
    <div x-show="checked.length > 0" x-cloak
         class="flex items-center gap-3 px-5 py-3 bg-ink/5 border border-rule">
        <span class="font-mono text-xs text-ink"
              x-text="checked.length + ' model' + (checked.length > 1 ? 's' : '') + ' selected'"></span>
        <div class="flex items-center gap-2 ml-auto">
            <button type="button"
                    @click="submitBulkForm('{{ route('admin.catalog.car-models.bulk-activate') }}')"
                    class="inline-flex items-center gap-1 border px-3 py-1.5 font-mono text-[10px] font-bold uppercase tracking-wider border-green-600/30 bg-green-50 text-green-700 hover:bg-green-100">
                <x-heroicon-o-check-circle class="w-3.5 h-3.5" />
                Activate
            </button>
            <button type="button"
                    @click="submitBulkForm('{{ route('admin.catalog.car-models.bulk-deactivate') }}')"
                    class="inline-flex items-center gap-1 border px-3 py-1.5 font-mono text-[10px] font-bold uppercase tracking-wider border-rule bg-ivory-alt text-ink hover:bg-rule">
                <x-heroicon-o-x-circle class="w-3.5 h-3.5" />
                Deactivate
            </button>
            <button type="button" @click="checked = []"
                    class="font-mono text-[10px] text-ink-muted hover:text-ink ml-2">Clear</button>
        </div>
    </div>

    {{-- Table --}}
    <section class="bp-card overflow-hidden">
        <header class="bp-card-header flex items-center justify-between gap-4">
            <div>
                <p class="bp-spec text-amber-ink">§ Catalog · Car Models</p>
                <h2 class="mt-1 font-display text-xl font-bold text-ink tracking-[-0.02em]">
                    Car Model Registry<span class="text-amber">.</span>
                </h2>
            </div>
            <p class="font-mono text-xs text-ink-muted tabular-nums">
                {{ number_format($carModels->total()) }} records
            </p>
        </header>

        <div class="overflow-x-auto">
            <table class="bp-table">
                <thead>
                    <tr>
                        <th class="w-10">
                            <input type="checkbox" class="rounded-none border-rule"
                                   x-on:change="checked = $event.target.checked
                                       ? {{ json_encode($carModels->pluck('id')->toArray()) }} : []"
                                   :checked="checked.length === {{ $carModels->count() }} && {{ $carModels->count() }} > 0">
                        </th>
                        <th>Model</th>
                        <th>Manufacturer</th>
                        <th>Years</th>
                        <th>Status</th>
                        <th>Products</th>
                        <th class="text-right pr-5">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($carModels as $carModel)
                        <tr class="cursor-pointer" data-edit-url="{{ route('admin.catalog.car-models.edit', $carModel) }}">
                            <td class="no-row-click">
                                <input type="checkbox" name="selected_ids[]"
                                       value="{{ $carModel->id }}"
                                       class="rounded-none border-rule"
                                       x-model="checked">
                            </td>
                            <td>
                                <p class="text-sm font-bold text-ink">{{ $carModel->name }}</p>
                                @if($carModel->slug)
                                    <p class="font-mono text-xs text-ink-muted mt-0.5">{{ $carModel->slug }}</p>
                                @endif
                            </td>
                            <td>
                                <p class="text-sm text-ink">{{ $carModel->manufacturer ? trans_field($carModel->manufacturer->name) : '—' }}</p>
                            </td>
                            <td>
                                <p class="font-mono text-xs tabular-nums text-ink">
                                    @if($carModel->year_from)
                                        {{ $carModel->year_from }}–{{ $carModel->year_to ?? 'now' }}
                                    @else
                                        —
                                    @endif
                                </p>
                            </td>
                            <td>
                                @if($carModel->is_active)
                                    <span class="font-mono text-xs text-emerald-600 font-bold">ACTIVE</span>
                                @else
                                    <span class="font-mono text-xs text-ink-muted font-bold">INACTIVE</span>
                                @endif
                            </td>
                            <td>
                                <p class="font-mono text-sm tabular-nums text-ink">
                                    {{ number_format($carModel->products_count ?? $carModel->products()->count()) }}
                                </p>
                            </td>
                            <td class="text-right pr-5 no-row-click">
                                <div class="flex items-center justify-end gap-1">
                                    <a href="{{ route('admin.catalog.car-models.edit', $carModel) }}"
                                       class="bp-btn-ghost gap-1 text-[10px]">
                                        <x-heroicon-o-pencil-square class="w-3.5 h-3.5" />
                                        Edit
                                    </a>
                                    <form action="{{ route('admin.catalog.car-models.destroy', $carModel) }}" method="POST"
                                          onsubmit="return confirm('Delete this car model?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="bp-btn-ghost text-red-600 hover:text-red-700 gap-1 text-[10px]" aria-label="Delete">
                                            <x-heroicon-o-trash class="w-3.5 h-3.5" />
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-16 text-center">
                                <x-heroicon-o-inbox class="w-10 h-10 mx-auto text-ink/20 mb-3" />
                                <p class="font-display font-bold text-ink">No car models found</p>
                                <p class="mt-1 text-sm text-ink-muted">Try adjusting your filters or add one.</p>
                                <a href="{{ route('admin.catalog.car-models.create') }}" class="bp-btn-primary mt-5 inline-flex">
                                    <x-heroicon-o-plus class="w-4 h-4" />
                                    Add First Car Model
                                </a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($carModels->hasPages())
            <div class="px-5 py-4 border-t border-rule">
                {{ $carModels->withQueryString()->links() }}
            </div>
        @endif
    </section>

</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('tr[data-edit-url]').forEach(row => {
            row.addEventListener('click', function (e) {
                if (e.target.closest('a, button, form, .no-row-click')) return;
                window.location.href = this.dataset.editUrl;
            });
        });
    });

    function submitBulkForm(action) {
        const checked = document.querySelectorAll('input[name="selected_ids[]"]:checked');
        if (!checked.length) return;
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = action;
        const csrf = document.createElement('input');
        csrf.type = 'hidden';
        csrf.name = '_token';
        csrf.value = '{{ csrf_token() }}';
        form.appendChild(csrf);
        checked.forEach(cb => {
            const inp = document.createElement('input');
            inp.type = 'hidden';
            inp.name = 'ids[]';
            inp.value = cb.value;
            form.appendChild(inp);
        });
        document.body.appendChild(form);
        form.submit();
    }
</script>
@endpush
