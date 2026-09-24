@extends('components.app-layout')

@section('title', 'Edit Grading Scheme')
@section('subtitle', $gradeScheme->name)

@section('content')
<form method="POST" action="{{ route('settings.grade-schemes.update', $gradeScheme) }}" class="bg-white rounded-lg shadow-sm p-6 space-y-6">
    @csrf
    @method('PUT')
    @include('settings.grade_schemes._form')
    <div class="flex justify-end gap-3">
        <x-button href="{{ route('settings.grade-schemes') }}" variant="secondary">Cancel</x-button>
        <x-button type="submit" icon="fas fa-check">Save Changes</x-button>
    </div>
</form>
@endsection
