@props([
    'title' => 'Welcome back',
    'subtitle' => 'Please enter your details',
    'pageTitle' => 'Sign in',
    'tagline' => 'Fees, scores and terminal reports — all in one place.',
])
{{-- Split-screen sign-in layout: form on the left, illustrated brand panel on the right. --}}
@php
    $school = \App\Models\Setting::get('institution_name', 'Your School Name');
    $logo = \App\Models\Setting::get('institution_logo');
    // Uploaded logo (Settings > Institution Profile), else the bundled crest. Empty files are skipped.
    $logoUrl = null;
    if ($logo && @filesize(storage_path('app/public/' . $logo))) {
        $logoUrl = asset('storage/' . $logo);
    } else {
        foreach (['images/logos/institution_logo.png', 'images/logos/logo.png'] as $candidate) {
            if (@filesize(public_path($candidate))) {
                $logoUrl = asset($candidate);
                break;
            }
        }
    }
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $pageTitle }} — {{ $school }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { theme: { extend: {
            fontFamily: { sans: ['Inter', 'ui-sans-serif', 'system-ui'] },
            colors: { brand: { 50: '#f4f0fb', 100: '#e9e1f7', 300: '#c4b1ea', 400: '#a78bdf', 500: '#8b6bd1', 600: '#6a4aa8', 700: '#5a3d93', 800: '#462f73' } },
        } } };
    </script>
</head>
<body class="min-h-screen bg-white font-sans text-gray-900 antialiased">
<div class="flex min-h-screen">
    <!-- Form side -->
    <div class="flex w-full flex-col px-6 py-8 sm:px-10 lg:w-1/2 xl:px-16">
        <a href="{{ url('/') }}" class="flex items-center gap-3">
            @if($logoUrl)
                <img src="{{ $logoUrl }}" alt="" class="h-11 w-11 rounded-lg object-contain">
            @else
                <span class="flex h-11 w-11 items-center justify-center rounded-lg bg-brand-600 text-white"><i class="fas fa-school"></i></span>
            @endif
            <span class="text-xl font-semibold tracking-tight text-gray-900">{{ $school }}</span>
        </a>

        <div class="flex flex-1 items-center justify-center py-10">
            <div class="w-full max-w-sm">
                <h1 class="text-4xl font-extrabold tracking-tight text-gray-900">{{ $title }}</h1>
                <p class="mt-2 text-gray-500">{{ $subtitle }}</p>

                @if (session('status'))
                    <div class="mt-6 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
                @endif
                @if (session('success'))
                    <div class="mt-6 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
                @endif
                @if (session('error'))
                    <div class="mt-6 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">{{ session('error') }}</div>
                @endif
                @if ($errors->any())
                    <div class="mt-6 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
                        @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                    </div>
                @endif

                <div class="mt-8">
                    {{ $slot }}
                </div>

                @isset($footer)
                    <div class="mt-8 text-center text-sm text-gray-500">{{ $footer }}</div>
                @endisset
            </div>
        </div>

        <p class="text-xs text-gray-400">&copy; {{ date('Y') }} {{ $school }}. All rights reserved.</p>
    </div>

    <!-- Illustration side -->
    <div class="relative hidden overflow-hidden bg-brand-400 lg:flex lg:w-1/2 lg:flex-col lg:items-center lg:justify-center">
        <svg class="w-full max-w-xl px-10" viewBox="0 0 600 560" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <g stroke="#6a4aa8" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" opacity=".55">
                <!-- floating doodles: x's, circles, dashes -->
                <path d="M70 60l12 12M82 60L70 72M520 70l12 12M532 70l-12 12M470 200l10 10M480 200l-10 10M120 260l10 10M130 260l-10 10M300 30l10 10M310 30l-10 10"/>
                <circle cx="40" cy="160" r="7"/><circle cx="560" cy="150" r="7"/><circle cx="430" cy="40" r="6"/><circle cx="200" cy="40" r="6"/><circle cx="560" cy="300" r="6"/>
                <path d="M240 70h22M360 60h22M150 120h18M500 250h22M60 330h18M380 250h16"/>
                <!-- speech bubble with 'i' -->
                <path d="M60 90h90a10 10 0 0110 10v50a10 10 0 01-10 10H95l-20 20v-20H60a10 10 0 01-10-10v-50a10 10 0 0110-10z"/>
                <path d="M105 112v3M105 122v22"/>
                <!-- chat bubble dots -->
                <path d="M470 110a40 32 0 1180 0a40 32 0 01-40 32l-18 14v-17a40 32 0 01-22-29z"/>
                <circle cx="495" cy="110" r="4"/><circle cx="510" cy="110" r="4"/><circle cx="525" cy="110" r="4"/>
                <!-- open book -->
                <path d="M180 200c25-12 55-12 80 0v62c-25-12-55-12-80 0zM260 200c25-12 55-12 80 0v62c-25-12-55-12-80 0z"/>
                <path d="M200 215h40M200 228h40M280 215h40M280 228h40"/>
                <!-- calculator / report -->
                <rect x="380" y="170" width="70" height="90" rx="8"/>
                <path d="M395 190h40M395 210h10M415 210h10M435 210h0M395 228h10M415 228h10M395 246h40"/>
                <!-- envelope -->
                <rect x="60" y="380" width="96" height="66" rx="4"/><path d="M60 384l48 36 48-36"/>
                <!-- receipt with cedi -->
                <path d="M470 360h60v86l-10-7-10 7-10-7-10 7-10-7-10 7z"/><path d="M485 380h30M485 395h30M485 410h18"/>
            </g>

            <!-- graduation cap -->
            <g transform="translate(225 300)">
                <path d="M75 0L0 32l75 32 75-32z" fill="#462f73"/>
                <path d="M30 46v32c0 14 20 26 45 26s45-12 45-26V46l-45 18z" fill="#5a3d93"/>
                <path d="M140 36v42" stroke="#462f73" stroke-width="4" stroke-linecap="round"/>
                <circle cx="140" cy="84" r="7" fill="#f5c542"/>
            </g>

            <!-- big check badge -->
            <g transform="translate(120 450)">
                <rect x="0" y="0" width="150" height="70" rx="35" stroke="#e9e1f7" stroke-width="3" fill="none"/>
                <circle cx="35" cy="35" r="27" fill="#fff"/>
                <path d="M22 35l9 9 18-20" stroke="#6a4aa8" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
            </g>

            <!-- monitor with report card -->
            <g transform="translate(300 410)">
                <rect x="0" y="0" width="190" height="120" rx="12" fill="#1f1640"/>
                <rect x="12" y="12" width="166" height="96" rx="6" fill="#fff"/>
                <rect x="24" y="24" width="70" height="8" rx="4" fill="#8b6bd1"/>
                <rect x="24" y="42" width="142" height="5" rx="2.5" fill="#e9e1f7"/>
                <rect x="24" y="54" width="142" height="5" rx="2.5" fill="#e9e1f7"/>
                <rect x="24" y="66" width="100" height="5" rx="2.5" fill="#e9e1f7"/>
                <rect x="24" y="84" width="30" height="14" rx="3" fill="#8cc152"/>
                <rect x="60" y="78" width="30" height="20" rx="3" fill="#42a5dc"/>
                <rect x="96" y="72" width="30" height="26" rx="3" fill="#f5922f"/>
                <rect x="132" y="66" width="30" height="32" rx="3" fill="#6a4aa8"/>
                <path d="M80 120h30l6 22H74z" fill="#c4b1ea"/>
            </g>
            <!-- squiggles -->
            <path d="M30 520c15-15 25 15 40 0s25 15 40 0" stroke="#fff" stroke-width="2.5" stroke-linecap="round" opacity=".8"/>
            <path d="M500 330c15-15 25 15 40 0s25 15 40 0" stroke="#fff" stroke-width="2.5" stroke-linecap="round" opacity=".8"/>
        </svg>

        <div class="mt-6 max-w-md px-10 text-center text-white">
            <p class="text-2xl font-bold">{{ $school }}</p>
            <p class="mt-2 text-brand-50/90">{{ $tagline }}</p>
        </div>
    </div>
</div>

<script>
    // Show / hide password toggles.
    document.querySelectorAll('[data-toggle-password]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = document.getElementById(btn.dataset.togglePassword);
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.querySelector('i').className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
            btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        });
    });
    // Prevent double submits and show progress.
    document.querySelectorAll('form[data-auth-form]').forEach(function (form) {
        form.addEventListener('submit', function () {
            var b = form.querySelector('button[type=submit]');
            b.disabled = true;
            b.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Please wait…';
        });
    });
</script>
</body>
</html>
