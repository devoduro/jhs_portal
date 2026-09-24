<?php

namespace App\Http\Controllers;

use App\Models\ClassGroup;
use App\Models\Semester;
use App\Services\TerminalReportService;
use Illuminate\Http\Request;

/**
 * School-wide academic performance for a term: each class's average and top pupils, and
 * the average in every subject, so the head teacher can see where attention is needed.
 */
class ReportController extends Controller
{
    public function index(Request $request)
    {
        $terms = Semester::with('academicYear')->get()
            ->sortByDesc(fn ($t) => [$t->academicYear?->start_date?->timestamp ?? 0, $t->semester_number])
            ->values();
        $term = $request->filled('semester_id')
            ? $terms->firstWhere('id', (int) $request->semester_id)
            : ($terms->firstWhere('is_current', true) ?? $terms->first());

        $classes = collect();

        if ($term) {
            $classes = ClassGroup::with('classTeacher')->orderBy('level')->orderBy('name')->get()
                ->map(function (ClassGroup $classGroup) use ($term) {
                    $data = TerminalReportService::forClass($classGroup, $term);
                    $scored = $data['pupils']->filter(fn ($p) => $p['average'] !== null);

                    $subjectAverages = $data['subjects']->map(function ($subject) use ($data) {
                        $totals = $data['pupils']->map(fn ($p) => $p['rows']->firstWhere('subject.id', $subject->id)['total'] ?? null)->filter(fn ($t) => $t !== null);

                        return [
                            'subject' => $subject,
                            'average' => $totals->count() ? round($totals->avg(), 1) : null,
                            'count' => $totals->count(),
                        ];
                    })->filter(fn ($s) => $s['average'] !== null)->sortByDesc('average')->values();

                    return [
                        'class' => $classGroup,
                        'roll' => $data['roll'],
                        'scored' => $scored->count(),
                        'average' => $scored->count() ? round($scored->avg('average'), 1) : null,
                        'top' => $scored->sortBy('position')->take(3)->values(),
                        'subjects' => $subjectAverages,
                        'passRate' => $scored->count() ? round($scored->filter(fn ($p) => $p['average'] >= 50)->count() / $scored->count() * 100) : null,
                    ];
                });
        }

        return view('reports.index', compact('terms', 'term', 'classes'));
    }
}
