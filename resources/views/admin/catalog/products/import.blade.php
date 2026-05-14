@extends('layouts.admin')

@section('title', 'Import Products via CSV')
@section('page_title', 'Import Products')

@section('header_actions')
    <a href="{{ route('admin.catalog.products.index') }}" class="bp-btn-ghost gap-1">
        <x-heroicon-o-arrow-left class="w-4 h-4" />
        Back to Products
    </a>
@endsection

@section('content')
<div class="space-y-6">

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Upload form --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- Validation errors --}}
            @if($errors->any())
            <div class="border border-red-600/30 bg-red-50 p-4">
                <p class="bp-spec text-red-600 mb-2">§ Validation · Errors</p>
                <ul class="space-y-1">
                    @foreach($errors->all() as $error)
                        <li class="font-mono text-xs text-red-700">— {{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            @endif

            <section class="bp-card overflow-hidden">
                <header class="bp-card-header">
                    <p class="bp-spec text-amber-ink">§ Catalog · CSV Import</p>
                    <h2 class="mt-1 font-display text-xl font-bold text-ink tracking-[-0.02em]">
                        Upload CSV File<span class="text-amber">.</span>
                    </h2>
                </header>

                <form action="{{ route('admin.catalog.products.import.process') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="divide-y divide-rule">

                        {{-- File drop zone --}}
                        <div class="p-5" x-data="{ fileName: '' }">
                            <label class="block bp-spec mb-3">§ CSV File <span class="text-red-500">*</span></label>
                            <div class="border-2 border-dashed border-rule bg-ivory-alt p-8 text-center transition-colors hover:border-amber cursor-pointer"
                                 @dragover.prevent="$el.classList.add('border-amber')"
                                 @dragleave.prevent="$el.classList.remove('border-amber')"
                                 @drop.prevent="
                                     $el.classList.remove('border-amber');
                                     const file = $event.dataTransfer.files[0];
                                     if (file) { fileName = file.name; $refs.fileInput.files = $event.dataTransfer.files; }
                                 ">
                                <x-heroicon-o-document-arrow-up class="w-10 h-10 mx-auto text-ink/20 mb-3" />
                                <p class="text-sm text-ink-muted mb-1" x-text="fileName || 'Drag and drop your CSV file here'"></p>
                                <label class="mt-2 inline-flex items-center gap-1 border border-rule bg-paper px-3 py-1.5 font-mono text-xs text-ink uppercase tracking-wider cursor-pointer hover:bg-ivory-alt transition-colors">
                                    <x-heroicon-o-folder-open class="w-3.5 h-3.5" />
                                    Browse File
                                    <input type="file" name="csv_file" accept=".csv,text/csv"
                                           class="hidden" x-ref="fileInput"
                                           @change="fileName = $event.target.files[0]?.name ?? ''">
                                </label>
                                <p class="mt-3 font-mono text-xs text-ink-muted">CSV only · max 100 MB per file · split larger files into multiple uploads</p>
                            </div>
                        </div>

                        {{-- Import options --}}
                        <div class="p-5 space-y-3">
                            <p class="bp-spec text-ink-muted">§ Import · Options</p>
                            <div class="border border-amber/30 bg-amber-50 p-4">
                                <label class="flex items-start gap-3 cursor-pointer">
                                    <input type="checkbox" name="update_existing" id="update_existing" value="1"
                                           {{ old('update_existing') ? 'checked' : '' }}
                                           class="mt-0.5 rounded-none border-rule flex-shrink-0">
                                    <div>
                                        <p class="text-sm font-bold text-ink">Update existing products</p>
                                        <p class="font-mono text-xs text-ink-muted mt-0.5">
                                            When a product with the same manufacturer + OEM number already exists,
                                            overwrite its price, stock, condition, and names.
                                            Leave unchecked to skip duplicates.
                                        </p>
                                    </div>
                                </label>
                            </div>
                        </div>

                    </div>

                    <div class="px-5 py-4 bg-ivory-alt border-t border-rule flex items-center justify-end gap-3">
                        <a href="{{ route('admin.catalog.products.index') }}" class="bp-btn-ghost">Cancel</a>
                        <button type="submit" class="bp-btn-primary gap-1">
                            <x-heroicon-o-arrow-up-tray class="w-4 h-4" />
                            Start Import
                        </button>
                    </div>
                </form>
            </section>
        </div>

        {{-- Sidebar: template + column reference + tips --}}
        <div class="space-y-6">

            {{-- Template download --}}
            <section class="bp-card overflow-hidden">
                <header class="bp-card-header">
                    <p class="bp-spec text-ink-muted">§ Download · Template</p>
                </header>
                <div class="p-5">
                    <p class="text-sm text-ink-muted mb-4">Start from our pre-formatted template with all columns and an example row.</p>
                    <a href="{{ route('admin.catalog.products.import.template') }}"
                       class="bp-btn-outline w-full justify-center gap-1">
                        <x-heroicon-o-arrow-down-tray class="w-4 h-4" />
                        products_import_template.csv
                    </a>
                </div>
            </section>

            {{-- Column reference --}}
            <section class="bp-card overflow-hidden">
                <header class="bp-card-header">
                    <p class="bp-spec text-ink-muted">§ Column · Reference</p>
                </header>
                <div class="p-5 space-y-5">

                    <div>
                        <p class="bp-spec text-red-600 mb-2">§ Required</p>
                        <div class="space-y-2">
                            @foreach([
                                ['oem_number',       'e.g. 0252225577'],
                                ['manufacturer_slug','URL slug from Manufacturers list, e.g. bosch'],
                                ['condition',        null],
                                ['price',            'Ex. VAT, decimal, e.g. 8.99'],
                                ['is_in_stock',      '1 or 0'],
                            ] as [$col, $hint])
                            <div>
                                <p class="font-mono text-[11px] font-bold text-amber-ink">{{ $col }}</p>
                                @if($col === 'condition')
                                    <p class="font-mono text-[10px] text-ink-muted mt-0.5">
                                        @foreach(\App\Enums\ProductCondition::cases() as $c)
                                            {{ $c->value }}@if(!$loop->last), @endif
                                        @endforeach
                                    </p>
                                @elseif($hint)
                                    <p class="font-mono text-[10px] text-ink-muted mt-0.5">{{ $hint }}</p>
                                @endif
                            </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="border-t border-rule pt-4">
                        <p class="bp-spec text-ink-muted mb-2">§ Optional</p>
                        <div class="space-y-2">
                            @foreach([
                                ['delivery_time',      'e.g. 3-5 days'],
                                ['moq',                'Integer ≥ 1, default 1'],
                                ['name_en / name_de / name_lt / name_fr / name_es', 'Product name per language'],
                                ['description_en … description_es', 'Description per language'],
                                ['cross_oem_numbers',  'Pipe-separated, e.g. 0242229799|0242240650'],
                            ] as [$col, $hint])
                            <div>
                                <p class="font-mono text-[11px] text-ink">{{ $col }}</p>
                                <p class="font-mono text-[10px] text-ink-muted mt-0.5">{{ $hint }}</p>
                            </div>
                            @endforeach
                        </div>
                    </div>

                </div>
            </section>

            {{-- Tips --}}
            <div class="border border-blue-600/30 bg-blue-50 p-5 space-y-2">
                <p class="bp-spec text-blue-700">§ Tips · Large Imports</p>
                <ul class="space-y-1.5">
                    @foreach([
                        'Save your spreadsheet as CSV UTF-8 (comma delimited)',
                        'Split files over 100 MB into multiple uploads',
                        'Imports run in the background — you can navigate away',
                        'Duplicates are matched by manufacturer + OEM number',
                        'Errors are logged to Bulk Update Logs',
                    ] as $tip)
                    <li class="font-mono text-xs text-blue-800">— {{ $tip }}</li>
                    @endforeach
                </ul>
            </div>

        </div>
    </div>

</div>
@endsection
