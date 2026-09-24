<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Import Placement List') }}
            </h2>
            <x-button href="{{ route('admissions.index') }}" variant="secondary" icon="fas fa-arrow-left">
                {{ __('Back to Admissions') }}
            </x-button>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if(session('importStats'))
                @php($stats = session('importStats'))
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 bg-white border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Import Report</h3>
                        <div class="grid grid-cols-3 gap-4 mb-4">
                            <div class="bg-green-50 rounded-lg p-4 text-center">
                                <div class="text-2xl font-semibold text-green-900">{{ $stats['processed'] }}</div>
                                <div class="text-xs text-green-700 mt-1">New Placements</div>
                            </div>
                            <div class="bg-blue-50 rounded-lg p-4 text-center">
                                <div class="text-2xl font-semibold text-blue-900">{{ $stats['updated'] }}</div>
                                <div class="text-xs text-blue-700 mt-1">Updated (Still Unpaid)</div>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-4 text-center">
                                <div class="text-2xl font-semibold text-gray-900">{{ $stats['skipped'] }}</div>
                                <div class="text-xs text-gray-700 mt-1">Skipped</div>
                            </div>
                        </div>

                        @if(!empty($stats['errors']))
                            <div class="border border-yellow-200 bg-yellow-50 rounded-md p-4 max-h-64 overflow-y-auto">
                                <p class="text-sm font-medium text-yellow-800 mb-2">Issues ({{ count($stats['errors']) }})</p>
                                <ul class="list-disc list-inside text-sm text-yellow-700 space-y-1">
                                    @foreach($stats['errors'] as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @else
                            <p class="text-sm text-green-700">No issues - every row imported cleanly.</p>
                        @endif

                        <a href="{{ route('admissions.index') }}" class="inline-flex items-center gap-1 mt-4 text-primary-600 hover:underline text-sm">
                            View Admissions List &rarr;
                        </a>
                    </div>
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <div class="mb-6 p-4 bg-blue-50 border-l-4 border-blue-400 text-blue-700 text-sm">
                        <p class="font-medium mb-2">File format</p>
                        <p>Required columns: <strong>index_number</strong> (BECE index number) and <strong>full_name</strong>.</p>
                        <p class="mt-2">Optional columns, usually already on the GES CSSPS placement export so applicants won't need to re-enter them: <strong>gender</strong> ("Male"/"Female" or "M"/"F"), <strong>aggregate_score</strong> (BECE aggregate), <strong>programme</strong> (matched against this school's programme names, with common CSSPS spelling variants like "General Agriculture" handled automatically), and <strong>boarding_status</strong> ("Day" or "Boarder").</p>
                        <p class="mt-2">Columns are matched by name, not position, so it doesn't matter what order they're in. Re-uploading a corrected file <strong>updates</strong> a placement that's still unpaid, but never touches one an applicant has already started paying for or completing.</p>
                        <p class="mt-2">Applicants can then find their placement and pay the admission fee at <strong>{{ route('admission.verify') }}</strong>.</p>
                        <a href="{{ route('admissions.import.template') }}" class="inline-flex items-center gap-1 mt-3 text-blue-700 underline">
                            <i class="fas fa-download"></i> Download Template
                        </a>
                    </div>

                    @if ($errors->any())
                        <div class="mb-4 p-4 bg-red-50 border-l-4 border-red-400 text-red-700 text-sm">
                            <ul class="list-disc list-inside">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    @if(session('error'))
                        <div class="mb-4 p-4 bg-red-50 border-l-4 border-red-400 text-red-700 text-sm">{{ session('error') }}</div>
                    @endif

                    <form method="POST" action="{{ route('admissions.import.store') }}" enctype="multipart/form-data" class="space-y-6">
                        @csrf

                        <div>
                            <label for="excel_file" class="block text-sm font-medium text-gray-700 mb-1">Placement List File <span class="text-red-500">*</span></label>
                            <input type="file" id="excel_file" name="excel_file" accept=".xlsx,.xls,.csv" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring focus:ring-primary-200 focus:ring-opacity-50" required>
                            <p class="mt-1 text-sm text-gray-500">Accepted formats: .xlsx, .xls, .csv (max 10MB)</p>
                        </div>

                        <div class="flex justify-end space-x-3">
                            <x-button type="button" variant="secondary" onclick="window.history.back()">
                                {{ __('Cancel') }}
                            </x-button>
                            <x-button type="submit" icon="fas fa-upload">
                                {{ __('Upload') }}
                            </x-button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <h3 class="text-sm font-semibold text-gray-900 uppercase tracking-wide mb-2">Fix a Bad Upload</h3>
                    <p class="text-sm text-gray-500 mb-4">Removes placements that are still unpaid (nobody has started paying or completing a profile yet) so you can re-import a corrected file from scratch. Placements already in progress are never affected.</p>
                    <form method="POST" action="{{ route('admissions.clear-unpaid') }}" onsubmit="return confirm('Remove every unpaid placement? This cannot be undone. Placements that have started payment or been completed are not affected.')">
                        @csrf
                        <x-button type="submit" variant="danger" icon="fas fa-trash">
                            {{ __('Clear Unpaid Placements') }}
                        </x-button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
