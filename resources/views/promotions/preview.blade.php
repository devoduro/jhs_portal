<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $isFinal ? 'Complete' : 'Promote' }} {{ $classGroup->name }}
                @unless($isFinal) → {{ \App\Support\FormLabel::label($nextLevel) }} @endunless
            </h2>
            <x-button href="{{ route('promotions.index') }}" variant="secondary" icon="fas fa-arrow-left">Back</x-button>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('promotions.store') }}" class="bg-white shadow-sm rounded-lg overflow-hidden"
                  onsubmit="return confirm('Move the ticked pupils now?')">
                @csrf
                <input type="hidden" name="class_group_id" value="{{ $classGroup->id }}">

                <div class="p-6 border-b space-y-4">
                    @if($isFinal)
                        <p class="text-sm text-gray-600">Ticked pupils will be marked as having completed school and removed from the class list. Their scores and fee records are kept.</p>
                        <x-input name="graduation_year" type="number" label="Year of completion" :value="now()->year" class="max-w-xs" />
                    @else
                        <div class="max-w-sm">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Move ticked pupils into</label>
                            <select name="target_class_group_id" class="w-full rounded-lg border-gray-300 text-sm">
                                <option value="">{{ \App\Support\FormLabel::label($nextLevel) }} — place in a class later</option>
                                @foreach($targetClasses as $target)
                                    <option value="{{ $target->id }}" @selected($suggested && $suggested->id === $target->id)>{{ $target->name }}</option>
                                @endforeach
                            </select>
                            @if($targetClasses->isEmpty())
                                <p class="text-xs text-orange-600 mt-1">No {{ \App\Support\FormLabel::label($nextLevel) }} class exists yet. <a href="{{ route('class-groups.create') }}" class="underline">Create it</a> first, or place pupils later.</p>
                            @endif
                        </div>
                        <p class="text-sm text-gray-600">Untick pupils who are repeating {{ \App\Support\FormLabel::label($classGroup->level) }}; they stay in {{ $classGroup->name }}.</p>
                    @endif
                    @if($decisions->isNotEmpty())
                        <p class="text-xs text-gray-500">Ticks are pre-filled from the class teacher's "Promoted to" entries on the {{ $finalTerm->full_name }} reports.</p>
                    @endif
                </div>

                <table class="min-w-full text-sm divide-y divide-gray-100">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 w-10"><input type="checkbox" checked onclick="document.querySelectorAll('.p-box').forEach(b => b.checked = this.checked)" class="rounded border-gray-300"></th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Pupil</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Class teacher's decision</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($students as $student)
                            @php
                                $decision = $decisions[$student->id] ?? null;
                                $repeats = $decision !== null && (int) $decision === (int) $classGroup->level;
                            @endphp
                            <tr class="{{ $repeats ? 'bg-yellow-50' : '' }}">
                                <td class="px-6 py-2"><input type="checkbox" name="student_ids[]" value="{{ $student->id }}" class="p-box rounded border-gray-300" @checked(!$repeats)></td>
                                <td class="px-6 py-2">{{ $student->full_name }} <span class="text-xs text-gray-500">{{ $student->index_number }}</span></td>
                                <td class="px-6 py-2 text-gray-600">
                                    @if($decision === null) —
                                    @elseif($repeats) <span class="text-yellow-700">Repeat</span>
                                    @elseif((int) $decision === 0) Completed
                                    @else Promote to {{ \App\Support\FormLabel::label((int) $decision) }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="p-6 flex justify-end border-t">
                    <x-button type="submit" icon="fas fa-level-up-alt">{{ $isFinal ? 'Mark ticked pupils as completed' : 'Promote ticked pupils' }}</x-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
