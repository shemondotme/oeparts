@extends('layouts.admin')

@section('title', 'Import Products via CSV')

@section('content')
<div class="px-6 py-8">

    {{-- Header --}}
    <div class="mb-8 flex justify-between items-start">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Import Products via CSV</h1>
            <p class="text-gray-600 mt-1">Upload a CSV file to add or update products in bulk.</p>
        </div>
        <a href="{{ route('admin.catalog.products.index') }}"
           class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
            <x-heroicon-o-arrow-left class="w-4 h-4 mr-2" />
            Back to Products
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Upload form --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- Errors --}}
            @if($errors->any())
            <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                <ul class="text-sm text-red-700 space-y-1 list-disc list-inside">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            @endif

            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <form action="{{ route('admin.catalog.products.import.process') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="p-6 space-y-6">

                        {{-- File picker --}}
                        <div x-data="{ fileName: '' }">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                CSV File <span class="text-red-500">*</span>
                            </label>
                            <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-amber transition-colors"
                                 @dragover.prevent
                                 @drop.prevent="
                                     const file = $event.dataTransfer.files[0];
                                     if (file) { fileName = file.name; $refs.fileInput.files = $event.dataTransfer.files; }
                                 ">
                                <x-heroicon-o-document-arrow-up class="w-10 h-10 mx-auto text-gray-400 mb-3" />
                                <p class="text-sm text-gray-600 mb-1">
                                    <span x-text="fileName || 'Drag and drop your CSV file here, or'"></span>
                                </p>
                                <label class="mt-1 inline-flex items-center px-3 py-1.5 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 cursor-pointer">
                                    Browse file
                                    <input type="file"
                                           name="csv_file"
                                           accept=".csv,text/csv"
                                           class="hidden"
                                           x-ref="fileInput"
                                           @change="fileName = $event.target.files[0]?.name ?? ''">
                                </label>
                                <p class="mt-2 text-xs text-gray-400">CSV only · max 100 MB per file</p>
                                <p class="text-xs text-gray-400">Split files larger than 100 MB into multiple uploads</p>
                            </div>
                        </div>

                        {{-- Options --}}
                        <div class="space-y-3">
                            <h3 class="text-sm font-semibold text-gray-700">Import Options</h3>
                            <div class="flex items-start gap-3 bg-amber-50 border border-amber-200 rounded-lg p-4">
                                <input type="checkbox"
                                       name="update_existing"
                                       id="update_existing"
                                       value="1"
                                       {{ old('update_existing') ? 'checked' : '' }}
                                       class="mt-0.5 h-4 w-4 text-amber border-gray-300 rounded focus:ring-amber">
                                <div>
                                    <label for="update_existing" class="text-sm font-medium text-gray-700 cursor-pointer">
                                        Update existing products
                                    </label>
                                    <p class="text-xs text-gray-500 mt-0.5">
                                        When a product with the same manufacturer + OEM number already exists,
                                        overwrite its price, stock, condition, and names.
                                        Leave unchecked to skip duplicates.
                                    </p>
                                </div>
                            </div>
                        </div>

                    </div>

                    <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-end gap-3">
                        <a href="{{ route('admin.catalog.products.index') }}"
                           class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                            Cancel
                        </a>
                        <button type="submit"
                                class="inline-flex items-center gap-2 px-4 py-2 bg-navy text-white rounded-lg text-sm font-medium hover:bg-navy/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-navy transition-colors">
                            <x-heroicon-o-arrow-up-tray class="w-4 h-4" />
                            Start Import
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Sidebar: column reference + template download --}}
        <div class="space-y-6">

            {{-- Template download --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                <h3 class="text-sm font-semibold text-gray-900 mb-2">Download Template</h3>
                <p class="text-sm text-gray-500 mb-4">
                    Start from our pre-formatted template with all columns and an example row.
                </p>
                <a href="{{ route('admin.catalog.products.import.template') }}"
                   class="inline-flex items-center gap-2 w-full justify-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    <x-heroicon-o-arrow-down-tray class="w-4 h-4" />
                    products_import_template.csv
                </a>
            </div>

            {{-- Column reference --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                <h3 class="text-sm font-semibold text-gray-900 mb-3">Column Reference</h3>

                <div class="space-y-3 text-xs">
                    <div>
                        <p class="font-semibold text-gray-700 uppercase tracking-wide mb-1">Required</p>
                        <dl class="space-y-1.5">
                            <div>
                                <dt class="font-mono text-navy font-semibold">oem_number</dt>
                                <dd class="text-gray-500 pl-2">e.g. <span class="font-mono">0252225577</span></dd>
                            </div>
                            <div>
                                <dt class="font-mono text-navy font-semibold">manufacturer_slug</dt>
                                <dd class="text-gray-500 pl-2">URL slug from Manufacturers list, e.g. <span class="font-mono">bosch</span></dd>
                            </div>
                            <div>
                                <dt class="font-mono text-navy font-semibold">condition</dt>
                                <dd class="text-gray-500 pl-2">
                                    @foreach(\App\Enums\ProductCondition::cases() as $c)
                                        <span class="font-mono">{{ $c->value }}</span>@if(!$loop->last), @endif
                                    @endforeach
                                </dd>
                            </div>
                            <div>
                                <dt class="font-mono text-navy font-semibold">price</dt>
                                <dd class="text-gray-500 pl-2">Ex. VAT, decimal, e.g. <span class="font-mono">8.99</span></dd>
                            </div>
                            <div>
                                <dt class="font-mono text-navy font-semibold">is_in_stock</dt>
                                <dd class="text-gray-500 pl-2"><span class="font-mono">1</span> or <span class="font-mono">0</span></dd>
                            </div>
                        </dl>
                    </div>

                    <div>
                        <p class="font-semibold text-gray-700 uppercase tracking-wide mb-1">Optional</p>
                        <dl class="space-y-1.5">
                            <div>
                                <dt class="font-mono text-gray-600">delivery_time</dt>
                                <dd class="text-gray-500 pl-2">e.g. <span class="font-mono">3-5 days</span></dd>
                            </div>
                            <div>
                                <dt class="font-mono text-gray-600">moq</dt>
                                <dd class="text-gray-500 pl-2">Integer ≥ 1, default 1</dd>
                            </div>
                            <div>
                                <dt class="font-mono text-gray-600">name_en / name_de / name_lt / name_fr / name_es</dt>
                                <dd class="text-gray-500 pl-2">Product name per language</dd>
                            </div>
                            <div>
                                <dt class="font-mono text-gray-600">description_en … description_es</dt>
                                <dd class="text-gray-500 pl-2">Description per language</dd>
                            </div>
                            <div>
                                <dt class="font-mono text-gray-600">cross_oem_numbers</dt>
                                <dd class="text-gray-500 pl-2">Pipe-separated, e.g. <span class="font-mono">0242229799|0242240650</span></dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>

            {{-- Tips --}}
            <div class="bg-blue-50 border border-blue-200 rounded-xl p-5 text-xs text-blue-800 space-y-1.5">
                <p class="font-semibold text-blue-900">Tips for large imports</p>
                <ul class="list-disc list-inside space-y-1 text-blue-700">
                    <li>Save your spreadsheet as CSV UTF-8 (comma delimited)</li>
                    <li>Split files over 100 MB into multiple uploads</li>
                    <li>Imports run in the background — you can navigate away</li>
                    <li>Duplicates are matched by manufacturer + OEM number</li>
                    <li>Errors are logged to <strong>Bulk Update Logs</strong></li>
                </ul>
            </div>

        </div>
    </div>
</div>
@endsection
