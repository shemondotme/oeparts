<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    
    <title>{{ trans_field($message ?? ['en' => 'Maintenance']) }} — {{ config('app.name') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|plus-jakarta-sans:600,700,800" rel="stylesheet" />
    
    <!-- Tailwind CDN for maintenance page only -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        navy: '#0B3A68',
                        amber: '#F59E0B',
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        display: ['Plus Jakarta Sans', 'sans-serif'],
                    },
                },
            },
        }
    </script>

    <style>
        .maintenance-pattern {
            background-color: #0B3A68;
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%231e4a8a' fill-opacity='0.2'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }
    </style>
</head>
<body class="font-sans antialiased">
    <div class="min-h-screen flex items-center justify-center maintenance-pattern px-4 py-12">
        <div class="max-w-2xl w-full">
            <!-- Logo -->
            <div class="text-center mb-8">
                <div class="inline-flex items-center gap-2 px-6 py-3 bg-white/10 rounded-xl backdrop-blur-sm">
                    <span class="font-display font-bold text-2xl text-white">OEMHub</span>
                </div>
            </div>

            <!-- Main Content -->
            <div class="bg-white rounded-2xl shadow-2xl p-8 lg:p-12">
                <!-- Maintenance Icon -->
                <div class="w-20 h-20 mx-auto mb-6 rounded-full bg-amber-100 flex items-center justify-center">
                    <svg class="w-10 h-10 text-amber-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 0 0 4.486-4.19 1.529 1.529 0 0 0-1.529-1.529c-.627 0-1.209.283-1.585.717M12 9V3m0 0a4.978 4.978 0 0 0-3.867 1.802c-.492.59-.824 1.332-.824 2.198 0 .866.332 1.608.824 2.198A4.978 4.978 0 0 0 12 9Zm0 12c-.866 0-1.608-.332-2.198-.824a4.978 4.978 0 0 1-1.802-3.867c0-.866.332-1.608.824-2.198A4.978 4.978 0 0 1 12 15Z" />
                    </svg>
                </div>

                <!-- Title -->
                <h1 class="text-3xl font-display font-bold text-navy text-center mb-4">
                    {{ trans('maintenance.title', [], 'en') }}
                </h1>

                <!-- Message -->
                <p class="text-lg text-slate-600 text-center mb-8">
                    {{ trans_field($message ?? ['en' => "We're currently performing scheduled maintenance. We'll be back shortly."]) }}
                </p>

                <!-- Estimated Time -->
                @if($showEstimatedTime && !empty($estimatedBackAt))
                    <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 mb-6">
                        <div class="flex items-center justify-center gap-2 text-amber-800">
                            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                            <span class="font-medium">
                                {{ trans('maintenance.estimated_return', [], 'en') }}: 
                                <strong>{{ \Carbon\Carbon::parse($estimatedBackAt)->format('M d, Y \a\t H:i') }}</strong>
                            </span>
                        </div>
                    </div>
                @endif

                <!-- Contact Info -->
                @if(!empty($contactEmail))
                    <div class="text-center">
                        <p class="text-sm text-slate-500 mb-2">{{ trans('maintenance.need_help', [], 'en') }}</p>
                        <a href="mailto:{{ $contactEmail }}" class="text-amber-600 hover:text-amber-700 font-medium hover:underline">
                            {{ $contactEmail }}
                        </a>
                    </div>
                @endif

                <!-- Divider -->
                <div class="my-8 border-t border-slate-200"></div>

                <!-- Additional Info -->
                <div class="text-center text-sm text-slate-500">
                    <p>{{ trans('maintenance.check_back', [], 'en') }}</p>
                </div>
            </div>

            <!-- Footer -->
            <div class="mt-8 text-center text-white/60 text-sm">
                <p>&copy; {{ date('Y') }} {{ config('app.name') }}. {{ trans('maintenance.all_rights_reserved', [], 'en') }}</p>
            </div>
        </div>
    </div>
</body>
</html>
