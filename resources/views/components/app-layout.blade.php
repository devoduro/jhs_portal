<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', \App\Models\Setting::get('institution_name', 'Your School Name')) }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            200: '#bae6fd',
                            300: '#7dd3fc',
                            400: '#38bdf8',
                            500: '#0ea5e9',
                            600: '#0284c7',
                            700: '#0369a1',
                            800: '#075985',
                            900: '#0c4a6e',
                        },
                    },
                    fontFamily: {
                        sans: ['Poppins', 'sans-serif'],
                    },
                }
            }
        }
    </script>
    
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Custom Styles -->
    <style>
        [x-cloak] { display: none !important; }

        /* Tailwind's CDN build has no forms plugin, so text boxes, selects and text areas would have
           no border at all unless each one asks for it. Give every field a 1px border by default;
           utilities such as border-0 or border-gray-300 still win because this rule is deliberately low-specificity (but still beats Tailwind's border-width: 0 reset). */
        body :where(input:not([type=checkbox]):not([type=radio]):not([type=file]):not([type=range]):not([type=hidden]):not([type=submit]):not([type=button]), select, textarea) {
            border-width: 1px;
        }

        .gradient-bg {
            background: linear-gradient(135deg, #0c9b13 0%, #0a6107 100%);
        }

        .gradient-text {
            background: linear-gradient(135deg, #0c9c24 0%, #064b07 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .gradient-border {
            border-image: linear-gradient(135deg, #41a10d 0%, #1f9330 100%) 1;
        }

        /* Desktop sidebar collapse (icon-only rail). Scoped to md+ so mobile's
           overlay sidebar always shows full labels regardless of this state. */
        @media (min-width: 768px) {
            .sidebar-collapsed nav a > span,
            .sidebar-collapsed nav button > span > span,
            .sidebar-collapsed nav button > i.fa-chevron-down {
                display: none;
            }

            .sidebar-collapsed nav a,
            .sidebar-collapsed nav button {
                justify-content: center;
                padding-left: 0.5rem;
                padding-right: 0.5rem;
            }

            .sidebar-collapsed nav .pl-8 {
                display: none;
            }

            /* Section labels have no room on the icon-only rail - keep the grouping visible as
               a divider rule instead of dropping it entirely. */
            .sidebar-collapsed nav .nav-heading {
                height: 1px;
                padding: 0;
                margin: 0.75rem 0.5rem;
                overflow: hidden;
                color: transparent;
                background-color: #334155;
            }
        }

        /* Thin, dark scrollbar for the sidebar. */
        .sidebar-scroll { scrollbar-width: thin; scrollbar-color: #334155 transparent; }
        .sidebar-scroll::-webkit-scrollbar { width: 6px; }
        .sidebar-scroll::-webkit-scrollbar-thumb { background: #334155; border-radius: 3px; }
    </style>
    
    @stack('styles')
</head>
<body class="font-sans antialiased bg-gray-50">
    <div
        x-data="{ sidebarOpen: false, sidebarCollapsed: localStorage.getItem('sidebarCollapsed') === 'true' }"
        class="min-h-screen flex"
    >
        <!-- Sidebar -->
        <aside
            x-cloak
            :class="{
                'translate-x-0': sidebarOpen,
                '-translate-x-full': !sidebarOpen,
                'md:w-20': sidebarCollapsed,
                'md:w-64': !sidebarCollapsed,
                'sidebar-collapsed': sidebarCollapsed,
            }"
            class="fixed inset-y-0 left-0 z-50 w-64 bg-slate-900 border-r border-slate-800 shadow-lg transform transition-all duration-300 ease-in-out md:translate-x-0 md:sticky md:top-0 md:h-screen md:shadow-none flex-shrink-0 flex flex-col"
        >
            <!-- Collapse toggle (desktop only) -->
            <button
                @click="sidebarCollapsed = !sidebarCollapsed; localStorage.setItem('sidebarCollapsed', sidebarCollapsed)"
                class="hidden md:flex absolute -right-3 top-20 w-6 h-6 items-center justify-center rounded-full bg-slate-800 border border-slate-600 shadow-sm text-slate-300 hover:text-white hover:border-primary-400 transition-colors z-10"
                :title="sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'"
            >
                <i class="fas text-[10px]" :class="sidebarCollapsed ? 'fa-chevron-right' : 'fa-chevron-left'"></i>
            </button>

            <!-- Logo -->
            @php
                $institutionName = \App\Models\Setting::get('institution_name', 'Your School Name');
                $institutionLogo = \App\Models\Setting::get('institution_logo');
                $sidebarLogo = $institutionLogo && @filesize(storage_path('app/public/' . $institutionLogo))
                    ? asset('storage/' . $institutionLogo)
                    : (@filesize(public_path('images/logos/institution_logo.png')) ? asset('images/logos/institution_logo.png') : null);
                $initials = \Illuminate\Support\Str::of($institutionName)->replace(['[', ']'], '')->explode(' ')->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
            @endphp
            <a href="{{ route('dashboard') }}" class="flex h-16 shrink-0 items-center gap-3 border-b border-slate-800 px-4 overflow-hidden" :class="sidebarCollapsed ? 'md:justify-center' : ''">
                @if($sidebarLogo)
                    <img src="{{ $sidebarLogo }}" alt="" class="h-9 w-9 shrink-0 rounded-lg bg-white object-contain p-0.5">
                @else
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg gradient-bg text-sm font-bold text-white">{{ $initials }}</span>
                @endif
                <span class="min-w-0" x-show="!sidebarCollapsed" x-cloak>
                    <span class="block truncate text-base font-bold leading-tight text-white">{{ $institutionName }}</span>
                    <span class="block text-[11px] text-slate-400">School management</span>
                </span>
            </a>

            <!-- Navigation -->
            <nav class="sidebar-scroll flex-1 space-y-1 overflow-y-auto p-4">
                @include('components.partials.sidebar-nav')
            </nav>

            <!-- Signed-in user -->
            <div class="shrink-0 border-t border-slate-800 p-3">
                <a href="{{ route('profile') }}" class="flex items-center gap-3 rounded-lg px-2 py-2 text-slate-300 transition hover:bg-slate-800 hover:text-white" :class="sidebarCollapsed ? 'md:justify-center' : ''">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary-600 text-sm font-semibold text-white">{{ mb_strtoupper(mb_substr(auth()->user()->name ?? 'U', 0, 1)) }}</span>
                    <span class="min-w-0" x-show="!sidebarCollapsed" x-cloak>
                        <span class="block truncate text-sm font-medium text-white">{{ auth()->user()->name }}</span>
                        <span class="block truncate text-xs text-slate-400">{{ auth()->user()->getRoleNames()->first() ?? 'Staff' }}</span>
                    </span>
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col min-h-screen min-w-0">
            <!-- Top Navigation -->
            <header class="bg-white border-b border-gray-200 shadow-sm">
                <div class="flex items-center justify-between h-14 px-4 md:px-6">
                    <!-- Mobile Menu Button -->
                    <button 
                        @click="sidebarOpen = !sidebarOpen" 
                        class="text-gray-600 md:hidden focus:outline-none"
                    >
                        <i class="fas fa-bars text-lg"></i>
                    </button>
                    
                    <!-- Page Title - Mobile -->
                    <div class="md:hidden font-semibold text-lg text-gray-800">
                        {{ $header ?? __('Dashboard') }}
                    </div>
                    
                    <!-- Pupil search -->
                    @canany(['manage-students', 'manage-fees', 'view-student-directory'])
                    <div class="hidden md:flex md:flex-1 md:max-w-md"
                         x-data="pupilSearch('{{ route('search.pupils') }}')" @click.outside="open = false" @keydown.escape="open = false">
                        <div class="relative w-full">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <i class="fas fa-search text-gray-400" x-show="!loading"></i>
                                <i class="fas fa-spinner fa-spin text-gray-400" x-show="loading" x-cloak></i>
                            </div>
                            <input
                                type="search"
                                x-model="q"
                                @input.debounce.250ms="search()"
                                @focus="open = results.length > 0"
                                @keydown.arrow-down.prevent="move(1)"
                                @keydown.arrow-up.prevent="move(-1)"
                                @keydown.enter.prevent="go()"
                                class="w-full py-2 pl-10 pr-4 text-sm text-gray-700 bg-gray-100 border-0 rounded-lg focus:bg-white focus:ring-2 focus:ring-primary-500 focus:outline-none"
                                placeholder="Find a pupil — name, admission no. or parent's phone"
                                aria-label="Find a pupil"
                            >
                            <div x-show="open" x-cloak class="absolute z-40 mt-2 w-full overflow-hidden rounded-lg border border-gray-200 bg-white shadow-lg">
                                <template x-if="results.length === 0">
                                    <p class="px-4 py-3 text-sm text-gray-500">No pupil matches "<span x-text="q"></span>".</p>
                                </template>
                                <template x-for="(pupil, i) in results" :key="pupil.admission">
                                    <div class="flex items-center justify-between gap-3 px-4 py-2.5 text-sm" :class="i === active ? 'bg-primary-50' : 'hover:bg-gray-50'">
                                        <a :href="pupil.url" class="min-w-0 flex-1">
                                            <span class="block truncate font-medium text-gray-800" x-text="pupil.name"></span>
                                            <span class="block truncate text-xs text-gray-500">
                                                <span x-text="pupil.admission"></span> &bull; <span x-text="pupil.class"></span>
                                                <span x-show="pupil.status !== 'active'" class="text-orange-600" x-text="'• ' + (pupil.status === 'graduated' ? 'completed' : pupil.status)"></span>
                                            </span>
                                        </a>
                                        <span class="flex shrink-0 gap-2 text-xs">
                                            <a x-show="pupil.links.fees" :href="pupil.links.fees" class="rounded bg-green-50 px-2 py-1 font-medium text-green-700 hover:bg-green-100" title="Fees"><i class="fas fa-cash-register"></i></a>
                                            <a x-show="pupil.links.report" :href="pupil.links.report" target="_blank" class="rounded bg-indigo-50 px-2 py-1 font-medium text-indigo-700 hover:bg-indigo-100" title="Terminal report"><i class="fas fa-file-lines"></i></a>
                                        </span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                    @else
                    <div class="hidden md:flex md:flex-1"></div>
                    @endcanany

                    <!-- Right Navigation -->
                    <div class="flex items-center space-x-4">
                        <!-- User Menu -->
                        <div x-data="{ open: false }" class="relative">
                            <button 
                                @click="open = !open" 
                                class="flex items-center space-x-2 focus:outline-none"
                            >
                                <div class="w-8 h-8 overflow-hidden rounded-full bg-primary-100 flex items-center justify-center">
                                    <i class="fas fa-user text-primary-600"></i>
                                </div>
                                <span class="hidden md:block text-sm font-medium text-gray-700">{{ Auth::user()->name ?? 'User' }}</span>
                                <i class="hidden md:block fas fa-chevron-down text-xs text-gray-400"></i>
                            </button>
                            
                            <!-- Dropdown -->
                            <div 
                                x-show="open" 
                                @click.away="open = false" 
                                x-transition 
                                class="absolute right-0 z-10 w-48 mt-2 origin-top-right bg-white border border-gray-200 rounded-lg shadow-lg"
                            >
                                <div class="p-2">
                                    <a href="{{ route('profile') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-primary-600 rounded-md">
                                        <i class="fas fa-user-circle mr-2"></i> Profile
                                    </a>
                                    <a href="{{ route('settings.index') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-primary-600 rounded-md">
                                        <i class="fas fa-cog mr-2"></i> Settings
                                    </a>
                                    <hr class="my-1 border-gray-200">
                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <button type="submit" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-primary-600 rounded-md">
                                            <i class="fas fa-sign-out-alt mr-2"></i> Logout
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </header>
            
            <!-- Page Content -->
            <main class="flex-1 flex flex-col min-w-0 px-4 md:px-6 py-4">
                <!-- Flash Messages -->
                @if (session('success'))
                    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)" class="p-3 bg-green-50 border-l-4 border-green-500 text-green-700 flex justify-between items-center">
                        <div>
                            <i class="fas fa-check-circle mr-2"></i>
                            {{ session('success') }}
                        </div>
                        <button @click="show = false" class="text-green-700 hover:text-green-900">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                @endif
                
                @if (session('error'))
                    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)" class="p-3 bg-red-50 border-l-4 border-red-500 text-red-700 flex justify-between items-center">
                        <div>
                            <i class="fas fa-exclamation-circle mr-2"></i>
                            {{ session('error') }}
                        </div>
                        <button @click="show = false" class="text-red-700 hover:text-red-900">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                @endif

                @if (session('warning'))
                    <div x-data="{ show: true }" x-show="show" class="p-3 bg-yellow-50 border-l-4 border-yellow-500 text-yellow-700 flex justify-between items-center">
                        <div>
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            {{ session('warning') }}
                        </div>
                        <button @click="show = false" class="text-yellow-700 hover:text-yellow-900">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                @endif

                <!-- Page Header -->
                @if (isset($header))
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mt-2">
                        <div class="p-3 bg-white border-b border-gray-200">
                            {{ $header }}
                        </div>
                    </div>
                @else
                    <div class="py-2 mt-1">
                        <h1 class="text-2xl font-bold text-gray-800">@yield('title', 'Dashboard')</h1>
                        <p class="text-sm text-gray-500">@yield('subtitle', 'Welcome to ' . \App\Models\Setting::get('institution_name', 'Your School Name'))</p>
                    </div>
                @endif
                
                <!-- Content -->
                @isset($slot)
                    {{ $slot }}
                @else
                    @yield('content')
                @endisset
            </main>
            
            <!-- Footer -->
            <footer class="py-4 px-6 border-t border-gray-200">
                <div class="flex flex-col md:flex-row justify-between items-center">
                    <p class="text-sm text-gray-500">
                        &copy; {{ date('Y') }} {{ \App\Models\Setting::get('institution_name', 'Your School Name') }}. All rights reserved.
                    </p>
                    <div class="mt-2 md:mt-0">
                        <a href="#" class="text-sm text-gray-500 hover:text-primary-600 mr-4">Privacy Policy</a>
                        <a href="#" class="text-sm text-gray-500 hover:text-primary-600">Terms of Service</a>
                    </div>
                </div>
            </footer>
        </div>
    </div>
    
    <script>
        function pupilSearch(endpoint) {
            return {
                q: '', results: [], open: false, loading: false, active: -1,
                async search() {
                    if (this.q.trim().length < 2) { this.results = []; this.open = false; return; }
                    this.loading = true;
                    try {
                        const res = await fetch(endpoint + '?q=' + encodeURIComponent(this.q), { headers: { 'Accept': 'application/json' } });
                        this.results = res.ok ? await res.json() : [];
                    } catch (e) { this.results = []; }
                    this.loading = false; this.active = this.results.length ? 0 : -1; this.open = true;
                },
                move(step) { if (this.results.length) this.active = (this.active + step + this.results.length) % this.results.length; },
                go() { const p = this.results[this.active]; if (p && p.url) window.location = p.url; },
            };
        }
    </script>
    @stack('scripts')
</body>
</html>
