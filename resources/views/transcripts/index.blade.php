<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Transcripts') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="rounded-lg bg-blue-50 p-4 text-sm text-blue-800 ring-1 ring-blue-100">
                A transcript lists a pupil's scores class by class, for every term, with yearly averages, positions and attendance.
                Use it for pupils leaving the school or moving on, e.g. <strong>Basic 1 to Basic 6</strong> for those finishing primary, or <strong>JHS 1 to JHS 3</strong> for school leavers.
                For one pupil, open the pupil's profile and click <em>Transcript</em>.
            </div>

            <form method="GET" action="{{ route('transcripts.bulk') }}" target="_blank" class="space-y-5 rounded-lg bg-white p-6 shadow-sm">
                <h3 class="font-semibold text-gray-900">Print a whole class</h3>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Class</label>
                    <select name="class_group_id" class="w-full rounded-lg border-gray-300 text-sm" required>
                        <option value="">Choose a class…</option>
                        @foreach($classGroups as $c)
                            <option value="{{ $c->id }}">{{ $c->name }} ({{ $c->students_count }} pupils)</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">From</label>
                        <select name="from" class="w-full rounded-lg border-gray-300 text-sm">
                            <option value="">Their first class here</option>
                            @foreach(\App\Support\FormLabel::options() as $level => $label)<option value="{{ $level }}">{{ $label }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">To</label>
                        <select name="to" class="w-full rounded-lg border-gray-300 text-sm">
                            <option value="">Their current class</option>
                            @foreach(\App\Support\FormLabel::options() as $level => $label)<option value="{{ $level }}">{{ $label }}</option>@endforeach
                        </select>
                    </div>
                </div>
                <div class="flex justify-end">
                    <x-button type="submit" icon="fas fa-print">Print transcripts</x-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
