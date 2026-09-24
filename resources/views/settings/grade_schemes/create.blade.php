@extends('components.app-layout')

@section('title', 'Add Grading Scheme')
@section('subtitle', 'Grade bands and remarks printed on terminal reports')

@section('content')
<form method="POST" action="{{ route('settings.grade-schemes.store') }}" class="bg-white rounded-lg shadow-sm p-6 space-y-6">
    @csrf
    @include('settings.grade_schemes._form')
    <div class="flex justify-end gap-3">
        <x-button href="{{ route('settings.grade-schemes') }}" variant="secondary">Cancel</x-button>
        <x-button type="submit" icon="fas fa-check">Create Scheme</x-button>
    </div>
</form>
@endsection
