{{-- Shared admit / edit pupil form. $student is null when admitting. --}}
@php
    $isEdit = isset($student);
    $sel = 'mt-1 block w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-primary-500 focus:ring-primary-200';
@endphp

@if($errors->any())
    <div class="mb-6 p-4 bg-red-50 border-l-4 border-red-400 text-red-700 text-sm">
        <ul class="list-disc list-inside">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Pupil -->
    <div class="lg:col-span-2 bg-white shadow-sm rounded-lg p-6 space-y-5">
        <h3 class="text-lg font-semibold text-gray-900"><i class="fas fa-child text-primary-600 mr-2"></i>Pupil</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <x-input name="full_name" label="Full name (Surname first)" :value="old('full_name', $student->full_name ?? '')" required autofocus class="md:col-span-2" />
            <x-input name="index_number" label="Admission number" :value="old('index_number', $student->index_number ?? '')"
                     :placeholder="$suggestedAdmissionNumber ?? ''" :helper="$isEdit ? null : 'Leave blank to use ' . ($suggestedAdmissionNumber ?? 'the next number')" />
            <div>
                <label class="block text-sm font-medium text-gray-700">Gender <span class="text-red-500">*</span></label>
                <select name="gender" class="{{ $sel }}" required>
                    <option value="">Select...</option>
                    @foreach(['Male' => 'Boy', 'Female' => 'Girl'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('gender', $student->gender ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <x-input name="date_of_birth" type="date" label="Date of birth" :value="old('date_of_birth', isset($student->date_of_birth) ? $student->date_of_birth->format('Y-m-d') : '')" />
            <div>
                <label class="block text-sm font-medium text-gray-700">Class</label>
                <select name="class_group_id" id="class_group_id" class="{{ $sel }}">
                    <option value="">Not placed yet</option>
                    @foreach($classGroups as $classGroup)
                        <option value="{{ $classGroup->id }}" @selected(old('class_group_id', $student->class_group_id ?? request('class_group_id')) == $classGroup->id)>{{ $classGroup->name }}</option>
                    @endforeach
                </select>
            </div>
            <div id="level-wrapper">
                <label class="block text-sm font-medium text-gray-700">Class level <span class="text-xs text-gray-400">(only if not placed in a class)</span></label>
                <select name="level" class="{{ $sel }}">
                    <option value="">Select...</option>
                    @foreach(\App\Support\FormLabel::options() as $level => $label)
                        <option value="{{ $level }}" @selected(old('level', $student->level ?? '') == $level)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            @if($isEdit)
                <div>
                    <label class="block text-sm font-medium text-gray-700">Status</label>
                    <select name="status" class="{{ $sel }}">
                        @foreach(['active' => 'In school', 'graduated' => 'Completed / Left after final class', 'withdrawn' => 'Withdrawn / Transferred'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', $student->status) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <x-input name="hometown" label="Hometown" :value="old('hometown', $student->hometown ?? '')" />
        </div>
    </div>

    <!-- Photo -->
    <div class="bg-white shadow-sm rounded-lg p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4"><i class="fas fa-camera text-primary-600 mr-2"></i>Photo</h3>
        @if($isEdit)
            <x-student-photo :student="$student" class="w-32 h-36 object-cover rounded border mb-3" />
        @endif
        <input type="file" name="profile_photo" accept="image/*" class="block w-full text-sm text-gray-600">
        <p class="text-xs text-gray-400 mt-2">Passport picture, max 2MB. Printed on the terminal report.</p>
    </div>

    <!-- Parent -->
    <div class="lg:col-span-3 bg-white shadow-sm rounded-lg p-6 space-y-5">
        <h3 class="text-lg font-semibold text-gray-900"><i class="fas fa-user-friends text-primary-600 mr-2"></i>Parent / Guardian</h3>
        <p class="text-sm text-gray-500 -mt-3">Fee receipts, balance reminders and report links are texted to this number.</p>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            <x-input name="emergency_contact_name" label="Name" :value="old('emergency_contact_name', $student->emergency_contact_name ?? '')" />
            <div>
                <label class="block text-sm font-medium text-gray-700">Relationship</label>
                <input type="text" name="emergency_contact_relationship" list="relationships" value="{{ old('emergency_contact_relationship', $student->emergency_contact_relationship ?? '') }}" class="{{ $sel }}">
                <datalist id="relationships">
                    @foreach(['Father', 'Mother', 'Guardian', 'Grandparent', 'Uncle', 'Aunt', 'Sibling'] as $r)<option value="{{ $r }}">@endforeach
                </datalist>
            </div>
            <x-input name="emergency_contact_phone" type="tel" label="Phone (for SMS)" placeholder="024 XXX XXXX" :value="old('emergency_contact_phone', $student->emergency_contact_phone ?? '')" />
            <x-input name="phone" type="tel" label="Other phone" :value="old('phone', $student->phone ?? '')" />
            <x-input name="email" type="email" label="Email" :value="old('email', $student->email ?? '')" />
            <x-input name="gps_address" label="GhanaPost GPS" placeholder="e.g. GA-123-4567" :value="old('gps_address', $student->gps_address ?? '')" />
            <div class="md:col-span-3">
                <label class="block text-sm font-medium text-gray-700">Residential address</label>
                <textarea name="address" rows="2" class="{{ $sel }}">{{ old('address', $student->address ?? '') }}</textarea>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        var cls = document.getElementById('class_group_id'), lvl = document.getElementById('level-wrapper');
        function toggle() { lvl.style.display = cls.value ? 'none' : ''; }
        cls.addEventListener('change', toggle); toggle();
    })();
</script>
