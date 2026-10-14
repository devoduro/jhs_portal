<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Pupils List</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 14px; }
        th, td { border: 1px solid #ddd; padding: 5px 6px; text-align: left; }
        th { background-color: #f8f9fa; font-weight: bold; }
        tr:nth-child(even) { background-color: #f6f6f6; }
        .header { text-align: center; margin-bottom: 14px; }
        .header h1 { color: #333; margin: 0; font-size: 16px; }
        .header p { color: #666; margin: 4px 0 0 0; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Pupils List</h1>
        <p>{{ $students->count() }} pupil(s) &middot; generated on {{ now()->format('F d, Y') }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Admission No.</th>
                <th>Full Name</th>
                <th>Gender</th>
                <th>Class</th>
                <th>Parent / Guardian</th>
                <th>Parent Phone</th>
            </tr>
        </thead>
        <tbody>
            @foreach($students as $student)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $student->index_number }}</td>
                    <td>{{ $student->full_name }}</td>
                    <td>{{ $student->gender }}</td>
                    <td>{{ $student->classGroup->name ?? \App\Support\FormLabel::label($student->level) }}</td>
                    <td>{{ $student->emergency_contact_name }}</td>
                    <td>{{ $student->emergency_contact_phone }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
