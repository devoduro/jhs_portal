@extends('components.student-app-layout')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
            <div class="p-6">
                <div class="border border-gray-300 rounded-lg overflow-hidden relative">
                    <style>
                        .transcript-container {
                            max-width: 1000px;
                            margin: 0 auto;
                            background: white;
                            padding: 20px;
                            position: relative;
                            font-family: Arial, sans-serif;
                            color: #333;
                        }
                        .header {
                            text-align: center;
                            margin-bottom: 20px;
                        }
                        .header h1 {
                            font-size: 24px;
                            margin: 10px 0 5px;
                            text-transform: uppercase;
                        }
                        .header h2 {
                            font-size: 20px;
                            margin-bottom: 10px;
                            color: #444;
                        }
                        .header p {
                            font-size: 12px;
                            margin-bottom: 5px;
                        }
                        .student-info {
                            width: 100%;
                            border-collapse: collapse;
                            margin-bottom: 20px;
                        }
                        .student-info td {
                            padding: 5px;
                            border: 1px solid #ddd;
                        }
                        .student-info .label {
                            font-weight: bold;
                            width: 20%;
                            background-color: #f5f5f5;
                        }
                        .semester-header {
                            background: #007bff;
                            color: white;
                            padding: 8px 10px;
                            font-weight: bold;
                            margin: 15px 0 5px;
                        }
                        .results-table {
                            width: 100%;
                            border-collapse: collapse;
                            margin-bottom: 15px;
                        }
                        .results-table th,
                        .results-table td {
                            padding: 8px;
                            text-align: left;
                            border: 1px solid #ddd;
                            font-size: 12px;
                        }
                        .results-table th {
                            background: #f0f0f0;
                            font-weight: bold;
                        }
                        .summary-section {
                            margin: 20px 0;
                        }
                        .summary-table {
                            width: 100%;
                            border-collapse: collapse;
                        }
                        .summary-table td {
                            padding: 8px;
                            border: 1px solid #ddd;
                        }
                        .grading-system {
                            margin: 20px 0;
                        }
                        .grading-system h3 {
                            font-size: 12px;
                            margin-bottom: 10px;
                            text-align: center;
                        }
                        .grading-table {
                            width: 100%;
                            border-collapse: collapse;
                            font-size: 12px;
                        }
                        .grading-table th,
                        .grading-table td {
                            padding: 5px;
                            text-align: center;
                            border: 1px solid #ddd;
                        }
                        .grading-table th {
                            background: #f0f0f0;
                        }
                        .footer {
                            margin-top: 30px;
                            border-top: 1px solid #ddd;
                            padding-top: 15px;
                            text-align: center;
                            font-size: 12px;
                        }
                        .verification {
                            font-size: 11px;
                            font-style: italic;
                            margin-top: 20px;
                        }
                    </style>
                    <div class="transcript-container">
                        <div class="header">
                            <h1>{{ $settings['institution_name'] ?? \App\Models\Setting::get('institution_name', 'Your School Name') }}</h1>
                            <h2>ACADEMIC TRANSCRIPT</h2>
                            <p>
                                {{ $settings['institution_address'] ?? '' }}
                                @if(!empty($settings['institution_phone']))
                                    | Tel: {{ $settings['institution_phone'] }}
                                @endif
                                @if(!empty($settings['institution_email']))
                                    | Email: {{ $settings['institution_email'] }}
                                @endif
                            </p>
                        </div>

                        <table class="student-info">
                            <tr>
                                <td class="label">Name:</td>
                                <td><strong>{{ $student->full_name }}</strong></td>
                                <td class="label">Index Number:</td>
                                <td><strong>{{ $student->index_number }}</strong></td>
                            </tr>
                            <tr>
                                <td class="label">Programme:</td>
                                <td>{{ $student->programme->name ?? 'N/A' }}</td>
                                <td class="label">Date of Issue:</td>
                                <td>{{ now()->format('d/m/Y') }}</td>
                            </tr>
                        </table>

                        @forelse($groupedResults as $academicYear)
                            <div class="semester-header">
                                {{ $academicYear['academic_year']->name }}
                            </div>

                            @foreach($academicYear['semesters'] as $semesterData)
                                <div class="semester-header" style="padding-left: 20px;">
                                    {{ $semesterData['semester']->name }}
                                    @if($semesterData['aggregate'] !== null)
                                        &mdash; Aggregate: {{ $semesterData['aggregate'] }}
                                    @endif
                                </div>

                                <table class="results-table">
                                    <thead>
                                        <tr>
                                            <th>Subject Code</th>
                                            <th>Subject Title</th>
                                            <th>Score</th>
                                            <th>Grade</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($semesterData['results'] as $result)
                                            <tr>
                                                <td>{{ $result->subject->code ?? 'N/A' }}</td>
                                                <td>{{ $result->subject->title ?? 'N/A' }}</td>
                                                <td>{{ $result->score }}</td>
                                                <td>{{ $result->grade }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @endforeach
                        @empty
                            <p class="text-center text-gray-500 py-6">No results available yet.</p>
                        @endforelse

                        <div class="summary-section">
                            <table class="summary-table">
                                <tr>
                                    <td><strong>Aggregate (Best 6):</strong></td>
                                    <td>
                                        @if($aggregate !== null)
                                            {{ $aggregate }}
                                        @else
                                            Aggregate pending &mdash; fewer than 6 subjects graded
                                        @endif
                                    </td>
                                    <td><strong>Standing:</strong></td>
                                    <td>{{ $gradeStanding ?? 'Not Available' }}</td>
                                </tr>
                            </table>
                        </div>

                        <div class="grading-system">
                            <h3>WASSCE GRADING SYSTEM</h3>
                            <table class="grading-table">
                                <tr><th>Grade</th><th>Mark Range</th><th>Grade Value</th></tr>
                                <tr><td>A1</td><td>80-100</td><td>1</td></tr>
                                <tr><td>B2</td><td>75-79</td><td>2</td></tr>
                                <tr><td>B3</td><td>70-74</td><td>3</td></tr>
                                <tr><td>C4</td><td>65-69</td><td>4</td></tr>
                                <tr><td>C5</td><td>60-64</td><td>5</td></tr>
                                <tr><td>C6</td><td>55-59</td><td>6</td></tr>
                                <tr><td>D7</td><td>50-54</td><td>7</td></tr>
                                <tr><td>E8</td><td>45-49</td><td>8</td></tr>
                                <tr><td>F9</td><td>0-44</td><td>9</td></tr>
                            </table>
                        </div>

                        <div class="footer">
                            <div class="verification">
                                <p>This transcript is not valid without the official seal of the institution.</p>
                                <p>Date Issued: {{ now()->format('d/m/Y') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
