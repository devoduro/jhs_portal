@props(['title' => 'Online Admission'])
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} — {{ \App\Models\Setting::get('institution_name', 'Online Admission') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 font-sans min-h-screen flex flex-col">
    <header class="bg-white border-b border-gray-200 px-6 py-4">
        <div class="max-w-3xl mx-auto flex items-center justify-between">
            <span class="font-bold text-lg text-gray-800 uppercase tracking-wide">{{ \App\Models\Setting::get('institution_name', 'Online Admission') }}</span>
            <span class="text-sm text-gray-500">Online Admission Portal</span>
        </div>
    </header>

    <main class="flex-1 py-10 px-4">
        <div class="max-w-3xl mx-auto">
            @if(session('success'))
                <div class="bg-green-50 border border-green-200 text-green-700 rounded-md px-4 py-3 text-sm mb-6">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="bg-red-50 border border-red-200 text-red-700 rounded-md px-4 py-3 text-sm mb-6">{{ session('error') }}</div>
            @endif
            @if($errors->any())
                <div class="bg-red-50 border border-red-200 text-red-700 rounded-md px-4 py-3 text-sm mb-6">
                    <ul class="list-disc list-inside">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{ $slot }}
        </div>
    </main>

    <footer class="text-center text-xs text-gray-400 py-6">
        &copy; {{ date('Y') }} {{ \App\Models\Setting::get('institution_name', '') }}. All rights reserved.
    </footer>
</body>
</html>
