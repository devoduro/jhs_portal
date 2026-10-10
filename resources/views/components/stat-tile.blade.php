@props([
    'color' => 'blue',     // blue | green | orange | red | slate | purple
    'icon' => 'fa-chart-simple',
    'title',
    'value',
    'sub' => null,         // small caption under the title (e.g. "This year")
    'badge' => null,       // small pill on the right (e.g. "86% of billed")
    'href' => null,
])
@php
    // Flat, two-tone tiles: a darker icon block beside a solid colour body.
    $palette = [
        'blue'   => ['body' => 'bg-[#42a5dc]', 'icon' => 'bg-[#2c8fc6]'],
        'green'  => ['body' => 'bg-[#8cc152]', 'icon' => 'bg-[#74a83b]'],
        'orange' => ['body' => 'bg-[#f5922f]', 'icon' => 'bg-[#de7a14]'],
        'red'    => ['body' => 'bg-[#da4453]', 'icon' => 'bg-[#c12f3e]'],
        'slate'  => ['body' => 'bg-[#5b6f8f]', 'icon' => 'bg-[#4a5c7a]'],
        'purple' => ['body' => 'bg-[#967adc]', 'icon' => 'bg-[#7d62c4]'],
    ][$color] ?? ['body' => 'bg-[#42a5dc]', 'icon' => 'bg-[#2c8fc6]'];
    $tag = $href ? 'a' : 'div';
    // Long values (e.g. "GH₵ 108,360.00") get a smaller size so they never clip in a 4-across row.
    $valueLength = mb_strlen(trim(strip_tags((string) $value)));
    $valueSize = $valueLength > 12 ? 'text-base xl:text-lg' : ($valueLength > 9 ? 'text-lg xl:text-xl' : 'text-2xl');
@endphp
<{{ $tag }} @if($href) href="{{ $href }}" @endif
    {{ $attributes->merge(['class' => 'group flex overflow-hidden rounded-md text-white shadow-sm transition ' . ($href ? 'hover:shadow-md hover:-translate-y-0.5' : '')]) }}>
    <span class="flex w-16 shrink-0 items-center justify-center {{ $palette['icon'] }}">
        <i class="fas {{ $icon }} text-2xl"></i>
    </span>
    <span class="flex min-w-0 flex-1 flex-wrap items-center justify-between gap-x-3 gap-y-1 px-4 py-3 {{ $palette['body'] }}">
        <span class="min-w-0 flex-1 basis-28">
            <span class="block text-base font-semibold leading-tight">{{ $title }}</span>
            @if($sub)<span class="block truncate text-xs opacity-90">{{ $sub }}</span>@endif
        </span>
        <span class="text-right">
            <span class="block whitespace-nowrap {{ $valueSize }} font-bold leading-tight tracking-tight">{{ $value }}</span>
            @if($badge)<span class="mt-0.5 inline-block whitespace-nowrap rounded-full bg-white/90 px-2 py-0.5 text-[11px] font-semibold text-gray-700">{{ $badge }}</span>@endif
        </span>
    </span>
</{{ $tag }}>
