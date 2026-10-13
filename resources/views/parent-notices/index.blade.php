<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Send Results & Fees to Parents') }}</h2>
            <x-button href="{{ route('sms.parent-notifications') }}" variant="secondary" icon="fas fa-clock-rotate-left">SMS log</x-button>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="p-4 bg-blue-50 border-l-4 border-blue-400 text-blue-800 text-sm">
                Each parent gets their own child's results (scores, position and a link to the full report card) and/or fee statement
                (term bill, payments, balance) - by SMS to the parent phone, by email to the parent email, or both.
                Phone numbers and parent emails are kept on each pupil's record (Pupils → edit, or the pupil import).
            </div>

            @if($errors->any())
                <div class="p-4 bg-red-50 border-l-4 border-red-400 text-red-700 text-sm">{{ $errors->first() }}</div>
            @endif

            <form method="GET" action="{{ route('parent-notices.index') }}" class="bg-white shadow-sm rounded-lg p-5 space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Term</label>
                        <select name="semester_id" class="w-full rounded-md border-gray-300 text-sm" onchange="this.form.submit()">
                            @foreach($terms as $t)
                                <option value="{{ $t->id }}" @selected($term && $term->id === $t->id)>{{ $t->full_name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-600 mb-1">Classes</p>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                        @foreach($classGroups as $cg)
                            <label class="flex items-center gap-2 p-2 border rounded-lg text-sm">
                                <input type="checkbox" name="class_group_ids[]" value="{{ $cg->id }}" class="rounded border-gray-300" @checked(in_array($cg->id, $classIds)) onchange="this.form.submit()">
                                {{ $cg->name }}
                            </label>
                        @endforeach
                    </div>
                </div>
            </form>

            <div class="grid grid-cols-2 md:grid-cols-5 gap-3 text-center">
                @foreach([['Pupils', $counts['pupils'], 'text-gray-900'], ['With scores', $counts['with_results'], 'text-indigo-600'], ['Owing fees', $counts['owing'], 'text-red-600'], ['Parent phones', $counts['phones'], 'text-green-600'], ['Parent emails', $counts['emails'], 'text-blue-600']] as [$label, $n, $color])
                    <div class="bg-white shadow-sm rounded-lg p-3"><p class="text-2xl font-bold {{ $color }}">{{ $n }}</p><p class="text-xs text-gray-500">{{ $label }}{{ $classIds ? '' : ' (all)' }}</p></div>
                @endforeach
            </div>

            @if($term)
                <form method="POST" action="{{ route('parent-notices.send') }}" class="bg-white shadow-sm rounded-lg p-5 space-y-5" id="notice-form">
                    @csrf
                    <input type="hidden" name="semester_id" value="{{ $term->id }}">
                    @foreach($classIds as $id)<input type="hidden" name="class_group_ids[]" value="{{ $id }}">@endforeach
                    @if(!$classIds)
                        <input type="hidden" name="whole_school" value="1">
                    @endif

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <p class="text-sm font-semibold text-gray-900 mb-2">What to send</p>
                            <label class="flex items-center gap-2 text-sm mb-1"><input type="checkbox" name="kinds[]" value="results" class="rounded border-gray-300" checked> Terminal results</label>
                            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="kinds[]" value="fees" class="rounded border-gray-300" checked> Fee statement</label>
                            <label class="flex items-center gap-2 text-xs text-gray-600 mt-2 ml-6"><input type="checkbox" name="only_owing" value="1" class="rounded border-gray-300"> Fee statement only to parents who owe</label>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-gray-900 mb-2">How to send it</p>
                            <label class="flex items-center gap-2 text-sm mb-1"><input type="checkbox" name="channels[]" value="sms" class="rounded border-gray-300" checked> <i class="fas fa-sms text-green-600"></i> SMS to parent phone</label>
                            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="channels[]" value="email" class="rounded border-gray-300" checked> <i class="fas fa-envelope text-blue-600"></i> Email to parent email</label>
                            <p class="text-xs text-gray-500 mt-2">SMS uses your SMS credit; a parent with no phone or email on file is skipped for that channel.</p>
                        </div>
                    </div>

                    <div class="flex justify-between items-center border-t pt-4">
                        <p class="text-xs text-gray-500">{{ $classIds ? 'Sending to the ticked classes above.' : 'No class ticked - this goes to the whole school.' }} Sending may take a minute for a big list.</p>
                        <x-button type="submit" variant="success" icon="fas fa-paper-plane">Send to parents</x-button>
                    </div>
                </form>
            @endif

            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <div class="px-5 py-3 border-b text-sm font-semibold text-gray-900">Recent emails</div>
                <table class="min-w-full text-sm divide-y divide-gray-100">
                    <tbody class="divide-y divide-gray-100">
                        @forelse($emailLogs as $log)
                            <tr>
                                <td class="px-5 py-2">{{ $log->student->full_name ?? '—' }}<div class="text-xs text-gray-400">{{ $log->email }}</div></td>
                                <td class="px-3 py-2 text-gray-600">{{ $log->type === 'results' ? 'Results' : 'Fees' }}</td>
                                <td class="px-3 py-2"><span class="rounded-full px-2 py-0.5 text-xs {{ $log->success ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700' }}" @if($log->error) title="{{ $log->error }}" @endif>{{ $log->success ? 'Sent' : 'Failed' }}</span></td>
                                <td class="px-5 py-2 text-right text-xs text-gray-400">{{ $log->created_at->format('d M Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td class="px-5 py-8 text-center text-gray-400">No emails sent yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
