{{-- Shared add / edit teacher form. $teacher is null when adding. --}}
@php
    $isEdit = isset($teacher) && $teacher;
    $field = 'mt-1 block w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-[#42a5dc] focus:ring-[#42a5dc]/30';
    $labelCls = 'block text-sm font-medium text-gray-700';
    $chosenClasses = array_map('intval', (array) old('class_group_ids', $currentClassIds ?? []));
    $chosenSubjects = array_map('intval', (array) old('subject_ids', $currentSubjectIds ?? []));
    $initial = [
        'name' => old('name', $teacher->name ?? ''),
        'email' => old('email', $teacher->email ?? ''),
        'phone' => old('phone', $teacher->phone ?? ''),
        'staff_id' => old('staff_id', $teacher->staff_id ?? ''),
        'subjects' => $chosenSubjects,
    ];
@endphp

@if($errors->any())
    <div class="mb-6 rounded-lg border-l-4 border-red-400 bg-red-50 p-4 text-sm text-red-700">
        <p class="mb-1 font-semibold">Please fix the following:</p>
        <ul class="list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

<div x-data="teacherForm({{ Js::from($initial) }}, {{ $isEdit ? $teacher->id : 'null' }})" class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    @if($isEdit)<input type="hidden" name="assignments_submitted" value="1">@endif

    <div class="space-y-6 lg:col-span-2">

        {{-- 1. Personal --}}
        <section class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-100">
            <header class="flex items-center gap-3 border-b border-gray-100 bg-gradient-to-r from-[#42a5dc]/10 to-transparent px-6 py-4">
                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-[#42a5dc] text-sm font-bold text-white">1</span>
                <div><h3 class="font-semibold text-gray-900">Personal & contact</h3><p class="text-xs text-gray-500">Name as it should appear on timetables and reports</p></div>
            </header>
            <div class="grid grid-cols-1 gap-5 p-6 md:grid-cols-2">
                <div class="md:col-span-2">
                    <label class="{{ $labelCls }}">Full name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" x-model="name" @input.debounce.500ms="check()" required autofocus maxlength="255" placeholder="e.g. Mrs. Grace Owusu" class="{{ $field }}">
                </div>
                <div>
                    <label class="{{ $labelCls }}"><i class="fas fa-envelope text-blue-600 mr-1"></i> Email</label>
                    <input type="email" name="email" x-model="email" @change="check()" placeholder="needed to create a login" class="{{ $field }}">
                </div>
                <div>
                    <label class="{{ $labelCls }}"><i class="fas fa-sms text-green-600 mr-1"></i> Phone</label>
                    <input type="tel" name="phone" x-model="phone" @change="check()" placeholder="024 123 4567" inputmode="tel" class="{{ $field }}" :class="phone && !phoneOk ? 'border-amber-400' : ''">
                    <p class="mt-1 text-xs" :class="phone && !phoneOk ? 'text-amber-600' : 'text-gray-500'" x-text="phone && !phoneOk ? 'That does not look like a full Ghana number (10 digits).' : 'Spaces are fine - saved as 0241234567.'"></p>
                </div>

                <div x-show="dupes.length" x-cloak class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800 md:col-span-2">
                    <p class="font-semibold"><i class="fas fa-triangle-exclamation mr-1"></i> Someone similar is already on record</p>
                    <ul class="mt-1 space-y-1">
                        <template x-for="d in dupes" :key="d.url">
                            <li><a :href="d.url" target="_blank" class="underline" x-text="d.name"></a>
                                <span class="text-xs text-amber-700" x-text="' · ' + (d.staff_id || 'no staff ID') + ' · ' + d.why"></span></li>
                        </template>
                    </ul>
                </div>
            </div>
        </section>

        {{-- 2. Staff --}}
        <section class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-100">
            <header class="flex items-center gap-3 border-b border-gray-100 bg-gradient-to-r from-[#8cc152]/15 to-transparent px-6 py-4">
                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-[#8cc152] text-sm font-bold text-white">2</span>
                <div><h3 class="font-semibold text-gray-900">Staff record</h3><p class="text-xs text-gray-500">Staff ID and department</p></div>
            </header>
            <div class="grid grid-cols-1 gap-5 p-6 md:grid-cols-2">
                <div>
                    <label class="{{ $labelCls }}">Staff ID</label>
                    <input type="text" name="staff_id" x-model="staff_id" @change="check()" maxlength="50" placeholder="{{ $suggestedStaffId ?? '' }}" class="{{ $field }}">
                    @unless($isEdit)<p class="mt-1 text-xs text-gray-500">Leave blank to use {{ $suggestedStaffId ?? 'the next number' }}</p>@endunless
                </div>
                <div>
                    <label class="{{ $labelCls }}">Department</label>
                    <select name="department_id" class="{{ $field }}">
                        <option value="">Not set</option>
                        @foreach($departments as $department)
                            <option value="{{ $department->id }}" @selected(old('department_id', $teacher->department_id ?? '') == $department->id)>{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </section>

        {{-- 3. Teaching --}}
        <section class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-100">
            <header class="flex items-center gap-3 border-b border-gray-100 bg-gradient-to-r from-[#967adc]/10 to-transparent px-6 py-4">
                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-[#967adc] text-sm font-bold text-white">3</span>
                <div><h3 class="font-semibold text-gray-900">Teaching</h3><p class="text-xs text-gray-500">Class teacher duty and the subjects they teach - they can enter scores for these</p></div>
            </header>
            <div class="space-y-6 p-6">
                <div>
                    <p class="{{ $labelCls }}">Class teacher of</p>
                    @if($classOptions->isEmpty())
                        <p class="mt-1 text-sm text-gray-400">Every class already has a class teacher.</p>
                    @else
                        <div class="mt-2 grid grid-cols-2 gap-2 md:grid-cols-4">
                            @foreach($classOptions as $class)
                                <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm transition has-[:checked]:border-[#967adc] has-[:checked]:bg-[#967adc]/10">
                                    <input type="checkbox" name="class_group_ids[]" value="{{ $class->id }}" class="rounded border-gray-300" @checked(in_array($class->id, $chosenClasses))> {{ $class->name }}
                                </label>
                            @endforeach
                        </div>
                        <p class="mt-1 text-xs text-gray-500">Only classes without a class teacher are listed.</p>
                    @endif
                </div>

                <div>
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="{{ $labelCls }}">Subjects taught <span class="ml-1 rounded-full bg-[#967adc]/10 px-2 py-0.5 text-xs font-semibold text-[#7d62c4]" x-text="subjects.length + ' chosen'"></span></p>
                        <input type="text" x-model="subjectSearch" placeholder="Find a subject..." class="w-48 rounded-lg border-gray-300 py-1.5 text-sm">
                    </div>
                    <div class="mt-3 max-h-80 space-y-3 overflow-y-auto rounded-lg border border-gray-100 p-3">
                        @forelse($subjectsByLevel as $level => $subjects)
                            <div x-data="{ ids: {{ Js::from($subjects->pluck('id')->all()) }} }">
                                <div class="mb-1.5 flex items-center justify-between">
                                    <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ \App\Support\FormLabel::label($level) }}</span>
                                    <button type="button" class="text-xs text-[#2c8fc6] hover:underline" @click="toggleLevel(ids)">Tick / untick all</button>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    @foreach($subjects as $subject)
                                        <label class="cursor-pointer rounded-full border border-gray-200 px-3 py-1 text-xs transition has-[:checked]:border-[#967adc] has-[:checked]:bg-[#967adc] has-[:checked]:text-white"
                                               x-show="!subjectSearch || {{ Js::from(mb_strtolower($subject->title)) }}.includes(subjectSearch.toLowerCase())">
                                            <input type="checkbox" name="subject_ids[]" value="{{ $subject->id }}" class="sr-only" :checked="subjects.includes({{ $subject->id }})" @change="toggleSubject({{ $subject->id }})"> {{ $subject->title }}
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-gray-400">No subjects have been set up yet. Add them under Subjects.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </section>
    </div>

    {{-- Side column --}}
    <aside class="space-y-6">
        <section class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-100 lg:sticky lg:top-4">
            <h3 class="mb-4 font-semibold text-gray-900"><i class="fas fa-camera mr-2 text-[#42a5dc]"></i>Photo</h3>
            <div class="flex flex-col items-center">
                <div class="mb-3 flex h-36 w-36 items-center justify-center overflow-hidden rounded-full border-2 border-dashed border-gray-300 bg-gray-50">
                    <img x-show="preview" :src="preview" class="h-full w-full object-cover" alt="">
                    <div x-show="!preview" class="h-full w-full">
                        @if($isEdit && $teacher->profile_photo)
                            <img src="{{ asset('storage/' . $teacher->profile_photo) }}" alt="{{ $teacher->name }}" class="h-full w-full object-cover">
                        @else
                            <div class="flex h-full items-center justify-center text-gray-400"><i class="fas fa-chalkboard-teacher text-4xl"></i></div>
                        @endif
                    </div>
                </div>
                <label class="cursor-pointer rounded-lg border border-[#42a5dc] px-4 py-2 text-sm font-semibold text-[#2c8fc6] hover:bg-[#42a5dc]/10">
                    <i class="fas fa-upload mr-1"></i> Choose photo
                    <input type="file" name="profile_photo" accept="image/*" class="sr-only" @change="showPhoto($event)">
                </label>
                <p class="mt-2 text-center text-xs text-gray-400">Max 2MB.</p>
                <p x-show="photoError" x-cloak class="mt-1 text-center text-xs text-red-600" x-text="photoError"></p>
            </div>

            @unless($isEdit)
                <div class="mt-6 space-y-3 border-t border-gray-100 pt-5">
                    <h4 class="text-sm font-semibold text-gray-900">Portal login</h4>
                    <label class="flex items-start gap-2 text-sm text-gray-700" :class="email ? '' : 'opacity-50'">
                        <input type="checkbox" name="create_login" value="1" x-model="makeLogin" class="mt-0.5 rounded border-gray-300" :disabled="!email">
                        <span>Create a login now
                            <span class="block text-xs text-gray-500" x-text="email ? 'Role: Teacher. A temporary password is shown after saving.' : 'Enter an email address first.'"></span></span>
                    </label>
                    <label class="ml-6 flex items-start gap-2 text-sm text-gray-700" x-show="makeLogin && email" x-cloak :class="phoneOk ? '' : 'opacity-50'">
                        <input type="checkbox" name="send_sms" value="1" class="mt-0.5 rounded border-gray-300" :disabled="!phoneOk">
                        <span>Text the password to their phone <span class="block text-xs text-gray-500">Uses SMS credit</span></span>
                    </label>
                </div>
            @endunless
        </section>
    </aside>
</div>

@push('scripts')
<script>
    function teacherForm(initial, exceptId) {
        return {
            name: initial.name, email: initial.email, phone: initial.phone, staff_id: initial.staff_id,
            subjects: initial.subjects, subjectSearch: '', dupes: [], makeLogin: false, preview: null, photoError: '',
            get phoneOk() {
                var d = (this.phone || '').replace(/\D/g, '');
                return d.length === 10 || d.length === 9 || (d.length === 12 && d.indexOf('233') === 0);
            },
            toggleSubject(id) { var i = this.subjects.indexOf(id); i === -1 ? this.subjects.push(id) : this.subjects.splice(i, 1); },
            toggleLevel(ids) {
                var all = ids.every(function (id) { return this.subjects.indexOf(id) !== -1; }, this);
                this.subjects = all ? this.subjects.filter(function (id) { return ids.indexOf(id) === -1; }) : Array.from(new Set(this.subjects.concat(ids)));
            },
            async check() {
                var params = new URLSearchParams({ name: this.name || '', email: this.email || '', phone: this.phone || '', staff_id: this.staff_id || '' });
                if (exceptId) params.set('except', exceptId);
                try {
                    var r = await fetch('{{ route('teachers.duplicates') }}?' + params, { headers: { 'Accept': 'application/json' } });
                    this.dupes = r.ok ? await r.json() : [];
                } catch (e) { this.dupes = []; }
            },
            showPhoto(e) {
                var file = e.target.files[0];
                this.photoError = '';
                if (!file) { this.preview = null; return; }
                if (file.size > 2 * 1024 * 1024) { this.photoError = 'That photo is over 2MB - choose a smaller one.'; e.target.value = ''; this.preview = null; return; }
                this.preview = URL.createObjectURL(file);
            },
            init() { if (this.name || this.email) this.check(); },
        };
    }
</script>
@endpush
