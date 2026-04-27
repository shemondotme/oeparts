@extends('layouts.admin')

@section('title', 'Edit Section: ' . trans_field($section->title))

@push('styles')
<style>
    .lang-tabs { display: flex; border-bottom: 1px solid #e5e7eb; margin-bottom: 1rem; }
    .lang-tab { padding: 0.5rem 1rem; cursor: pointer; border: 1px solid transparent; border-bottom: none; border-radius: 0.375rem 0.375rem 0 0; background: #f9fafb; font-size: 0.875rem; font-weight: 500; color: #6b7280; }
    .lang-tab.active { background: white; border-color: #e5e7eb; border-bottom-color: white; color: #0B3A68; margin-bottom: -1px; z-index: 10; }
    .lang-pane { display: none; }
    .lang-pane.active { display: block; }
    .preview-panel { position: sticky; top: 100px; max-height: calc(100vh - 120px); overflow-y: auto; }
    .version-item { padding: 0.75rem; border-left: 3px solid #ddd; margin: 0.5rem 0; transition: all 0.2s; cursor: pointer; }
    .version-item:hover { background: #f3f4f6; border-left-color: #0B3A68; }
    .version-item.active { background: #eff6ff; border-left-color: #0B3A68; }
</style>
@endpush

@section('content')
<div class="px-6 py-8">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Edit Section</h1>
            <p class="text-gray-600 mt-1">{{ $section->type }} • <span class="px-2 py-1 rounded text-xs font-medium" :class="'{{ $section->status->badgeColor() }}'">{{ $section->status->label() }}</span></p>
        </div>
        <a href="{{ route('admin.cms.sections.index') }}" class="text-sm text-gray-600 hover:text-gray-900">← Back</a>
    </div>

    @if ($errors->any())
        <div class="mb-6 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg">
            <ul class="list-disc list-inside text-sm">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('admin.cms.sections.update', $section) }}" method="POST" class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm" x-data="sectionEditor()">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">

            {{-- Left Column: Settings + Version History --}}
            <div class="lg:col-span-1 space-y-6">
                {{-- General Settings --}}
                <div>
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Settings</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Location</label>
                            <select name="location" class="w-full rounded-lg border-gray-300 text-sm focus:border-[#0B3A68] focus:ring-[#0B3A68]">
                                @foreach($locations as $loc)
                                    <option value="{{ $loc->value }}" {{ $section->location->value === $loc->value ? 'selected' : '' }}>
                                        {{ ucfirst(str_replace('_', ' ', $loc->value)) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <select name="status" class="w-full rounded-lg border-gray-300 text-sm focus:border-[#0B3A68] focus:ring-[#0B3A68]">
                                @foreach($statuses as $status)
                                    <option value="{{ $status->value }}" {{ $section->status->value === $status->value ? 'selected' : '' }}>
                                        {{ $status->label() }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Publish Date</label>
                            <input type="datetime-local" name="publish_at" 
                                   value="{{ $section->publish_at?->format('Y-m-d\TH:i') ?? '' }}"
                                   class="w-full rounded-lg border-gray-300 text-sm focus:border-[#0B3A68] focus:ring-[#0B3A68]">
                        </div>

                        <div class="flex items-center">
                            <input id="is_active" name="is_active" type="checkbox" value="1" {{ $section->is_active ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-[#0B3A68] focus:ring-[#0B3A68]">
                            <label for="is_active" class="ml-2 block text-sm text-gray-900">Active</label>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Sort Order</label>
                            <input type="number" name="sort_order" value="{{ $section->sort_order }}"
                                   class="w-full rounded-lg border-gray-300 text-sm focus:border-[#0B3A68] focus:ring-[#0B3A68]">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Change Summary</label>
                            <textarea name="change_summary" rows="2" placeholder="Describe what changed..."
                                      class="w-full rounded-lg border-gray-300 text-sm focus:border-[#0B3A68] focus:ring-[#0B3A68]"></textarea>
                        </div>
                    </div>
                </div>

                {{-- Version History --}}
                @if($versions->count() > 0)
                <div>
                    <h3 class="text-lg font-medium text-gray-900 mb-3">Version History</h3>
                    <div class="space-y-2 max-h-96 overflow-y-auto">
                        @foreach($versions as $version)
                        <form action="{{ route('admin.cms.sections.restore-version', [$section, $version]) }}" method="POST" style="display:inline;">
                            @csrf
                            <button type="submit" class="version-item w-full text-left text-xs hover:bg-gray-100" title="Restore this version">
                                <div class="font-medium text-gray-900 capitalize">{{ $version->action }}</div>
                                <div class="text-gray-500">{{ $version->created_at->format('M d, H:i') }}</div>
                                @if($version->author)
                                <div class="text-gray-400">by {{ $version->author->name ?? 'Unknown' }}</div>
                                @endif
                                @if($version->change_summary)
                                <div class="text-gray-600 italic mt-1">{{ $version->change_summary }}</div>
                                @endif
                            </button>
                        </form>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>

            {{-- Middle Column: Content Editor --}}
            <div class="lg:col-span-2 space-y-6">

                {{-- Title --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Section Title (Admin)</label>
                    <div x-data="{ lang: 'en' }" class="border border-gray-200 rounded-lg overflow-hidden">
                        <div class="flex bg-gray-50 border-b border-gray-200">
                            @foreach(['en' => 'English', 'de' => 'German', 'lt' => 'Lithuanian', 'fr' => 'French', 'es' => 'Spanish'] as $code => $name)
                                <button type="button" @click="lang = '{{ $code }}'"
                                        :class="{ 'bg-white text-[#0B3A68] border-t-2 border-[#0B3A68]': lang === '{{ $code }}', 'text-gray-500 hover:text-gray-700': lang !== '{{ $code }}' }"
                                        class="px-4 py-2 text-xs font-medium transition-colors">
                                    {{ $name }}
                                </button>
                            @endforeach
                        </div>
                        <div class="p-4 bg-white">
                            @foreach(['en', 'de', 'lt', 'fr', 'es'] as $code)
                                <div x-show="lang === '{{ $code }}'">
                                    <input type="text" name="title[{{ $code }}]"
                                           value="{{ old('title.' . $code, $section->title[$code] ?? '') }}"
                                           class="w-full rounded-lg border-gray-300 text-sm focus:border-[#0B3A68] focus:ring-[#0B3A68]">
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Content with Rich Editor --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Section Content</label>
                    <div x-data="{ lang: 'en' }" class="border border-gray-200 rounded-lg overflow-hidden">
                        <div class="flex bg-gray-50 border-b border-gray-200">
                            @foreach(['en' => 'English', 'de' => 'German', 'lt' => 'Lithuanian', 'fr' => 'French', 'es' => 'Spanish'] as $code => $name)
                                <button type="button" @click="lang = '{{ $code }}'"
                                        :class="{ 'bg-white text-[#0B3A68] border-t-2 border-[#0B3A68]': lang === '{{ $code }}', 'text-gray-500 hover:text-gray-700': lang !== '{{ $code }}' }"
                                        class="px-4 py-2 text-xs font-medium transition-colors">
                                    {{ $name }}
                                </button>
                            @endforeach
                        </div>

                        <div class="p-4 bg-white space-y-4">
                            @foreach(['en', 'de', 'lt', 'fr', 'es'] as $code)
                                <div x-show="lang === '{{ $code }}'">
                                    @php
                                        $content = old('content.' . $code, $section->content[$code] ?? []);
                                        $isComplex = is_array($content) && count(array_filter($content, 'is_array')) > 0;
                                    @endphp

                                    @if(!$isComplex)
                                        @foreach(['headline', 'subheadline', 'description', 'eyebrow', 'button_text', 'placeholder', 'cta_text'] as $key)
                                            <div>
                                                <label class="block text-xs font-medium text-gray-500 uppercase mb-1">{{ str_replace('_', ' ', $key) }}</label>
                                                @if(in_array($key, ['subheadline', 'description']))
                                                    <x-forms.rich-text-editor 
                                                        name="content[{{ $code }}][{{ $key }}]"
                                                        :value="$content[$key] ?? ''"
                                                        :lang="$code" />
                                                @else
                                                    <input type="text" name="content[{{ $code }}][{{ $key }}]"
                                                           value="{{ $content[$key] ?? '' }}"
                                                           class="w-full rounded-lg border-gray-300 text-sm focus:border-[#0B3A68] focus:ring-[#0B3A68]">
                                                @endif
                                            </div>
                                        @endforeach

                                        <div>
                                            <label class="block text-xs font-medium text-gray-500 uppercase mb-1">CTA URL</label>
                                            <input type="text" name="content[{{ $code }}][cta_url]"
                                                   value="{{ $content['cta_url'] ?? '' }}"
                                                   class="w-full rounded-lg border-gray-300 text-sm focus:border-[#0B3A68] focus:ring-[#0B3A68]">
                                        </div>
                                    @else
                                        <div class="p-4 bg-yellow-50 border border-yellow-200 rounded text-sm text-yellow-800">
                                            Complex structure detected. Use JSON editor below.
                                            <textarea name="content[{{ $code }}]" rows="8" class="mt-2 w-full font-mono text-xs border rounded p-2">{{ json_encode($content, JSON_PRETTY_PRINT) }}</textarea>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

            </div>

            {{-- Right Column: Live Preview --}}
            <div class="lg:col-span-1">
                <div class="preview-panel bg-gray-50 rounded-lg border border-gray-200 p-4">
                    <h3 class="text-sm font-medium text-gray-900 mb-3">Live Preview</h3>
                    <div id="livePreview" class="bg-white rounded border border-gray-300 p-3 text-sm min-h-64 overflow-auto">
                        <p class="text-gray-400 text-xs">Preview will update as you type...</p>
                    </div>
                </div>
            </div>

        </div>

        <div class="mt-8 pt-6 border-t border-gray-200 flex items-center justify-end gap-4">
            <a href="{{ route('admin.cms.sections.index') }}" class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</a>
            <button type="submit" class="px-6 py-2 bg-[#0B3A68] text-white rounded-lg text-sm font-medium hover:bg-blue-900 shadow-sm">Save Changes</button>
        </div>
    </form>
</div>

@push('scripts')
<script>
    function sectionEditor() {
        return {
            updatePreview() {
                const content = document.querySelector('input[name="content[en][headline]"]')?.value || '';
                document.getElementById('livePreview').innerHTML = `<div class="prose prose-sm max-w-none">${content}</div>`;
            },
            init() {
                document.addEventListener('input', () => this.updatePreview());
                this.updatePreview();
            }
        }
    }
</script>
@endpush

@endsection
