<x-admission-layout title="Complete My Profile">
    <div class="bg-white shadow-sm rounded-lg p-8">
        <h1 class="text-xl font-bold text-gray-900 mb-1">Complete Your Profile</h1>
        <p class="text-sm text-gray-500 mb-6">{{ $application->full_name }} &bull; {{ $application->index_number }} &bull; {{ $application->programme->name ?? 'Programme not yet assigned' }}</p>

        <form method="POST" action="{{ route('admission.profile.update') }}" enctype="multipart/form-data" class="space-y-8">
            @csrf

            <div>
                <h2 class="text-sm font-semibold text-gray-900 uppercase tracking-wide mb-3">Personal Details</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Gender</label>
                        <select name="gender" required class="block w-full pl-3 pr-10 py-2 border-gray-300 rounded-md">
                            <option value="">Select&hellip;</option>
                            @foreach(['Male', 'Female', 'Other'] as $option)
                                <option value="{{ $option }}" {{ old('gender', $application->gender) === $option ? 'selected' : '' }}>{{ $option }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date of Birth</label>
                        <input type="date" name="date_of_birth" value="{{ old('date_of_birth', optional($application->date_of_birth)->format('Y-m-d')) }}" required class="block w-full pl-3 pr-3 py-2 border-gray-300 rounded-md">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Boarding Status</label>
                        <select name="boarding_status" required class="block w-full pl-3 pr-10 py-2 border-gray-300 rounded-md">
                            <option value="">Select&hellip;</option>
                            <option value="day" {{ old('boarding_status', $application->boarding_status) === 'day' ? 'selected' : '' }}>Day Student</option>
                            <option value="boarder" {{ old('boarding_status', $application->boarding_status) === 'boarder' ? 'selected' : '' }}>Boarder</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nationality</label>
                        <input type="text" name="nationality" value="{{ old('nationality', $application->nationality) }}" class="block w-full pl-3 pr-3 py-2 border-gray-300 rounded-md">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Religion</label>
                        <input type="text" name="religion" value="{{ old('religion', $application->religion) }}" class="block w-full pl-3 pr-3 py-2 border-gray-300 rounded-md">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Place of Birth</label>
                        <input type="text" name="place_of_birth" value="{{ old('place_of_birth', $application->place_of_birth) }}" class="block w-full pl-3 pr-3 py-2 border-gray-300 rounded-md">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Ghana Card Number</label>
                        <input type="text" name="ghana_card_number" value="{{ old('ghana_card_number', $application->ghana_card_number) }}" class="block w-full pl-3 pr-3 py-2 border-gray-300 rounded-md">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Passport Photo</label>
                        <input type="file" name="photo" accept="image/*" class="block w-full text-sm">
                        @if($application->photo_path)
                            <p class="text-xs text-gray-500 mt-1">A photo is already on file - upload a new one only to replace it.</p>
                        @endif
                    </div>
                </div>
            </div>

            <div>
                <h2 class="text-sm font-semibold text-gray-900 uppercase tracking-wide mb-3">Contact Details</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                        <input type="text" name="phone" value="{{ old('phone', $application->phone) }}" required class="block w-full pl-3 pr-3 py-2 border-gray-300 rounded-md">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Alternate Phone</label>
                        <input type="text" name="alternate_phone" value="{{ old('alternate_phone', $application->alternate_phone) }}" class="block w-full pl-3 pr-3 py-2 border-gray-300 rounded-md">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">WhatsApp Number</label>
                        <input type="text" name="whatsapp_number" value="{{ old('whatsapp_number', $application->whatsapp_number) }}" class="block w-full pl-3 pr-3 py-2 border-gray-300 rounded-md">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input type="email" name="email" value="{{ old('email', $application->email) }}" class="block w-full pl-3 pr-3 py-2 border-gray-300 rounded-md">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Home Address</label>
                        <textarea name="address" rows="2" required class="block w-full pl-3 pr-3 py-2 border-gray-300 rounded-md">{{ old('address', $application->address) }}</textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Town</label>
                        <input type="text" name="town" value="{{ old('town', $application->town) }}" class="block w-full pl-3 pr-3 py-2 border-gray-300 rounded-md">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Region</label>
                        <input type="text" name="region" value="{{ old('region', $application->region) }}" class="block w-full pl-3 pr-3 py-2 border-gray-300 rounded-md">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">District</label>
                        <input type="text" name="district" value="{{ old('district', $application->district) }}" class="block w-full pl-3 pr-3 py-2 border-gray-300 rounded-md">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Digital Address (GhanaPost GPS)</label>
                        <input type="text" name="digital_address" value="{{ old('digital_address', $application->digital_address) }}" class="block w-full pl-3 pr-3 py-2 border-gray-300 rounded-md">
                    </div>
                </div>
            </div>

            <div>
                <h2 class="text-sm font-semibold text-gray-900 uppercase tracking-wide mb-3">Parent / Guardian</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Father's Name</label>
                        <input type="text" name="father_name" value="{{ old('father_name', $application->father_name) }}" class="block w-full pl-3 pr-3 py-2 border-gray-300 rounded-md">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Father's Occupation</label>
                        <input type="text" name="father_occupation" value="{{ old('father_occupation', $application->father_occupation) }}" class="block w-full pl-3 pr-3 py-2 border-gray-300 rounded-md">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Mother's Name</label>
                        <input type="text" name="mother_name" value="{{ old('mother_name', $application->mother_name) }}" class="block w-full pl-3 pr-3 py-2 border-gray-300 rounded-md">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Mother's Occupation</label>
                        <input type="text" name="mother_occupation" value="{{ old('mother_occupation', $application->mother_occupation) }}" class="block w-full pl-3 pr-3 py-2 border-gray-300 rounded-md">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Guardian's Name (if different from parents)</label>
                        <input type="text" name="guardian_name" value="{{ old('guardian_name', $application->guardian_name) }}" class="block w-full pl-3 pr-3 py-2 border-gray-300 rounded-md">
                    </div>
                </div>
            </div>

            <div>
                <h2 class="text-sm font-semibold text-gray-900 uppercase tracking-wide mb-3">JHS / Academic</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">JHS Attended</label>
                        <input type="text" name="jhs_attended" value="{{ old('jhs_attended', $application->jhs_attended) }}" required class="block w-full pl-3 pr-3 py-2 border-gray-300 rounded-md">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">JHS Type</label>
                        <select name="jhs_type" required class="block w-full pl-3 pr-10 py-2 border-gray-300 rounded-md">
                            <option value="">Select&hellip;</option>
                            <option value="Public" {{ old('jhs_type', $application->jhs_type) === 'Public' ? 'selected' : '' }}>Public</option>
                            <option value="Private" {{ old('jhs_type', $application->jhs_type) === 'Private' ? 'selected' : '' }}>Private</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">BECE Aggregate</label>
                        <input type="text" name="aggregate_score" value="{{ old('aggregate_score', $application->aggregate_score) }}" class="block w-full pl-3 pr-3 py-2 border-gray-300 rounded-md">
                    </div>
                </div>
            </div>

            <div class="flex gap-2 pt-4 border-t border-gray-200">
                <button type="submit" name="action" value="save" class="bg-gray-100 text-gray-700 rounded-md px-4 py-2.5 text-sm font-medium hover:bg-gray-200">
                    Save Progress
                </button>
                <button type="submit" name="action" value="submit" class="bg-blue-600 text-white rounded-md px-4 py-2.5 text-sm font-medium hover:bg-blue-700">
                    Submit Application
                </button>
            </div>
        </form>
    </div>
</x-admission-layout>
