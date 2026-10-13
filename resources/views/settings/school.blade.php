<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('School & Report Settings') }}</h2>
            <x-button href="{{ route('settings.index') }}" variant="secondary" icon="fas fa-arrow-left">Settings</x-button>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('settings.school.update') }}" class="space-y-6">
                @csrf
                @method('PUT')

                @if($errors->any())
                    <div class="p-4 bg-red-50 border-l-4 border-red-400 text-red-700 text-sm">{{ $errors->first() }}</div>
                @endif

                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-1">Classes this school runs</h3>
                    <p class="text-sm text-gray-500 mb-4">Only ticked levels appear in class, subject and fee lists. The last ticked level is the one pupils complete school from.</p>
                    @foreach(\App\Support\FormLabel::SECTIONS as $section => $sectionLabel)
                        <p class="text-xs font-semibold uppercase text-gray-400 mt-3 mb-2">{{ $sectionLabel }}</p>
                        <div class="grid grid-cols-3 md:grid-cols-6 gap-2">
                            @foreach(\App\Support\FormLabel::OPTIONS as $level => $label)
                                @continue(\App\Support\FormLabel::section($level) !== $section)
                                <label class="flex items-center gap-2 p-2 border rounded-lg text-sm">
                                    <input type="checkbox" name="levels[]" value="{{ $level }}" class="rounded border-gray-300" @checked(in_array($level, old('levels', $offered)))>
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                    @endforeach
                </div>

                <div class="bg-white shadow-sm rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-1">Terminal report scores</h3>
                    <p class="text-sm text-gray-500 mb-4">The default split of the 100 marks per subject between continuous assessment (class exercises, tests, projects) and the end-of-term exam. GES basic schools commonly use 50/50; some schools use 30/70 or 40/60.
                        Want separate columns (e.g. Exercises 10, Class Test 10, Project 10, Exam 70) or a different sheet for a class? <a href="{{ route('assessment-settings.index') }}" class="text-primary-600 underline">Design the score sheets</a>.</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <x-input name="class_score_max" type="number" step="0.5" min="0" max="100" label="Class score out of" :value="old('class_score_max', $values['class_score_max'] ?? 50)" required />
                        <x-input name="exam_score_max" type="number" step="0.5" min="0" max="100" label="Exam score out of" :value="old('exam_score_max', $values['exam_score_max'] ?? 50)" required />
                    </div>
                    <p class="text-xs text-orange-600 mt-3">Changing the split does not rescale scores already entered.</p>
                </div>

                <div class="bg-white shadow-sm rounded-lg p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <x-input name="institution_code" label="Admission number prefix" :value="old('institution_code', $values['institution_code'] ?? 'SCH')" required
                             :helper="'New pupils are numbered ' . strtoupper($values['institution_code'] ?? 'SCH') . '/' . now()->year . '/0001, 0002...'" />
                    <x-input name="head_title" label="Head's title on reports" :value="old('head_title', $values['head_title'] ?? 'Head Teacher')" required helper="e.g. Head Teacher, Headmistress, Proprietor" />
                    <label class="flex items-center gap-2 text-sm text-gray-700 md:col-span-2">
                        <input type="hidden" name="report_show_fees" value="0">
                        <input type="checkbox" name="report_show_fees" value="1" class="rounded border-gray-300" @checked(old('report_show_fees', $values['report_show_fees'] ?? '1') == '1')>
                        Show the pupil's fee balance and next term's fees on terminal reports
                    </label>
                </div>

                <div class="flex justify-end">
                    <x-button type="submit" icon="fas fa-save">Save Settings</x-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
