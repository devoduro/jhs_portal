<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Add New Result') }}
            </h2>
            <x-button href="{{ route('results.index') }}" variant="secondary" icon="fas fa-arrow-left">
                {{ __('Back to Results') }}
            </x-button>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <form method="POST" action="{{ route('results.store') }}" class="space-y-6">
                        @csrf
                        
                        <!-- Student Selection -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="student_id" class="block text-sm font-medium text-gray-700 mb-1">Student</label>
                                <select id="student_id" name="student_id" class="shadow-sm focus:ring-primary-500 focus:border-primary-500 block w-full sm:text-sm border-gray-300 rounded-md @error('student_id') border-red-500 @enderror" required>
                                    <option value="">Select Student</option>
                                    @if(isset($student))
                                        <option value="{{ $student->id }}" selected>{{ $student->index_number }} - {{ $student->full_name }}</option>
                                    @else
                                        @foreach($students ?? [] as $student)
                                            <option value="{{ $student->id }}" {{ old('student_id') == $student->id ? 'selected' : '' }}>
                                                {{ $student->index_number }} - {{ $student->full_name }}
                                            </option>
                                        @endforeach
                                    @endif
                                </select>
                                @error('student_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            
                            <div>
                                <label for="subject_id" class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
                                <select id="subject_id" name="subject_id" class="shadow-sm focus:ring-primary-500 focus:border-primary-500 block w-full sm:text-sm border-gray-300 rounded-md @error('subject_id') border-red-500 @enderror" required>
                                    <option value="">Select Subject</option>
                                    @if(isset($subject) && $subject)
                                        <option value="{{ $subject->id }}" selected>{{ $subject->code }} - {{ $subject->title }}</option>
                                    @else
                                        @foreach($subjects ?? [] as $subject)
                                            <option value="{{ $subject->id }}" {{ old('subject_id') == $subject->id ? 'selected' : '' }}>
                                                {{ $subject->code }} - {{ $subject->title }}
                                            </option>
                                        @endforeach
                                    @endif
                                </select>
                                @error('subject_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                        
                        <!-- Academic Period -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="academic_year_id" class="block text-sm font-medium text-gray-700 mb-1">Academic Year</label>
                                <select id="academic_year_id" name="academic_year_id" class="shadow-sm focus:ring-primary-500 focus:border-primary-500 block w-full sm:text-sm border-gray-300 rounded-md @error('academic_year_id') border-red-500 @enderror" required>
                                    <option value="">Select Academic Year</option>
                                    @foreach($academicYears ?? [] as $academicYear)
                                        <option value="{{ $academicYear->id }}" {{ old('academic_year_id', $academicYearId ?? '') == $academicYear->id ? 'selected' : '' }}>
                                            {{ $academicYear->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('academic_year_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            
                            <div>
                                <label for="semester_id" class="block text-sm font-medium text-gray-700 mb-1">Semester</label>
                                <select id="semester_id" name="semester_id" class="shadow-sm focus:ring-primary-500 focus:border-primary-500 block w-full sm:text-sm border-gray-300 rounded-md @error('semester_id') border-red-500 @enderror" required>
                                    <option value="">Select Semester</option>
                                    @foreach($semesters ?? [] as $semester)
                                        <option value="{{ $semester->id }}" {{ old('semester_id', $semesterId ?? '') == $semester->id ? 'selected' : '' }}>
                                            {{ $semester->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('semester_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                        
                        <!-- Result Details -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label for="score" class="block text-sm font-medium text-gray-700 mb-1">Score</label>
                                <div class="mt-1 relative rounded-md shadow-sm">
                                    <input type="number" step="0.01" min="0" max="100" name="score" id="score" value="{{ old('score') }}" class="focus:ring-primary-500 focus:border-primary-500 block w-full pr-12 sm:text-sm border-gray-300 rounded-md @error('score') border-red-500 @enderror" placeholder="0.00" required>
                                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                        <span class="text-gray-500 sm:text-sm">/ 100</span>
                                    </div>
                                </div>
                                @error('score')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            
                            <div>
                                <label for="grade" class="block text-sm font-medium text-gray-700 mb-1">Grade</label>
                                <input type="text" name="grade" id="grade" value="{{ old('grade') }}" class="shadow-sm focus:ring-primary-500 focus:border-primary-500 block w-full sm:text-sm border-gray-300 rounded-md @error('grade') border-red-500 @enderror" placeholder="A, B+, etc." readonly>
                                <p class="mt-1 text-xs text-gray-500">Grade will be calculated automatically based on score</p>
                                @error('grade')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            
                            <div>
                                <label for="grade_point" class="block text-sm font-medium text-gray-700 mb-1">Grade Point</label>
                                <input type="number" step="0.01" min="0" max="4" name="grade_point" id="grade_point" value="{{ old('grade_point') }}" class="shadow-sm focus:ring-primary-500 focus:border-primary-500 block w-full sm:text-sm border-gray-300 rounded-md @error('grade_point') border-red-500 @enderror" placeholder="0.00" readonly>
                                <p class="mt-1 text-xs text-gray-500">Grade point will be calculated automatically</p>
                                @error('grade_point')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                        
                        <!-- Additional Information -->
                        <div>
                            <label for="remarks" class="block text-sm font-medium text-gray-700 mb-1">Remarks (Optional)</label>
                            <textarea id="remarks" name="remarks" rows="3" class="shadow-sm focus:ring-primary-500 focus:border-primary-500 block w-full sm:text-sm border-gray-300 rounded-md @error('remarks') border-red-500 @enderror" placeholder="Any additional notes about this result">{{ old('remarks') }}</textarea>
                            @error('remarks')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        
                        <!-- Submit Button -->
                        <div class="flex justify-end">
                            <x-button type="submit" icon="fas fa-save">
                                {{ __('Save Result') }}
                            </x-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-calculate grade and grade point based on score
            const scoreInput = document.getElementById('score');
            const gradeInput = document.getElementById('grade');
            const gradePointInput = document.getElementById('grade_point');
            
            scoreInput.addEventListener('input', function() {
                const score = parseFloat(this.value);
                let grade = '';
                let gradePoint = 0;

                // WASSCE grading: grade point is 1-9, lower is better.
                if (score >= 80) {
                    grade = 'A1';
                    gradePoint = 1;
                } else if (score >= 75) {
                    grade = 'B2';
                    gradePoint = 2;
                } else if (score >= 70) {
                    grade = 'B3';
                    gradePoint = 3;
                } else if (score >= 65) {
                    grade = 'C4';
                    gradePoint = 4;
                } else if (score >= 60) {
                    grade = 'C5';
                    gradePoint = 5;
                } else if (score >= 55) {
                    grade = 'C6';
                    gradePoint = 6;
                } else if (score >= 50) {
                    grade = 'D7';
                    gradePoint = 7;
                } else if (score >= 45) {
                    grade = 'E8';
                    gradePoint = 8;
                } else {
                    grade = 'F9';
                    gradePoint = 9;
                }

                gradeInput.value = grade;
                gradePointInput.value = gradePoint.toFixed(2);
            });
            
            // Trigger calculation on page load if score already has a value
            if (scoreInput.value) {
                scoreInput.dispatchEvent(new Event('input'));
            }
        });
    </script>
    @endpush
</x-app-layout>
