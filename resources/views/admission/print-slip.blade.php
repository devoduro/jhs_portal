<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admission Slip — {{ $application->full_name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
        }
    </style>
</head>
<body class="bg-gray-100 font-sans">
    <div class="no-print bg-white border-b border-gray-200 px-6 py-3 flex justify-between items-center sticky top-0 shadow-sm">
        <span class="text-sm text-gray-600">{{ $application->status === 'admitted' ? 'Admission Slip' : 'Provisional Admission Slip' }}</span>
        <button onclick="window.print()" class="bg-blue-600 text-white rounded-md px-4 py-2 text-sm hover:bg-blue-700">Print / Save as PDF</button>
    </div>

    <div class="max-w-2xl mx-auto p-8 print:p-0 bg-white my-6 print:my-0 print:shadow-none shadow-md rounded-lg">
        <div class="text-center border-b-2 border-gray-800 pb-4 mb-6">
            <h1 class="text-xl font-bold uppercase tracking-wide">{{ \App\Models\Setting::get('institution_name', 'Your School Name') }}</h1>
            <h2 class="text-lg font-semibold text-blue-700 mt-2">
                {{ $application->status === 'admitted' ? 'ADMISSION SLIP' : 'PROVISIONAL ADMISSION SLIP' }}
            </h2>
        </div>

        @if($application->photo_path)
            <img src="{{ asset('storage/'.$application->photo_path) }}" alt="Passport photo" class="w-24 h-24 object-cover rounded border border-gray-300 mb-4">
        @endif

        <table class="w-full text-sm border-collapse mb-6">
            <tr>
                <td class="border border-gray-300 px-3 py-2 font-semibold bg-gray-50 w-1/3">Name</td>
                <td class="border border-gray-300 px-3 py-2">{{ $application->full_name }}</td>
            </tr>
            <tr>
                <td class="border border-gray-300 px-3 py-2 font-semibold bg-gray-50">Index Number</td>
                <td class="border border-gray-300 px-3 py-2">{{ $application->index_number }}</td>
            </tr>
            <tr>
                <td class="border border-gray-300 px-3 py-2 font-semibold bg-gray-50">Programme</td>
                <td class="border border-gray-300 px-3 py-2">{{ $application->programme->name ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td class="border border-gray-300 px-3 py-2 font-semibold bg-gray-50">Boarding Status</td>
                <td class="border border-gray-300 px-3 py-2">{{ ucfirst($application->boarding_status ?? 'N/A') }}</td>
            </tr>
            <tr>
                <td class="border border-gray-300 px-3 py-2 font-semibold bg-gray-50">JHS Attended</td>
                <td class="border border-gray-300 px-3 py-2">{{ $application->jhs_attended ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td class="border border-gray-300 px-3 py-2 font-semibold bg-gray-50">Status</td>
                <td class="border border-gray-300 px-3 py-2">{{ \App\Models\AdmissionApplication::STATUS_LABELS[$application->status] ?? $application->status }}</td>
            </tr>
            <tr>
                <td class="border border-gray-300 px-3 py-2 font-semibold bg-gray-50">Date Issued</td>
                <td class="border border-gray-300 px-3 py-2">{{ now()->format('M j, Y') }}</td>
            </tr>
        </table>

        <p class="text-xs text-gray-400 text-center mt-8">
            @if($application->status === 'admitted')
                This slip confirms admission. Bring it, along with original certificates, on reporting day.
            @else
                This is a provisional slip pending review by the school. It does not yet confirm admission.
            @endif
        </p>
    </div>
</body>
</html>
