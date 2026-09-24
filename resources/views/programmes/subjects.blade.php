@extends('layouts.app')

@section('content')
<div class="container-fluid px-4">
    <h1 class="mt-4">{{ $programme->name }} - Subjects</h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="{{ route('programmes.index') }}">Programmes</a></li>
        <li class="breadcrumb-item"><a href="{{ route('programmes.show', $programme->id) }}">{{ $programme->name }}</a></li>
        <li class="breadcrumb-item active">Subjects</li>
    </ol>

    @include('layouts.messages')

    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-table me-1"></i>
            Subject List
        </div>
        <div class="card-body">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Title</th>
                        <th>Semester</th>
                        <th>Credit Hours</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($subjects as $subject)
                    <tr>
                        <td>{{ $subject->code }}</td>
                        <td>{{ $subject->title }}</td>
                        <td>{{ $subject->semester->name }}</td>
                        <td>{{ $subject->credit_hours }}</td>
                        <td>
                            <a href="{{ route('subjects.show', $subject->id) }}" class="btn btn-sm btn-info">
                                <i class="fas fa-eye"></i> View
                            </a>
                            <a href="{{ route('subjects.edit', $subject->id) }}" class="btn btn-sm btn-primary">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center">No subjects found</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="mt-4">
                {{ $subjects->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
