<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Class List - {{ $classGroup->name }}</title>
    <style>
        @page { margin: 14mm 12mm; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10px; color: #111827; }
        .head { text-align: center; border-bottom: 2px solid #1f2937; padding-bottom: 8px; margin-bottom: 10px; }
        .head img { max-width: 60px; max-height: 60px; margin-bottom: 4px; }
        .head h1 { font-size: 15px; margin: 0; text-transform: uppercase; letter-spacing: .5px; }
        .head .addr { font-size: 9px; color: #6b7280; margin-top: 2px; }
        .head h2 { font-size: 13px; color: #0369a1; margin: 8px 0 0; }
        table.meta { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        table.meta td { padding: 2px 0; vertical-align: top; }
        .label { color: #6b7280; }
        table.list { width: 100%; border-collapse: collapse; }
        table.list th, table.list td { border: 1px solid #9ca3af; padding: 4px 6px; text-align: left; }
        table.list th { background: #f3f4f6; }
        table.list thead { display: table-header-group; }
        table.list tr { page-break-inside: avoid; }
        table.list td.blank { height: 16px; }
        .empty { text-align: center; color: #9ca3af; padding: 16px; }
        .foot { margin-top: 10px; font-size: 9px; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>
    <div class="head">
        @if($logoBase64)<img src="{{ $logoBase64 }}" alt="Logo"><br>@endif
        <h1>{{ $settings['institution_name'] ?? 'School' }}</h1>
        @if(!empty($settings['institution_address']))<div class="addr">{{ $settings['institution_address'] }}</div>@endif
        <h2>{{ $title ?: 'Class List' }} &mdash; {{ $classGroup->name }}</h2>
    </div>

    <table class="meta">
        <tr>
            <td width="50%"><span class="label">Class:</span> <strong>{{ $classGroup->name }}</strong> ({{ \App\Support\FormLabel::label($classGroup->level) }})</td>
            <td width="50%"><span class="label">Class teacher:</span> <strong>{{ $classGroup->classTeacher->name ?? '-' }}</strong></td>
        </tr>
        <tr>
            <td><span class="label">Term:</span> <strong>{{ $term->name ?? '-' }}{{ $term?->academicYear ? ', ' . $term->academicYear->name : '' }}</strong></td>
            <td><span class="label">Printed:</span> <strong>{{ now()->format('F j, Y') }}</strong> <span class="label">by {{ $printedBy }}</span></td>
        </tr>
        <tr>
            <td><span class="label">On roll:</span> <strong>{{ $summary['total'] }}</strong>
                <span class="label">&nbsp;(Boys {{ $summary['boys'] }}, Girls {{ $summary['girls'] }})</span></td>
            <td>@if($gender || $students->count() !== $summary['total'])<span class="label">Showing:</span> <strong>{{ $students->count() }}</strong>@if($gender) <span class="label">({{ $gender === 'Male' ? 'boys' : 'girls' }} only)</span>@endif @endif</td>
        </tr>
    </table>

    @php $labels = \App\Http\Controllers\ClassListController::COLUMNS; @endphp
    <table class="list">
        <thead>
            <tr>
                <th width="22">#</th>
                <th>Full Name</th>
                @foreach($columns as $col)
                    <th @if($col === 'signature' || $col === 'remarks') width="90" @endif>{{ $labels[$col] }}</th>
                @endforeach
                @for($i = 1; $i <= $blank; $i++)<th width="18"></th>@endfor
            </tr>
        </thead>
        <tbody>
            @forelse($students as $index => $student)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $student->full_name }}</td>
                    @foreach($columns as $col)
                        <td class="{{ in_array($col, ['signature', 'remarks']) ? 'blank' : '' }}">
                            @switch($col)
                                @case('index_number'){{ $student->index_number }}@break
                                @case('gender'){{ $student->gender }}@break
                                @case('date_of_birth'){{ $student->date_of_birth?->format('d/m/Y') }}@break
                                @case('parent'){{ $student->emergency_contact_name }}@break
                                @case('phone'){{ $student->emergency_contact_phone }}@break
                                @case('house'){{ $student->house?->name }}@break
                            @endswitch
                        </td>
                    @endforeach
                    @for($i = 1; $i <= $blank; $i++)<td class="blank"></td>@endfor
                </tr>
            @empty
                <tr><td colspan="{{ 2 + count($columns) + $blank }}" class="empty">No pupils match.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
