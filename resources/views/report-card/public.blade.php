<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Terminal Report — {{ $student->full_name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    @include('report-card._print-styles')
</head>
<body class="bg-gray-100 font-sans">
    <div class="no-print bg-white border-b border-gray-200 px-6 py-3 flex flex-wrap gap-3 justify-between items-center sticky top-0 z-20 shadow-sm">
        @if($publicLink ?? true)
            <span class="text-sm text-gray-600">Terminal report — shared securely by the school</span>
        @else
            <div class="flex items-center gap-4">
                <a href="{{ route('students.show', $student) }}" class="text-sm text-gray-600 hover:text-blue-600">&larr; Back to {{ $student->full_name }}</a>
                @isset($terms)
                    <form method="GET" action="{{ route('students.report-card', $student) }}">
                        <select name="semester_id" onchange="this.form.submit()" class="text-sm border rounded px-2 py-1">
                            @foreach($terms as $t)
                                <option value="{{ $t->id }}" @selected($selectedTermId === $t->id)>{{ $t->full_name }}</option>
                            @endforeach
                        </select>
                    </form>
                @endisset
            </div>
        @endif
        <button onclick="window.print()" class="text-white rounded-md px-4 py-2 text-sm" style="background:#2563eb;">
            Print / Save as PDF
        </button>
    </div>

    @foreach($cards as $card)
        <div class="report-card-page">
            @include('report-card._card', $card)
        </div>
    @endforeach
</body>
</html>
