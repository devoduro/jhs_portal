<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Score Sheets & Continuous Assessment') }}</h2>
            <x-button href="{{ route('settings.index') }}" variant="secondary" icon="fas fa-arrow-left">Settings</x-button>
        </div>
    </x-slot>

    @php $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.'); @endphp

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="p-4 bg-blue-50 border-l-4 border-blue-400 text-blue-800 text-sm">
                This is what teachers see when they enter scores. Each class level can have its own columns - for example
                <em>Exercises 10 + Class Test 10 + Project 10 + Homework 10 + Exam 60</em> - and every column counts towards either the pupil's
                <strong>class score</strong> (continuous assessment) or the <strong>exam score</strong> on the terminal report.
                A level without its own sheet uses the default: Class Score {{ $fmt($defaultClass) }} + Exam Score {{ $fmt($defaultExam) }}
                (<a href="{{ route('settings.school') }}" class="underline">change the default</a>).
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                @foreach($levels as $row)
                    <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                        <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
                            <div>
                                <h3 class="font-semibold text-gray-900">{{ $row['label'] }}</h3>
                                @if($row['components']->isNotEmpty())
                                    <p class="text-xs text-gray-500">{{ $row['components']->count() }} columns &middot; out of {{ $fmt($row['total']) }}</p>
                                @else
                                    <p class="text-xs text-gray-400">Default sheet &middot; Class {{ $fmt($defaultClass) }} + Exam {{ $fmt($defaultExam) }}</p>
                                @endif
                            </div>
                            <div class="flex items-center gap-3 text-sm">
                                <a href="{{ route('assessment-settings.edit', $row['level']) }}" class="text-primary-600 hover:text-primary-800">
                                    <i class="fas fa-pen"></i> {{ $row['components']->isNotEmpty() ? 'Edit' : 'Customise' }}
                                </a>
                                @if($row['components']->isNotEmpty())
                                    <form action="{{ route('assessment-settings.destroy', $row['level']) }}" method="POST" onsubmit="return confirm('Put {{ $row['label'] }} back on the default score sheet?')">
                                        @csrf @method('DELETE')
                                        <button class="text-red-600 hover:text-red-800" title="Back to default"><i class="fas fa-rotate-left"></i></button>
                                    </form>
                                @endif
                            </div>
                        </div>
                        @if($row['components']->isNotEmpty())
                            <div class="px-5 py-3 flex flex-wrap gap-2">
                                @foreach($row['components'] as $c)
                                    <span class="inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-medium {{ $c->type === 'exam' ? 'bg-orange-50 text-orange-700 ring-1 ring-orange-100' : 'bg-indigo-50 text-indigo-700 ring-1 ring-indigo-100' }}">
                                        {{ $c->label }} <strong>{{ $fmt($c->max_mark) }}</strong>
                                    </span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-app-layout>
