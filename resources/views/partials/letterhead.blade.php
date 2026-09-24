{{-- School letterhead for printed documents. Expects $settings (institution settings) and optionally $title. --}}
@php
    // The logo uploaded under Settings > Institution Profile, else the bundled default.
    $uploaded = \App\Models\Setting::get('institution_logo');
    $logoFile = $uploaded && is_file(storage_path('app/public/' . $uploaded))
        ? storage_path('app/public/' . $uploaded)
        : public_path('images/logos/institution_logo.png');
    $logoBase64 = is_file($logoFile)
        ? 'data:' . (mime_content_type($logoFile) ?: 'image/png') . ';base64,' . base64_encode(file_get_contents($logoFile))
        : null;
    $logoPx = $logoSize ?? 70;
@endphp
<div class="flex items-center gap-4 border-b-2 border-gray-800 pb-3 mb-4">
    @if($logoBase64)
        <img src="{{ $logoBase64 }}" alt="School crest" style="width: {{ $logoPx }}px; height: {{ $logoPx }}px; object-fit: contain;">
    @endif
    <div class="flex-1 text-center">
        <h1 class="text-xl font-extrabold uppercase tracking-wide leading-tight">{{ $settings['institution_name'] ?? 'Your School Name' }}</h1>
        @if(!empty($settings['institution_address']))
            <p class="text-xs text-gray-600">{{ $settings['institution_address'] }}</p>
        @endif
        <p class="text-xs text-gray-600">
            {{ collect([$settings['institution_phone'] ?? $settings['institution_contact'] ?? null, $settings['institution_email'] ?? null])->filter()->implode(' | ') }}
        </p>
        @if(!empty($settings['institution_slogan']))
            <p class="text-xs italic text-gray-500">"{{ $settings['institution_slogan'] }}"</p>
        @endif
        @isset($title)
            <h2 class="mt-2 inline-block px-4 py-0.5 bg-gray-800 text-white text-sm font-bold uppercase tracking-widest">{{ $title }}</h2>
        @endisset
    </div>
    @if($logoBase64)
        <div style="width: {{ $logoPx }}px;"></div>
    @endif
</div>
