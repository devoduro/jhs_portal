<?php

namespace App\Http\Controllers;

use App\Models\Student;
use Illuminate\Http\Request;

/**
 * Live pupil search behind the top-bar search box: name, admission number or parent's
 * phone/name. Returns the links the signed-in user is allowed to open for each match.
 */
class PupilSearchController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        abort_unless($user->canAny(['manage-students', 'manage-fees', 'view-student-directory']), 403);

        $term = trim((string) $request->query('q', ''));

        if (mb_strlen($term) < 2) {
            return response()->json([]);
        }

        $pupils = Student::with('classGroup')
            ->where(function ($q) use ($term) {
                $q->where('full_name', 'like', "%{$term}%")
                    ->orWhere('index_number', 'like', "%{$term}%")
                    ->orWhere('emergency_contact_phone', 'like', "%{$term}%")
                    ->orWhere('emergency_contact_name', 'like', "%{$term}%");
            })
            ->orderByRaw("status = 'active' DESC")
            ->orderBy('full_name')
            ->limit(8)
            ->get();

        return response()->json($pupils->map(function (Student $student) use ($user) {
            $links = [];

            if ($user->can('manage-students')) {
                $links['profile'] = route('students.show', $student);
            } elseif ($user->can('view-student-directory')) {
                $links['profile'] = route('student-directory.show', $student);
            }

            if ($user->can('manage-fees')) {
                $links['fees'] = route('fees.show', $student);
            }

            if ($user->canAny(['manage-results', 'view-results', 'enter-scores', 'manage-term-reports'])) {
                $links['report'] = route('students.report-card', $student);
            }

            return [
                'name' => $student->full_name,
                'admission' => $student->index_number,
                'class' => $student->classGroup->name ?? \App\Support\FormLabel::label($student->level),
                'status' => $student->status,
                'url' => $links['profile'] ?? $links['fees'] ?? $links['report'] ?? null,
                'links' => $links,
            ];
        })->values());
    }
}
