<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Pupil Fees') }}</h2>
            <div class="flex flex-wrap gap-2">
                <x-button href="{{ route('fees.export.excel', request()->query()) }}" variant="secondary" icon="fas fa-file-excel">Excel</x-button>
                <x-button href="{{ route('fees.export.pdf', request()->query()) }}" variant="secondary" icon="fas fa-file-pdf">PDF</x-button>
                <x-button href="{{ route('fees.report', ['semester_id' => $term?->id]) }}" variant="secondary" icon="fas fa-chart-pie">Collection Report</x-button>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <!-- Summary -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-white shadow-sm rounded-lg p-4">
                    <p class="text-xs uppercase text-gray-500">Billed this term</p>
                    <p class="text-xl font-bold text-gray-900">GH₵ {{ number_format($summary['billed'], 2) }}</p>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-4">
                    <p class="text-xs uppercase text-gray-500">Collected this term</p>
                    <p class="text-xl font-bold text-green-600">GH₵ {{ number_format($summary['paid'], 2) }}</p>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-4">
                    <p class="text-xs uppercase text-gray-500">Total outstanding</p>
                    <p class="text-xl font-bold text-red-600">GH₵ {{ number_format($summary['outstanding'], 2) }}</p>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-4">
                    <p class="text-xs uppercase text-gray-500">Pupils owing</p>
                    <p class="text-xl font-bold text-gray-900">{{ $summary['debtors'] }}</p>
                </div>
            </div>

            <!-- Filters -->
            <form method="GET" action="{{ route('fees.index') }}" class="bg-white shadow-sm rounded-lg p-4 grid grid-cols-2 md:grid-cols-6 gap-3 items-end">
                <div class="col-span-2">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Search pupil</label>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Name or admission no." class="w-full rounded-lg border-gray-300 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Term</label>
                    <select name="semester_id" class="w-full rounded-lg border-gray-300 text-sm">
                        @foreach($terms as $t)
                            <option value="{{ $t->id }}" @selected($term && $term->id === $t->id)>{{ $t->full_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Class</label>
                    <select name="class_group_id" class="w-full rounded-lg border-gray-300 text-sm">
                        <option value="">All classes</option>
                        @foreach($classGroups as $classGroup)
                            <option value="{{ $classGroup->id }}" @selected(request('class_group_id') == $classGroup->id)>{{ $classGroup->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Status</label>
                    <select name="status" class="w-full rounded-lg border-gray-300 text-sm">
                        <option value="">All pupils</option>
                        <option value="debtors" @selected(request('status') === 'debtors')>Owing</option>
                        <option value="settled" @selected(request('status') === 'settled')>Fully paid</option>
                        <option value="creditors" @selected(request('status') === 'creditors')>In credit</option>
                    </select>
                </div>
                <div class="flex gap-2">
                    <x-button type="submit" icon="fas fa-filter">Filter</x-button>
                    <x-button href="{{ route('fees.index') }}" variant="secondary">Reset</x-button>
                </div>
            </form>

            <!-- List -->
            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <form method="POST" action="{{ route('fees.reminders') }}">
                    @csrf
                    <div class="px-4 py-3 border-b border-gray-200 flex flex-wrap justify-between items-center gap-2">
                        <p class="text-sm text-gray-600">{{ $students->total() }} pupil(s){{ $term ? ' — ' . $term->full_name : '' }}</p>
                        @can('send-sms')
                            <x-button type="submit" size="sm" variant="outline-primary" icon="fas fa-sms" onclick="return confirm('Send an SMS balance reminder to the parents of the ticked pupils?')">
                                SMS reminder to ticked
                            </x-button>
                        @endcan
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-3"><input type="checkbox" onclick="document.querySelectorAll('.pupil-box').forEach(b => b.checked = this.checked)" class="rounded border-gray-300" title="Tick all pupils owing"></th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Pupil</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Class</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Bal. b/f</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Term Bill</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Paid (Term)</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Balance</th>
                                    <th class="px-4 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse($students as $row)
                                    @php $student = $row['student']; @endphp
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-3 py-3 text-center">
                                            @if($row['status'] === 'debtor')
                                                <input type="checkbox" name="student_ids[]" value="{{ $student->id }}" class="pupil-box rounded border-gray-300">
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">
                                            <a href="{{ route('fees.show', ['student' => $student, 'semester_id' => $term?->id]) }}" class="font-medium text-gray-900 hover:text-primary-600">{{ $student->full_name }}</a>
                                            <div class="text-xs text-gray-500">{{ $student->index_number }}</div>
                                        </td>
                                        <td class="px-4 py-3 text-gray-600">{{ $student->classGroup->name ?? \App\Support\FormLabel::label($student->level) }}</td>
                                        <td class="px-4 py-3 text-right {{ $row['brought_forward'] > 0 ? 'text-red-600' : 'text-gray-600' }}">{{ number_format($row['brought_forward'], 2) }}</td>
                                        <td class="px-4 py-3 text-right text-gray-900">{{ $row['term_bill'] > 0 ? number_format($row['term_bill'], 2) : '—' }}</td>
                                        <td class="px-4 py-3 text-right text-green-600">{{ number_format($row['term_paid'], 2) }}</td>
                                        <td class="px-4 py-3 text-right font-semibold {{ $row['status'] === 'debtor' ? 'text-red-600' : ($row['status'] === 'creditor' ? 'text-blue-600' : 'text-green-600') }}">
                                            {{ number_format($row['balance'], 2) }}
                                        </td>
                                        <td class="px-4 py-3 text-right whitespace-nowrap">
                                            <a href="{{ route('fees.show', ['student' => $student, 'semester_id' => $term?->id]) }}" class="inline-flex items-center px-3 py-1 rounded-lg bg-primary-600 text-white text-xs hover:bg-primary-700">
                                                <i class="fas fa-cash-register mr-1"></i> Receive
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="8" class="px-4 py-10 text-center text-gray-400">No pupils match these filters.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </form>
                <div class="px-4 py-3">{{ $students->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
