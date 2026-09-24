@php $t = $semester ?? null; @endphp
@if($errors->any())
    <div class="p-3 bg-red-50 text-red-700 text-sm rounded">{{ $errors->first() }}</div>
@endif
<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Academic year <span class="text-red-500">*</span></label>
        <select name="academic_year_id" class="w-full rounded-lg border-gray-300 text-sm" required>
            @foreach($academicYears as $year)
                <option value="{{ $year->id }}" @selected(old('academic_year_id', $t->academic_year_id ?? ($year->is_current ? $year->id : null)) == $year->id)>{{ $year->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Term <span class="text-red-500">*</span></label>
        <select name="semester_number" id="semester_number" class="w-full rounded-lg border-gray-300 text-sm" required
                onchange="document.getElementById('name').value = this.options[this.selectedIndex].text">
            @foreach([1 => 'First Term', 2 => 'Second Term', 3 => 'Third Term'] as $n => $label)
                <option value="{{ $n }}" @selected(old('semester_number', $t->semester_number ?? 1) == $n)>{{ $label }}</option>
            @endforeach
        </select>
        <input type="hidden" name="name" id="name" value="{{ old('name', $t->name ?? 'First Term') }}">
    </div>
    <x-input name="start_date" type="date" label="Reopening date" :value="old('start_date', $t?->start_date?->format('Y-m-d'))" required />
    <x-input name="end_date" type="date" label="Last day of term" :value="old('end_date', $t?->end_date?->format('Y-m-d'))" required />
    <x-input name="vacation_date" type="date" label="Vacation date (printed on reports)" :value="old('vacation_date', $t?->vacation_date?->format('Y-m-d'))" />
    <x-input name="next_term_begins" type="date" label="Next term begins (printed on reports)" :value="old('next_term_begins', $t?->next_term_begins?->format('Y-m-d'))" />
    <x-input name="attendance_days" type="number" label="Number of school days" min="0" max="200" :value="old('attendance_days', $t->attendance_days ?? '')" helper="Reports show attendance as 'days present out of' this number." />
</div>
