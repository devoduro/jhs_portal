{{-- Shared admit / edit pupil form. $student is null when admitting. --}}
@php
    $isEdit = isset($student);
    $classInfo = $classInfo ?? [];
    $currentTerm = $currentTerm ?? null;
    $field = 'mt-1 block w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-[#42a5dc] focus:ring-[#42a5dc]/30';
    $labelCls = 'block text-sm font-medium text-gray-700';
    $initial = [
        'name' => old('full_name', $student->full_name ?? ''),
        'dob' => old('date_of_birth', isset($student->date_of_birth) ? $student->date_of_birth->format('Y-m-d') : ''),
        'class' => (string) old('class_group_id', $student->class_group_id ?? request('class_group_id') ?? ''),
        'phone' => old('emergency_contact_phone', $student->emergency_contact_phone ?? ''),
    ];
@endphp

@if($errors->any())
    <div class="mb-6 rounded-lg border-l-4 border-red-400 bg-red-50 p-4 text-sm text-red-700">
        <p class="mb-1 font-semibold">Please fix the following:</p>
        <ul class="list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

<div x-data="pupilForm({{ Js::from($initial) }}, {{ Js::from($classInfo) }}, {{ $isEdit ? $student->id : 'null' }})" class="grid grid-cols-1 gap-6 lg:grid-cols-3">

    <div class="space-y-6 lg:col-span-2">

        {{-- 1. Pupil --}}
        <section class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-100">
            <header class="flex items-center gap-3 border-b border-gray-100 bg-gradient-to-r from-[#42a5dc]/10 to-transparent px-6 py-4">
                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-[#42a5dc] text-sm font-bold text-white">1</span>
                <div><h3 class="font-semibold text-gray-900">Pupil details</h3><p class="text-xs text-gray-500">Name as it should appear on reports</p></div>
            </header>
            <div class="grid grid-cols-1 gap-5 p-6 md:grid-cols-2">
                <div class="md:col-span-2">
                    <label class="{{ $labelCls }}">Full name (surname first) <span class="text-red-500">*</span></label>
                    <input type="text" name="full_name" x-model="name" @input.debounce.500ms="checkDuplicates()" required autofocus maxlength="255" placeholder="e.g. MENSAH Kofi Yaw" class="{{ $field }}">

                    <div x-show="dupes.length" x-cloak class="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                        <p class="font-semibold"><i class="fas fa-triangle-exclamation mr-1"></i> A pupil with a similar name is already on record</p>
                        <ul class="mt-1 space-y-1">
                            <template x-for="d in dupes" :key="d.admission">
                                <li><a :href="d.url" target="_blank" class="underline" x-text="d.name"></a>
                                    <span class="text-xs text-amber-700" x-text="' · ' + d.admission + ' · ' + d.class + (d.dob ? ' · born ' + d.dob : '') + (d.status !== 'active' ? ' · ' + d.status : '')"></span></li>
                            </template>
                        </ul>
                        <p class="mt-1 text-xs">If this is a different child, carry on.</p>
                    </div>
                </div>

                <div>
                    <x-input name="index_number" label="Admission number" :value="old('index_number', $student->index_number ?? '')"
                             :placeholder="$suggestedAdmissionNumber ?? ''" :helper="$isEdit ? null : 'Leave blank to use ' . ($suggestedAdmissionNumber ?? 'the next number')" />
                </div>
                <div>
                    <label class="{{ $labelCls }}">Gender <span class="text-red-500">*</span></label>
                    <div class="mt-2 grid grid-cols-2 gap-3">
                        @foreach(['Male' => ['Boy', 'fa-mars'], 'Female' => ['Girl', 'fa-venus']] as $value => [$text, $icon])
                            <label class="flex cursor-pointer items-center justify-center gap-2 rounded-lg border border-gray-300 px-3 py-2.5 text-sm font-medium text-gray-600 transition has-[:checked]:border-[#42a5dc] has-[:checked]:bg-[#42a5dc]/10 has-[:checked]:text-[#2c8fc6]">
                                <input type="radio" name="gender" value="{{ $value }}" class="sr-only" required @checked(old('gender', $student->gender ?? '') === $value)>
                                <i class="fas {{ $icon }}"></i> {{ $text }}
                            </label>
                        @endforeach
                    </div>
                </div>
                <div>
                    <label class="{{ $labelCls }}">Date of birth</label>
                    <input type="date" name="date_of_birth" x-model="dob" max="{{ today()->subDay()->toDateString() }}" @change="checkDuplicates()" class="{{ $field }}">
                    <p class="mt-1 text-xs text-gray-500" x-show="age !== null" x-text="'Age ' + age + ' years'"></p>
                </div>
                <x-input name="hometown" label="Hometown" :value="old('hometown', $student->hometown ?? '')" />
            </div>
        </section>

        {{-- 2. Class --}}
        <section class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-100">
            <header class="flex items-center gap-3 border-b border-gray-100 bg-gradient-to-r from-[#8cc152]/15 to-transparent px-6 py-4">
                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-[#8cc152] text-sm font-bold text-white">2</span>
                <div><h3 class="font-semibold text-gray-900">Class placement</h3><p class="text-xs text-gray-500">Where the pupil sits this term</p></div>
            </header>
            <div class="grid grid-cols-1 gap-5 p-6 md:grid-cols-2">
                <div>
                    <label class="{{ $labelCls }}">Class</label>
                    <select name="class_group_id" x-model="classId" class="{{ $field }}">
                        <option value="">Not placed yet</option>
                        @foreach($classGroups as $classGroup)
                            <option value="{{ $classGroup->id }}">{{ $classGroup->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div x-show="!classId">
                    <label class="{{ $labelCls }}">Class level <span class="text-xs text-gray-400">(needed if not placed in a class)</span></label>
                    <select name="level" class="{{ $field }}" :disabled="!!classId">
                        <option value="">Select...</option>
                        @foreach(\App\Support\FormLabel::options() as $level => $label)
                            <option value="{{ $level }}" @selected(old('level', $student->level ?? '') == $level)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                @if($isEdit)
                    <div>
                        <label class="{{ $labelCls }}">Status</label>
                        <select name="status" class="{{ $field }}">
                            @foreach(['active' => 'In school', 'graduated' => 'Completed / Left after final class', 'withdrawn' => 'Withdrawn / Transferred'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('status', $student->status) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div x-show="info" x-cloak class="flex flex-wrap items-center gap-3 rounded-lg bg-gray-50 px-4 py-3 text-sm text-gray-700 md:col-span-2">
                    <span><i class="fas fa-users text-[#8cc152]"></i> <strong x-text="info.roll"></strong> pupils on roll</span>
                    @if(!$isEdit)
                        <span x-show="info.fees > 0"><i class="fas fa-coins text-[#f5922f]"></i> {{ $currentTerm?->name ?? 'This term' }} fees: <strong x-text="'GH₵ ' + info.fees.toLocaleString(undefined, {minimumFractionDigits: 2})"></strong></span>
                        <span x-show="info.fees <= 0" class="text-gray-400">No fees set for this class this term</span>
                    @endif
                </div>
            </div>
        </section>

        {{-- 3. Parent --}}
        <section class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-100">
            <header class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 bg-gradient-to-r from-[#967adc]/10 to-transparent px-6 py-4">
                <div class="flex items-center gap-3">
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-[#967adc] text-sm font-bold text-white">3</span>
                    <div><h3 class="font-semibold text-gray-900">Parent / guardian</h3><p class="text-xs text-gray-500">Fee receipts, reminders and report links go to these contacts</p></div>
                </div>
                @unless($isEdit)
                    <button type="button" @click="familyOpen = !familyOpen" class="rounded-lg border border-[#967adc] px-3 py-1.5 text-xs font-semibold text-[#7d62c4] hover:bg-[#967adc]/10">
                        <i class="fas fa-people-roof mr-1"></i> Brother / sister already here?
                    </button>
                @endunless
            </header>

            @unless($isEdit)
                <div x-show="familyOpen" x-cloak class="border-b border-gray-100 bg-[#967adc]/5 px-6 py-4">
                    <label class="{{ $labelCls }}">Find the brother or sister - their parent's details will be copied here</label>
                    <input type="text" x-model="familyQuery" @input.debounce.300ms="findFamily()" placeholder="Type a name, admission number or parent's phone..." class="{{ $field }}">
                    <ul class="mt-2 divide-y rounded-lg border bg-white text-sm" x-show="family.length" x-cloak>
                        <template x-for="f in family" :key="f.name + f.class">
                            <li><button type="button" @click="useFamily(f)" class="flex w-full items-center justify-between px-3 py-2 text-left hover:bg-gray-50">
                                <span><span class="font-medium" x-text="f.name"></span> <span class="text-xs text-gray-500" x-text="'· ' + f.class"></span></span>
                                <span class="text-xs text-gray-500" x-text="f.parent.emergency_contact_name || 'no parent recorded'"></span>
                            </button></li>
                        </template>
                    </ul>
                    <p x-show="familyCopied" x-cloak class="mt-2 text-xs font-medium text-green-700"><i class="fas fa-check-circle"></i> Parent details copied - check them below.</p>
                </div>
            @endunless

            <div class="grid grid-cols-1 gap-5 p-6 md:grid-cols-3">
                <x-input name="emergency_contact_name" label="Name" :value="old('emergency_contact_name', $student->emergency_contact_name ?? '')" />
                <div>
                    <label class="{{ $labelCls }}">Relationship</label>
                    <input type="text" name="emergency_contact_relationship" list="relationships" value="{{ old('emergency_contact_relationship', $student->emergency_contact_relationship ?? '') }}" class="{{ $field }}">
                    <datalist id="relationships">
                        @foreach(['Father', 'Mother', 'Guardian', 'Grandparent', 'Uncle', 'Aunt', 'Sibling'] as $r)<option value="{{ $r }}">@endforeach
                    </datalist>
                </div>
                <div>
                    <label class="{{ $labelCls }}"><i class="fas fa-sms text-green-600 mr-1"></i> Phone (for SMS)</label>
                    <input type="tel" name="emergency_contact_phone" x-model="phone" placeholder="024 123 4567" inputmode="tel" class="{{ $field }}" :class="phone && !phoneOk ? 'border-amber-400' : ''">
                    <p class="mt-1 text-xs" :class="phone && !phoneOk ? 'text-amber-600' : 'text-gray-500'" x-text="phone && !phoneOk ? 'That does not look like a full Ghana number (10 digits).' : 'Spaces are fine - saved as 0241234567.'"></p>
                </div>
                <div>
                    <label class="{{ $labelCls }}"><i class="fas fa-envelope text-blue-600 mr-1"></i> Parent email</label>
                    <input type="email" name="parent_email" value="{{ old('parent_email', $student->parent_email ?? '') }}" placeholder="for results & fee statements" class="{{ $field }}">
                </div>
                <x-input name="phone" type="tel" label="Other phone" :value="old('phone', $student->phone ?? '')" />
                <x-input name="email" type="email" label="Pupil's own email" :value="old('email', $student->email ?? '')" />
                <x-input name="gps_address" label="GhanaPost GPS" placeholder="e.g. GA-123-4567" :value="old('gps_address', $student->gps_address ?? '')" />
                <div class="md:col-span-3">
                    <label class="{{ $labelCls }}">Residential address</label>
                    <textarea name="address" rows="2" class="{{ $field }}">{{ old('address', $student->address ?? '') }}</textarea>
                </div>
            </div>
        </section>
    </div>

    {{-- Side column --}}
    <aside class="space-y-6">
        <section class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-100 lg:sticky lg:top-4">
            <h3 class="mb-4 font-semibold text-gray-900"><i class="fas fa-camera mr-2 text-[#42a5dc]"></i>Photo</h3>
            <div class="flex flex-col items-center">
                <div class="relative mb-3 flex h-44 w-36 items-center justify-center overflow-hidden rounded-lg border-2 border-dashed border-gray-300 bg-gray-50">
                    <img x-show="preview" :src="preview" class="h-full w-full object-cover" alt="">
                    @if($isEdit)
                        <div x-show="!preview" class="h-full w-full"><x-student-photo :student="$student" class="h-full w-full object-cover" /></div>
                    @else
                        <div x-show="!preview" class="text-center text-gray-400"><i class="fas fa-user-graduate text-4xl"></i><p class="mt-1 text-xs">No photo yet</p></div>
                    @endif
                </div>
                <label class="cursor-pointer rounded-lg border border-[#42a5dc] px-4 py-2 text-sm font-semibold text-[#2c8fc6] hover:bg-[#42a5dc]/10">
                    <i class="fas fa-upload mr-1"></i> Choose photo
                    <input type="file" name="profile_photo" accept="image/*" class="sr-only" @change="showPhoto($event)">
                </label>
                <p class="mt-2 text-center text-xs text-gray-400">Passport picture, max 2MB. Printed on the terminal report.</p>
                <p x-show="photoError" x-cloak class="mt-1 text-center text-xs text-red-600" x-text="photoError"></p>
            </div>

            @unless($isEdit)
                <div class="mt-6 space-y-3 border-t border-gray-100 pt-5">
                    <h4 class="text-sm font-semibold text-gray-900">On admission</h4>
                    <label class="flex items-start gap-2 text-sm text-gray-700" :class="info && info.fees > 0 ? '' : 'opacity-50'">
                        <input type="checkbox" name="bill_now" value="1" class="mt-0.5 rounded border-gray-300" :disabled="!info || info.fees <= 0" :checked="info && info.fees > 0">
                        <span>Bill {{ $currentTerm?->name ?? "this term's" }} fees now
                            <span class="block text-xs text-gray-500" x-text="info && info.fees > 0 ? 'GH₵ ' + info.fees.toLocaleString(undefined, {minimumFractionDigits: 2}) + ' goes onto their account' : 'Choose a class that has fees set'"></span></span>
                    </label>
                </div>
            @endunless
        </section>
    </aside>
</div>

@push('scripts')
<script>
    function pupilForm(initial, classInfo, exceptId) {
        return {
            name: initial.name, dob: initial.dob, classId: initial.class, phone: initial.phone,
            dupes: [], family: [], familyQuery: '', familyOpen: false, familyCopied: false,
            preview: null, photoError: '',
            get info() { return this.classId ? (classInfo[this.classId] || null) : null; },
            get age() {
                if (!this.dob) return null;
                var d = new Date(this.dob), n = new Date();
                if (isNaN(d)) return null;
                var a = n.getFullYear() - d.getFullYear();
                if (n < new Date(n.getFullYear(), d.getMonth(), d.getDate())) a--;
                return a >= 0 && a < 30 ? a : null;
            },
            get phoneOk() {
                var digits = (this.phone || '').replace(/\D/g, '');
                return digits.length === 10 || (digits.length === 12 && digits.indexOf('233') === 0) || digits.length === 9;
            },
            async checkDuplicates() {
                if ((this.name || '').trim().split(/\s+/).length < 2) { this.dupes = []; return; }
                try {
                    var url = '{{ route('students.duplicates') }}?name=' + encodeURIComponent(this.name) + (exceptId ? '&except=' + exceptId : '');
                    var r = await fetch(url, { headers: { 'Accept': 'application/json' } });
                    this.dupes = r.ok ? await r.json() : [];
                } catch (e) { this.dupes = []; }
            },
            async findFamily() {
                if (this.familyQuery.trim().length < 2) { this.family = []; return; }
                try {
                    var r = await fetch('{{ route('students.family') }}?q=' + encodeURIComponent(this.familyQuery), { headers: { 'Accept': 'application/json' } });
                    this.family = r.ok ? await r.json() : [];
                } catch (e) { this.family = []; }
            },
            useFamily(f) {
                var self = this;
                Object.keys(f.parent).forEach(function (key) {
                    var el = document.querySelector("[name='" + key + "']");
                    if (el && f.parent[key]) { el.value = f.parent[key]; el.dispatchEvent(new Event('input')); }
                });
                this.phone = f.parent.emergency_contact_phone || this.phone;
                this.family = []; this.familyQuery = f.name; this.familyCopied = true;
            },
            showPhoto(e) {
                var file = e.target.files[0];
                this.photoError = '';
                if (!file) { this.preview = null; return; }
                if (file.size > 2 * 1024 * 1024) { this.photoError = 'That photo is over 2MB - choose a smaller one.'; e.target.value = ''; this.preview = null; return; }
                this.preview = URL.createObjectURL(file);
            },
            init() { if (this.name) this.checkDuplicates(); },
        };
    }
</script>
@endpush
