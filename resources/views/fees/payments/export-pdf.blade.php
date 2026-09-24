<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Student Fees</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 6px;
            text-align: left;
        }
        th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        .text-right {
            text-align: right;
        }
        .creditor {
            color: #059669;
        }
        .debtor {
            color: #dc2626;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .header h1 {
            color: #333;
            margin: 0;
            padding: 0;
            font-size: 18px;
        }
        .header p {
            color: #666;
            margin: 5px 0 0 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ \App\Models\Setting::get('institution_name', 'School') }} - Pupil Fees</h1>
        <p>{{ $term?->full_name ?? '' }}{{ $classGroup ? ' • ' . $classGroup->name : '' }} &bull; Generated on {{ now()->format('d F Y') }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Admission No.</th>
                <th>Pupil</th>
                <th>Class</th>
                <th class="text-right">Bal. b/f</th>
                <th class="text-right">Term Bill</th>
                <th class="text-right">Paid (Term)</th>
                <th class="text-right">Balance (GH¢)</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $row)
                @php $student = $row['student']; @endphp
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $student->index_number }}</td>
                    <td>{{ $student->full_name }}</td>
                    <td>{{ $student->classGroup->name ?? \App\Support\FormLabel::label($student->level) }}</td>
                    <td class="text-right">{{ number_format($row['brought_forward'], 2) }}</td>
                    <td class="text-right">{{ number_format($row['term_bill'], 2) }}</td>
                    <td class="text-right">{{ number_format($row['term_paid'], 2) }}</td>
                    <td class="text-right {{ $row['status'] }}">{{ number_format($row['balance'], 2) }}</td>
                    <td class="{{ $row['status'] }}">{{ ['debtor' => 'Owing', 'creditor' => 'In credit', 'settled' => 'Paid'][$row['status']] }}</td>
                </tr>
            @endforeach
            <tr>
                <th colspan="4">Totals ({{ $rows->count() }} pupils)</th>
                <th class="text-right">{{ number_format($rows->sum('brought_forward'), 2) }}</th>
                <th class="text-right">{{ number_format($rows->sum('term_bill'), 2) }}</th>
                <th class="text-right">{{ number_format($rows->sum('term_paid'), 2) }}</th>
                <th class="text-right">{{ number_format($rows->sum('balance'), 2) }}</th>
                <th></th>
            </tr>
        </tbody>
    </table>
</body>
</html>
