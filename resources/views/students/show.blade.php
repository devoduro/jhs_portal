<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $student->full_name }}</h2>
            <div class="flex flex-wrap gap-2">
                <x-button href="{{ route('students.edit', $student) }}" icon="fas fa-edit">Edit</x-button>
                @if(!$student->user)
                    <x-button href="{{ route('students.create-account', $student->id) }}" variant="secondary" icon="fas fa-user-plus">Create Portal Login</x-button>
                @endif
                <x-button href="{{ route('students.index', ['class_group_id' => $student->class_group_id]) }}" variant="secondary" icon="fas fa-arrow-left">Pupils</x-button>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Profile -->
            <div class="space-y-6">
                <div class="bg-white shadow-sm rounded-lg p-6 text-center">
                    <x-student-photo :student="$student" class="w-32 h-32 rounded-full object-cover mx-auto border-4 border-primary-100" />
                    <h3 class="text-xl font-bold text-gray-900 mt-3">{{ $student->full_name }}</h3>
                    <p class="text-sm text-gray-500">{{ $student->index_number }}</p>
                    <p class="mt-2">
                        <span class="px-3 py-1 rounded-full text-xs font-medium {{ $student->status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700' }}">
                            {{ ['active' => 'In school', 'graduated' => 'Completed' . ($student->graduation_year ? ' ' . $student->graduation_year : ''), 'withdrawn' => 'Withdrawn'][$student->status] ?? ucfirst($student->status) }}
                        </span>
                    </p>
                    <dl class="mt-4 text-sm text-left divide-y">
                        <div class="py-2 flex justify-between"><dt class="text-gray-500">Class</dt><dd class="font-medium">{{ $student->classGroup->name ?? \App\Support\FormLabel::label($student->level) . ' (no class)' }}</dd></div>
                        <div class="py-2 flex justify-between"><dt class="text-gray-500">Gender</dt><dd class="font-medium">{{ $student->gender === 'Male' ? 'Boy' : ($student->gender === 'Female' ? 'Girl' : '—') }}</dd></div>
                        <div class="py-2 flex justify-between"><dt class="text-gray-500">Date of birth</dt><dd class="font-medium">{{ $student->date_of_birth?->format('d M Y') ?? '—' }}{{ $student->date_of_birth ? ' (' . $student->date_of_birth->age . ' yrs)' : '' }}</dd></div>
                        <div class="py-2 flex justify-between"><dt class="text-gray-500">Hometown</dt><dd class="font-medium">{{ $student->hometown ?: '—' }}</dd></div>
                    </dl>
                </div>

                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="font-semibold text-gray-900 mb-3"><i class="fas fa-user-friends text-primary-600 mr-2"></i>Parent / Guardian</h3>
                    <dl class="text-sm divide-y">
                        <div class="py-2 flex justify-between"><dt class="text-gray-500">Name</dt><dd class="font-medium">{{ $student->emergency_contact_name ?: '—' }}</dd></div>
                        <div class="py-2 flex justify-between"><dt class="text-gray-500">Relationship</dt><dd class="font-medium">{{ $student->emergency_contact_relationship ?: '—' }}</dd></div>
                        <div class="py-2 flex justify-between"><dt class="text-gray-500">Phone</dt><dd class="font-medium">{{ $student->emergency_contact_phone ?: '—' }}</dd></div>
                        @if($student->phone)<div class="py-2 flex justify-between"><dt class="text-gray-500">Other phone</dt><dd class="font-medium">{{ $student->phone }}</dd></div>@endif
                        @if($student->email)<div class="py-2 flex justify-between"><dt class="text-gray-500">Email</dt><dd class="font-medium">{{ $student->email }}</dd></div>@endif
                        @if($student->address)<div class="py-2"><dt class="text-gray-500">Address</dt><dd class="font-medium">{{ $student->address }} {{ $student->gps_address ? '(' . $student->gps_address . ')' : '' }}</dd></div>@endif
                    </dl>
                </div>
            </div>

            <div class="lg:col-span-2 space-y-6">
                <!-- Fees -->
                @can('manage-fees')
                    <div class="bg-white shadow-sm rounded-lg p-6 flex flex-wrap justify-between items-center gap-4">
                        <div>
                            <p class="text-sm text-gray-500">School fees owing</p>
                            <p class="text-3xl font-bold {{ $balance > 0 ? 'text-red-600' : 'text-green-600' }}">GH₵ {{ number_format($balance, 2) }}</p>
                            @if($balance < 0)<p class="text-xs text-blue-600">In credit</p>@endif
                        </div>
                        <div class="flex gap-2">
                            <x-button href="{{ route('fees.show', $student) }}" icon="fas fa-cash-register">Receive Payment</x-button>
                            <x-button href="{{ route('fees.print', $student) }}" variant="secondary" icon="fas fa-print" target="_blank">Statement</x-button>
                        </div>
                    </div>
                @endcan

                <!-- Terminal reports -->
                <div class="bg-white shadow-sm rounded-lg p-6">
                    <div class="flex flex-wrap justify-between items-end gap-4 mb-4">
                        <h3 class="font-semibold text-gray-900"><i class="fas fa-file-alt text-primary-600 mr-2"></i>Terminal Reports</h3>
                        @canany(['manage-results', 'manage-students', 'view-reports'])
                            <x-button href="{{ route('students.transcript', $student) }}" target="_blank" size="sm" variant="outline-primary" icon="fas fa-scroll">Transcript (all classes)</x-button>
                        @endcanany
                        <div class="flex flex-wrap items-end gap-2">
                            <form method="GET" action="{{ route('students.report-card', $student) }}" target="_blank" class="flex gap-2">
                                <select name="semester_id" class="rounded-lg border-gray-300 text-sm">
                                    @foreach($semesters as $t)
                                        <option value="{{ $t->id }}" @selected($t->is_current)>{{ $t->full_name }}</option>
                                    @endforeach
                                </select>
                                <x-button type="submit" size="sm" icon="fas fa-print">View / Print</x-button>
                            </form>
                            @can('send-sms')
                                <form method="POST" action="{{ route('students.send-grades', $student) }}" class="flex gap-2" onsubmit="this.semester_id.value = this.previousElementSibling.semester_id.value">
                                    @csrf
                                    <input type="hidden" name="semester_id">
                                    <x-button type="submit" size="sm" variant="secondary" icon="fas fa-sms" :disabled="!$student->emergency_contact_phone" title="Text the parent a link to this report">Text to parent</x-button>
                                </form>
                            @endcan
                        </div>
                    </div>

                    @forelse($groupedResults as $year)
                        @foreach($year['semesters'] as $termData)
                            <div class="mb-4">
                                <p class="text-sm font-semibold text-gray-700 mb-2">{{ $termData['semester']->name }} — {{ $year['academic_year']->name }}</p>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full text-sm border">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-3 py-2 text-left text-xs text-gray-500 uppercase">Subject</th>
                                                <th class="px-3 py-2 text-center text-xs text-gray-500 uppercase">Class</th>
                                                <th class="px-3 py-2 text-center text-xs text-gray-500 uppercase">Exam</th>
                                                <th class="px-3 py-2 text-center text-xs text-gray-500 uppercase">Total</th>
                                                <th class="px-3 py-2 text-center text-xs text-gray-500 uppercase">Grade</th>
                                                <th class="px-3 py-2 text-left text-xs text-gray-500 uppercase">Remark</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y">
                                            @foreach($termData['results'] as $result)
                                                <tr>
                                                    <td class="px-3 py-2">{{ $result->subject->title ?? '—' }}</td>
                                                    <td class="px-3 py-2 text-center">{{ $result->class_score !== null ? (float) $result->class_score : '' }}</td>
                                                    <td class="px-3 py-2 text-center">{{ $result->exam_score !== null ? (float) $result->exam_score : '' }}</td>
                                                    <td class="px-3 py-2 text-center font-semibold">{{ (float) $result->score }}</td>
                                                    <td class="px-3 py-2 text-center">{{ $result->grade }}</td>
                                                    <td class="px-3 py-2">{{ $result->remark }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endforeach
                    @empty
                        <p class="text-sm text-gray-500">No scores recorded yet.</p>
                    @endforelse
                </div>

                <div class="flex justify-end">
                    <form method="POST" action="{{ route('students.destroy', $student) }}" onsubmit="return confirm('Permanently delete this pupil with all their scores and fee records? To keep records, set status to Withdrawn instead.');">
                        @csrf @method('DELETE')
                        <x-button type="submit" variant="outline-danger" size="sm" icon="fas fa-trash">Delete pupil</x-button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
