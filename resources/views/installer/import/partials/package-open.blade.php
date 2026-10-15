{{-- The package that is open for this browser: what it says about itself, and the way to pick a different one. --}}
<div class="mb-6 p-5 bg-green-50 border border-green-200 rounded-xl">
    <div class="flex items-start gap-3">
        <x-heroicon-o-lock-open class="w-6 h-6 text-green-600 shrink-0 mt-0.5" />
        <div class="min-w-0 flex-1">
            <h2 class="font-semibold text-green-900">Package opened</h2>
            <p class="font-mono text-sm text-green-900 break-all">{{ $open['package'] }}</p>

            <dl class="mt-4 grid sm:grid-cols-2 gap-x-6 gap-y-2 text-sm">
                @if(! empty($open['summary']['source_domain']))
                <div>
                    <dt class="text-green-800">Made on</dt>
                    <dd class="font-medium text-green-950">{{ $open['summary']['source_domain'] }}</dd>
                </div>
                @endif
                @if(! empty($open['summary']['created_at']))
                <div>
                    <dt class="text-green-800">Made</dt>
                    <dd class="font-medium text-green-950">{{ \Illuminate\Support\Carbon::parse($open['summary']['created_at'])->format('j M Y, H:i') }}</dd>
                </div>
                @endif
                @if(! empty($open['summary']['app_version']))
                <div>
                    <dt class="text-green-800">OeParts version</dt>
                    <dd class="font-medium text-green-950">{{ $open['summary']['app_version'] }}</dd>
                </div>
                @endif
                @if(! empty($open['summary']['size']))
                <div>
                    <dt class="text-green-800">Size</dt>
                    <dd class="font-medium text-green-950">{{ \Illuminate\Support\Number::fileSize($open['summary']['size']) }}</dd>
                </div>
                @endif
                @if(! empty($open['summary']['groups']))
                <div class="sm:col-span-2">
                    <dt class="text-green-800">Contains</dt>
                    <dd class="font-medium text-green-950">{{ implode(', ', $open['summary']['groups']) }}</dd>
                </div>
                @endif
                @if(! empty($open['summary']['anonymised']))
                <div class="sm:col-span-2 text-green-900">Personal data in this package was scrambled when it was exported.</div>
                @endif
            </dl>

            <div class="mt-5 flex flex-wrap items-center gap-x-6 gap-y-3">
                <a href="{{ route('installer.import.choose') }}" class="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl font-semibold bg-navy text-white shadow-sm hover:bg-navy/90 transition-all duration-200">
                    Next: choose what to bring
                    <x-heroicon-o-arrow-right class="w-4 h-4 ml-2" />
                </a>

                <form method="POST" action="{{ route('installer.import.package.forget') }}">
                    @csrf
                    <button type="submit" class="text-sm font-semibold text-green-900 underline underline-offset-2 hover:no-underline">
                        Choose another package
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
