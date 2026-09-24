<div class="text-center border-b-2 border-gray-800 pb-4 mb-6">
    <h1 class="text-xl font-bold uppercase tracking-wide">{{ $institutionSettings['institution_name'] ?? 'Your School Name' }}</h1>
    <h2 class="text-lg font-semibold text-blue-700 mt-2">ACADEMIC REPORT CARD</h2>
    @if(!empty($institutionSettings['institution_address']))
        <p class="text-xs text-gray-500 mt-1">{{ $institutionSettings['institution_address'] }}</p>
    @endif
</div>

<table class="w-full text-sm border-collapse mb-6">
    <tr>
        <td class="border border-gray-300 px-3 py-2 font-semibold bg-gray-50 w-1/4">Name</td>
        <td class="border border-gray-300 px-3 py-2">{{ $student->full_name }}</td>
        <td class="border border-gray-300 px-3 py-2 font-semibold bg-gray-50 w-1/4">Index Number</td>
        <td class="border border-gray-300 px-3 py-2">{{ $student->index_number }}</td>
    </tr>
    <tr>
        <td class="border border-gray-300 px-3 py-2 font-semibold bg-gray-50">Programme</td>
        <td class="border border-gray-300 px-3 py-2">{{ $student->programme->name ?? 'N/A' }}</td>
        <td class="border border-gray-300 px-3 py-2 font-semibold bg-gray-50">Date Issued</td>
        <td class="border border-gray-300 px-3 py-2">{{ now()->format('M j, Y') }}</td>
    </tr>
</table>

@forelse($groupedResults as $academicYear)
    <div class="bg-blue-600 text-white px-3 py-2 font-semibold mt-4 mb-2 rounded">
        {{ $academicYear['academic_year']->name }}
    </div>

    @foreach($academicYear['semesters'] as $semesterData)
        <div class="bg-gray-100 px-3 py-2 font-medium mb-2 rounded">
            {{ $semesterData['semester']->name }}
        </div>

        <table class="w-full text-sm border-collapse mb-4">
            <thead>
                <tr class="bg-gray-50">
                    <th class="border border-gray-300 px-3 py-2 text-left">Subject Code</th>
                    <th class="border border-gray-300 px-3 py-2 text-left">Subject Title</th>
                    <th class="border border-gray-300 px-3 py-2 text-left">Score</th>
                    <th class="border border-gray-300 px-3 py-2 text-left">Grade</th>
                </tr>
            </thead>
            <tbody>
                @foreach($semesterData['results'] as $result)
                    <tr>
                        <td class="border border-gray-300 px-3 py-2">{{ $result->subject->code ?? 'N/A' }}</td>
                        <td class="border border-gray-300 px-3 py-2">{{ $result->subject->title ?? 'N/A' }}</td>
                        <td class="border border-gray-300 px-3 py-2">{{ $result->score }}</td>
                        <td class="border border-gray-300 px-3 py-2 font-medium {{ $result->grade === 'F9' ? 'text-red-600' : 'text-green-700' }}">{{ $result->grade }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach
@empty
    <p class="text-center text-gray-500 py-8">No results available yet.</p>
@endforelse

<div class="mt-4 border border-gray-300 rounded p-4 bg-gray-50">
    <table class="w-full text-sm">
        <tr>
            <td class="font-semibold">Aggregate (Best 6):</td>
            <td>
                @if($aggregate !== null)
                    {{ $aggregate }}
                @else
                    Pending &mdash; fewer than 6 subjects graded
                @endif
            </td>
            <td class="font-semibold">Standing:</td>
            <td>{{ $gradeStanding ?? 'Not Available' }}</td>
        </tr>
    </table>
</div>

<div class="mt-6 text-xs text-gray-500">
    <p class="font-semibold mb-1">WASSCE Grading Key</p>
    <p>A1/B2/B3 (Excellent) &bull; C4/C5/C6 (Credit) &bull; D7/E8 (Pass) &bull; F9 (Fail)</p>
</div>
