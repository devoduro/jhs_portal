<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Class List — {{ $classGroup->name }}</h2>
                <p class="text-sm text-gray-500">{{ $summary['total'] }} pupils · {{ $summary['boys'] }} boys · {{ $summary['girls'] }} girls</p>
            </div>
            <x-button href="{{ route('class-lists.index') }}" variant="secondary" icon="fas fa-arrow-left">All classes</x-button>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <form method="GET" class="bg-white rounded-lg shadow-sm p-4 space-y-4 text-sm">
                <input type="hidden" name="submitted" value="1">
                <div>
                    <p class="text-xs font-semibold uppercase text-gray-500 mb-2">Columns to print</p>
                    <div class="flex flex-wrap gap-x-5 gap-y-2">
                        @foreach($columnOptions as $key => $label)
                            <label class="flex items-center gap-2">
                                <input type="checkbox" name="cols[]" value="{{ $key }}" class="rounded border-gray-300" @checked(in_array($key, $columns))> {{ $label }}
                            </label>
                        @endforeach
                    </div>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-5 gap-3 items-end">
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Show</label>
                        <select name="gender" class="w-full rounded-md border-gray-300 text-sm">
                            <option value="">Boys and girls</option>
                            <option value="Male" @selected($gender === 'Male')>Boys only</option>
                            <option value="Female" @selected($gender === 'Female')>Girls only</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Order by</label>
                        <select name="sort" class="w-full rounded-md border-gray-300 text-sm">
                            <option value="name">Name</option>
                            <option value="admission" @selected($sort === 'admission')>Admission number</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Blank tick columns <span class="text-gray-400">(register)</span></label>
                        <input type="number" name="blank" min="0" max="20" value="{{ $blank }}" class="w-full rounded-md border-gray-300 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Title</label>
                        <input type="text" name="title" value="{{ $title }}" placeholder="Class List" maxlength="60" class="w-full rounded-md border-gray-300 text-sm">
                    </div>
                    <div class="flex items-center gap-3">
                        <label class="flex items-center gap-2"><input type="checkbox" name="landscape" value="1" class="rounded border-gray-300" @checked(request()->boolean('landscape'))> Landscape</label>
                        <x-button type="submit" size="sm" variant="secondary">Update</x-button>
                    </div>
                </div>
            </form>

            <div class="flex flex-wrap gap-2">
                <button type="button" id="print-list" class="px-3 py-2 bg-primary-600 text-white rounded-md text-sm hover:bg-primary-700"><i class="fas fa-print mr-1"></i> Print</button>
                <a href="{{ route('class-lists.file', [$classGroup] + $query + ['download' => 1]) }}" class="px-3 py-2 bg-white rounded-md text-sm hover:bg-gray-50 border border-gray-300"><i class="fas fa-file-pdf mr-1 text-red-500"></i> Download PDF</a>
                <a href="{{ route('class-lists.excel', [$classGroup] + $query) }}" class="px-3 py-2 bg-white rounded-md text-sm hover:bg-gray-50 border border-gray-300"><i class="fas fa-file-excel mr-1 text-green-600"></i> Excel</a>
                <a href="{{ route('class-lists.file', [$classGroup] + $query) }}" target="_blank" rel="noopener" class="px-3 py-2 bg-white rounded-md text-sm hover:bg-gray-50 border border-gray-300"><i class="fas fa-up-right-from-square mr-1"></i> Open in new tab</a>
            </div>

            <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                <iframe id="list-frame" src="{{ route('class-lists.file', [$classGroup] + $query) }}" title="Class list preview" class="w-full border-0" style="height: 80vh;"></iframe>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('print-list').addEventListener('click', function () {
            var frame = document.getElementById('list-frame');
            try { frame.contentWindow.focus(); frame.contentWindow.print(); }
            catch (e) { window.open(frame.src, '_blank'); }
        });
    </script>
</x-app-layout>
