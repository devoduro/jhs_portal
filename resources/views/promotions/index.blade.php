<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Promote Pupils') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="p-4 bg-blue-50 border-l-4 border-blue-400 text-blue-800 text-sm space-y-1">
                <p>Do this once at the end of the academic year, after the third-term reports are printed.</p>
                <p><strong>Tip:</strong> promote the highest class first (JHS 3 → completed, then JHS 2 → JHS 3, and so on down to KG) so each class is empty before the class below moves into it.</p>
            </div>

            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <table class="min-w-full text-sm divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Class</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Pupils</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Moves to</th>
                            <th class="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($classGroups->sortByDesc('level') as $class)
                            <tr>
                                <td class="px-6 py-3 font-medium">{{ $class->name }}</td>
                                <td class="px-6 py-3 text-right">{{ $class->students_count }}</td>
                                <td class="px-6 py-3 text-gray-600">
                                    @if(!$class->next_level)
                                        <span class="text-purple-700">Completes school</span>
                                    @elseif($class->suggested_target)
                                        {{ $class->suggested_target->name }}
                                    @else
                                        {{ \App\Support\FormLabel::label($class->next_level) }} <span class="text-xs text-orange-600">(no matching class yet)</span>
                                    @endif
                                </td>
                                <td class="px-6 py-3 text-right">
                                    @if($class->students_count)
                                        <form method="POST" action="{{ route('promotions.preview') }}">
                                            @csrf
                                            <input type="hidden" name="class_group_id" value="{{ $class->id }}">
                                            <x-button type="submit" size="sm" icon="fas fa-level-up-alt">{{ $class->next_level ? 'Promote…' : 'Complete…' }}</x-button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
