<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Quizzes') }}</h2>
            <x-button href="{{ route('quizzes.create') }}" icon="fas fa-plus">{{ __('New Quiz') }}</x-button>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if(session('success'))
                <div class="bg-green-50 border border-green-200 text-green-700 rounded-md px-4 py-3 text-sm">{{ session('success') }}</div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <form method="GET" action="{{ route('quizzes.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                        <div>
                            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search title" class="block w-full pl-3 pr-3 py-2 text-sm border-gray-300 rounded-md">
                        </div>
                        <div>
                            <select name="subject_id" class="block w-full pl-3 pr-10 py-2 text-sm border-gray-300 rounded-md">
                                <option value="">All Subjects</option>
                                @foreach($subjects as $subject)
                                    <option value="{{ $subject->id }}" {{ (int) request('subject_id') === $subject->id ? 'selected' : '' }}>{{ $subject->code }} - {{ $subject->title }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex gap-2">
                            <button type="submit" class="bg-primary-600 text-white rounded-md px-4 py-2 text-sm hover:bg-primary-700">
                                <i class="fas fa-filter mr-1"></i> Filter
                            </button>
                            @if(request()->hasAny(['search', 'subject_id']))
                                <a href="{{ route('quizzes.index') }}" class="inline-flex items-center px-3 py-2 text-sm text-gray-600 hover:text-primary-600">Clear</a>
                            @endif
                        </div>
                    </form>
                </div>

                @if($quizzes->isEmpty())
                    <div class="text-center py-12">
                        <i class="fas fa-question-circle text-gray-300 text-5xl mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-500">No quizzes yet</h3>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Class</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Questions</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Attempts</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($quizzes as $quiz)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $quiz->title }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $quiz->subject->code ?? '-' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $quiz->classGroup->name ?? 'All classes' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $quiz->questions_count }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $quiz->attempts_count }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            @if($quiz->is_active)
                                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Active</span>
                                            @else
                                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">Inactive</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <a href="{{ route('quizzes.show', $quiz) }}" class="text-primary-600 hover:text-primary-900"><i class="fas fa-eye"></i></a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="p-4">{{ $quizzes->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
