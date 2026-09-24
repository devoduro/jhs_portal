<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Fee Collection Report') }}</h2>
            <div class="flex gap-2">
                <x-button href="{{ route('fees.report.print', request()->query() + ['semester_id' => $term?->id]) }}" variant="secondary" icon="fas fa-print" target="_blank">Print</x-button>
                <x-button href="{{ route('fees.report.export', request()->query() + ['semester_id' => $term?->id]) }}" variant="secondary" icon="fas fa-file-excel">Excel</x-button>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <form method="GET" action="{{ route('fees.report') }}" class="bg-white shadow-sm rounded-lg p-4 flex flex-wrap items-end gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Term</label>
                    <select name="semester_id" class="rounded-lg border-gray-300 text-sm">
                        @foreach($terms as $t)
                            <option value="{{ $t->id }}" @selected($term && $term->id === $t->id)>{{ $t->full_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Cash book from</label>
                    <input type="date" name="from" value="{{ $from->format('Y-m-d') }}" class="rounded-lg border-gray-300 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">to</label>
                    <input type="date" name="to" value="{{ $to->format('Y-m-d') }}" class="rounded-lg border-gray-300 text-sm">
                </div>
                <x-button type="submit" icon="fas fa-sync">Show</x-button>
            </form>

            @include('fees.partials.report-body')
        </div>
    </div>
</x-app-layout>
