<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Class Lists') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="p-4 bg-blue-50 border-l-4 border-blue-400 text-blue-800 text-sm">
                Print or download a class list with the letterhead - choose the columns (admission number, gender, parent phone, signature, remarks)
                and add blank tick columns to use it as an attendance register.
            </div>

            @if($classGroups->isEmpty())
                <div class="bg-white rounded-lg shadow-sm p-10 text-center text-gray-500">No classes are available to you yet. Ask the administrator to make you a class teacher or assign you a subject.</div>
            @else
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($classGroups as $classGroup)
                        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-100">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h3 class="font-semibold text-gray-900">{{ $classGroup->name }}</h3>
                                    <p class="text-xs text-gray-500">{{ \App\Support\FormLabel::label($classGroup->level) }} · {{ $classGroup->classTeacher->name ?? 'No class teacher' }}</p>
                                </div>
                                <span class="rounded-full bg-[#42a5dc]/10 px-2.5 py-1 text-xs font-semibold text-[#2c8fc6]">{{ $counts[$classGroup->id] ?? 0 }} pupils</span>
                            </div>
                            <div class="mt-4 flex gap-2">
                                <a href="{{ route('class-lists.show', $classGroup) }}" class="flex-1 rounded-lg bg-[#42a5dc] px-3 py-2 text-center text-sm font-semibold text-white hover:bg-[#2c8fc6]"><i class="fas fa-print mr-1"></i> Print</a>
                                <a href="{{ route('class-lists.file', [$classGroup, 'download' => 1, 'cols' => ['index_number', 'gender', 'remarks']]) }}" class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-600 hover:bg-gray-50" title="Download PDF"><i class="fas fa-file-pdf text-red-500"></i></a>
                                <a href="{{ route('class-lists.excel', [$classGroup, 'cols' => ['index_number', 'gender', 'phone']]) }}" class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-600 hover:bg-gray-50" title="Excel"><i class="fas fa-file-excel text-green-600"></i></a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
