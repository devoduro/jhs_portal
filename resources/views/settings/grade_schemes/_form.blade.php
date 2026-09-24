@php
    $scheme = $gradeScheme ?? null;
    $rows = old('grades', $scheme
        ? $scheme->grades->sortByDesc('min_score')->map(fn ($g) => ['grade' => $g->grade, 'min_score' => (float) $g->min_score, 'grade_value' => (float) $g->grade_value, 'remark' => $g->remark])->values()->all()
        : [['grade' => '', 'min_score' => '', 'grade_value' => '', 'remark' => '']]);
@endphp

@if($errors->any())
    <div class="p-3 bg-red-50 text-red-700 text-sm rounded">{{ $errors->first() }}</div>
@endif

<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <x-input name="name" label="Scheme name" :value="old('name', $scheme->name ?? '')" required placeholder="e.g. JHS Grading (BECE 1-9)" />
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Used for</label>
        <select name="section" class="w-full rounded-lg border-gray-300 text-sm">
            <option value="">Any class without its own scheme</option>
            @foreach(\App\Support\FormLabel::SECTIONS as $key => $label)
                <option value="{{ $key }}" @selected(old('section', $scheme->section ?? '') === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="md:col-span-2">
        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
        <textarea name="description" rows="2" class="w-full rounded-lg border-gray-300 text-sm">{{ old('description', $scheme->description ?? '') }}</textarea>
    </div>
    <label class="flex items-center gap-2 text-sm text-gray-700">
        <input type="hidden" name="is_default" value="0">
        <input type="checkbox" name="is_default" value="1" class="rounded border-gray-300" @checked(old('is_default', $scheme->is_default ?? false))>
        Default scheme (used when a class's section has no scheme of its own)
    </label>
</div>

<div>
    <h3 class="text-sm font-semibold text-gray-900 mb-2">Grade bands <span class="font-normal text-gray-500">(highest first; a score gets the first band whose minimum it reaches)</span></h3>
    <table class="min-w-full text-sm" id="bands">
        <thead>
            <tr class="text-left text-xs text-gray-500 uppercase">
                <th class="py-2 pr-2">Grade</th>
                <th class="py-2 pr-2">Minimum score</th>
                <th class="py-2 pr-2">Grade value <span class="normal-case">(1 = best, for aggregate)</span></th>
                <th class="py-2 pr-2">Remark</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $i => $row)
                <tr>
                    <td class="py-1 pr-2"><input type="text" name="grades[{{ $i }}][grade]" value="{{ $row['grade'] }}" maxlength="5" class="w-20 rounded border-gray-300 text-sm" required></td>
                    <td class="py-1 pr-2"><input type="number" step="0.01" min="0" max="100" name="grades[{{ $i }}][min_score]" value="{{ $row['min_score'] }}" class="w-24 rounded border-gray-300 text-sm" required></td>
                    <td class="py-1 pr-2"><input type="number" step="1" min="0" max="9" name="grades[{{ $i }}][grade_value]" value="{{ $row['grade_value'] }}" class="w-20 rounded border-gray-300 text-sm" required></td>
                    <td class="py-1 pr-2"><input type="text" name="grades[{{ $i }}][remark]" value="{{ $row['remark'] ?? '' }}" class="w-56 rounded border-gray-300 text-sm" placeholder="e.g. Proficient"></td>
                    <td class="py-1"><button type="button" class="text-red-500 hover:text-red-700" onclick="this.closest('tr').remove()"><i class="fas fa-times"></i></button></td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <button type="button" id="add-band" class="mt-2 text-sm text-primary-600 hover:underline">+ Add band</button>
</div>

<script>
    document.getElementById('add-band').addEventListener('click', function () {
        var body = document.querySelector('#bands tbody');
        var i = Date.now();
        var row = body.rows[body.rows.length - 1].cloneNode(true);
        row.querySelectorAll('input').forEach(function (input) {
            input.name = input.name.replace(/grades\[[^\]]+\]/, 'grades[' + i + ']');
            input.value = '';
        });
        body.appendChild(row);
    });
</script>
