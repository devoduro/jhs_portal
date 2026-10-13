<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Bill an Item to Many Pupils') }}</h2>
            <x-button href="{{ route('fees.charges.index', ['semester_id' => $term?->id]) }}" variant="secondary" icon="fas fa-arrow-left">{{ __('Billed Items') }}</x-button>
        </div>
    </x-slot>

    <div class="py-8" x-data="{ target: '{{ old('target', 'classes') }}', mode: '{{ old('mode', 'fixed') }}', discount: {{ old('is_discount') ? 'true' : 'false' }} }">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="p-4 bg-blue-50 border-l-4 border-blue-400 text-blue-800 text-sm">
                Use this for anything that is not on the regular fee schedule: a bus fee for the pupils who ride, an excursion for Basic 5,
                uniforms for new pupils, or a discount for staff children. You will see exactly who is billed what before anything is saved.
                To bill the whole fee schedule use <a href="{{ route('fees.billing', ['semester_id' => $term?->id]) }}" class="underline">Bill a Term</a>.
            </div>

            @if($errors->any())
                <div class="p-4 bg-red-50 border-l-4 border-red-400 text-red-700 text-sm">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('fees.bulk-bill.preview') }}" class="space-y-6">
                @csrf

                <div class="bg-white shadow-sm rounded-lg p-6 grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Term</label>
                        <select name="semester_id" class="w-full rounded-lg border-gray-300 text-sm">
                            @foreach($terms as $t)
                                <option value="{{ $t->id }}" @selected(old('semester_id', $term?->id) == $t->id)>{{ $t->full_name }}{{ $t->is_current ? ' - current' : '' }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">What is being billed?</label>
                        <label class="flex items-center gap-2 text-sm mb-2">
                            <input type="checkbox" name="is_discount" value="1" x-model="discount" class="rounded border-gray-300">
                            This is a <strong>discount / waiver</strong> (reduces what pupils owe)
                        </label>
                        <select name="category" x-show="!discount" :disabled="discount" class="w-full rounded-lg border-gray-300 text-sm">
                            <option value="">Choose a fee item...</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->slug }}" @selected(old('category') === $category->slug)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                        <p x-show="discount" class="text-xs text-gray-500">Saved under the "Discount / Waiver" item on each pupil's bill.</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Amount is</label>
                        <div class="flex gap-4 text-sm">
                            <label class="flex items-center gap-2"><input type="radio" name="mode" value="fixed" x-model="mode"> A fixed amount (GH₵)</label>
                            <label class="flex items-center gap-2"><input type="radio" name="mode" value="percent" x-model="mode"> A percentage of their term bill</label>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1"><span x-text="mode === 'percent' ? 'Percentage (%)' : 'Amount per pupil (GH₵)'"></span></label>
                        <input type="number" name="amount" step="0.01" min="0.01" value="{{ old('amount') }}" required class="w-full rounded-lg border-gray-300 text-sm">
                    </div>

                    <div x-show="mode === 'percent'" class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Percentage of which part of the bill?</label>
                        <select name="base_category" :disabled="mode !== 'percent'" class="w-full rounded-lg border-gray-300 text-sm">
                            <option value="">Everything billed to the pupil this term</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->slug }}" @selected(old('base_category') === $category->slug)>Only {{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Note on the bill <span class="text-gray-400 font-normal">(optional)</span></label>
                        <input type="text" name="notes" maxlength="255" value="{{ old('notes') }}" placeholder="e.g. Zoo trip 14 Nov, Staff child discount" class="w-full rounded-lg border-gray-300 text-sm">
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">If a pupil already has this item on their bill</label>
                        <div class="flex gap-4 text-sm">
                            <label class="flex items-center gap-2"><input type="radio" name="existing" value="skip" @checked(old('existing', 'skip') === 'skip')> Leave it as it is</label>
                            <label class="flex items-center gap-2"><input type="radio" name="existing" value="overwrite" @checked(old('existing') === 'overwrite')> Replace it with the new amount</label>
                        </div>
                    </div>
                </div>

                <div class="bg-white shadow-sm rounded-lg p-6 space-y-4">
                    <h3 class="text-sm font-semibold text-gray-900">Who is it for?</h3>
                    <div class="flex flex-wrap gap-4 text-sm">
                        <label class="flex items-center gap-2"><input type="radio" name="target" value="all" x-model="target"> Every active pupil</label>
                        <label class="flex items-center gap-2"><input type="radio" name="target" value="classes" x-model="target"> Whole classes</label>
                        <label class="flex items-center gap-2"><input type="radio" name="target" value="pupils" x-model="target"> Pupils I pick</label>
                    </div>

                    <div x-show="target === 'classes'" class="grid grid-cols-2 md:grid-cols-4 gap-2">
                        @foreach($classGroups as $classGroup)
                            <label class="flex items-center gap-2 p-2 border rounded-lg text-sm">
                                <input type="checkbox" name="class_group_ids[]" value="{{ $classGroup->id }}" class="rounded border-gray-300" @checked(in_array($classGroup->id, (array) old('class_group_ids', [])))>
                                <span>{{ $classGroup->name }} <span class="text-gray-400">({{ $classGroup->students->count() }})</span></span>
                            </label>
                        @endforeach
                    </div>

                    <div x-show="target === 'pupils'" class="space-y-2" x-data="{ q: '' }">
                        <input type="text" x-model="q" placeholder="Type to find a pupil..." class="w-full rounded-lg border-gray-300 text-sm">
                        <div class="max-h-96 overflow-y-auto border rounded-lg divide-y">
                            @foreach($classGroups as $classGroup)
                                @if($classGroup->students->isNotEmpty())
                                    <div class="px-3 py-1.5 bg-gray-50 text-xs font-semibold text-gray-500 uppercase sticky top-0">{{ $classGroup->name }}</div>
                                    @foreach($classGroup->students as $pupil)
                                        <label class="flex items-center gap-2 px-3 py-1.5 text-sm hover:bg-gray-50"
                                               x-show="q === '' || '{{ strtolower(addslashes($pupil->full_name . ' ' . $pupil->index_number)) }}'.includes(q.toLowerCase())">
                                            <input type="checkbox" name="student_ids[]" value="{{ $pupil->id }}" class="rounded border-gray-300" @checked(in_array($pupil->id, array_map('intval', (array) old('student_ids', []))))>
                                            {{ $pupil->full_name }} <span class="text-xs text-gray-400">{{ $pupil->index_number }}</span>
                                        </label>
                                    @endforeach
                                @endif
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="flex justify-end">
                    <x-button type="submit" icon="fas fa-eye">Review before billing</x-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
