<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\ClassGroup;
use App\Models\Quiz;
use App\Models\Result;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentArrear;
use App\Models\StudentFeeCharge;
use App\Models\StudentPayment;
use App\Models\Subject;
use App\Models\TermReport;
use App\Services\TerminalReportService;
use App\Support\FormLabel;
use App\Traits\ScopesToTeacher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Home page after login. Teachers see their own classes and subjects; everyone else sees
 * the school at a glance - pupils per section, this term's fee collection, today's takings,
 * and how far each class is with scores and remarks for the terminal report. Accountants
 * only see the fee panels.
 */
class DashboardController extends Controller
{
    use ScopesToTeacher;

    public function __construct()
    {
        $this->middleware(['auth', 'admin']);
    }

    public function index(Request $request)
    {
        if ($this->isScopedTeacher()) {
            return $this->teacherDashboard();
        }

        $user = $request->user();
        $showFees = $user->can('manage-fees');
        $showAcademics = !$user->hasRole('Accountant') || $user->hasRole('Super Admin');

        $term = Semester::with('academicYear')->where('is_current', true)->first();

        // Pupils by class level / section.
        $pupilsByLevel = Student::where('status', 'active')
            ->selectRaw('level, COUNT(*) as n')->groupBy('level')->pluck('n', 'level');
        $sections = collect(FormLabel::SECTIONS)->map(fn ($label, $key) => [
            'label' => $label,
            'count' => $pupilsByLevel->filter(fn ($n, $level) => FormLabel::section((int) $level) === $key)->sum(),
        ])->filter(fn ($s) => $s['count'] > 0);

        $stats = [
            'pupils' => $pupilsByLevel->sum(),
            'boys' => Student::where('status', 'active')->where('gender', 'Male')->count(),
            'girls' => Student::where('status', 'active')->where('gender', 'Female')->count(),
            'classes' => ClassGroup::count(),
            'teachers' => \App\Models\Teacher::count(),
            'unplaced' => Student::where('status', 'active')->whereNull('class_group_id')->count(),
            'newThisTerm' => $term?->start_date ? Student::where('created_at', '>=', $term->start_date)->count() : 0,
        ];

        // Chart: active pupils per class level, in promotion order.
        $levelChart = collect(FormLabel::options())
            ->map(fn ($label, $level) => ['label' => $label, 'count' => (int) ($pupilsByLevel[$level] ?? 0)])
            ->filter(fn ($row) => $row['count'] > 0)
            ->values();

        $termProgress = self::progress($term?->start_date, $term?->vacation_date ?? $term?->end_date);
        $yearProgress = self::progress($term?->academicYear?->start_date, $term?->academicYear?->end_date);

        $fees = null;
        if ($showFees) {
            $billed = $term ? (float) StudentFeeCharge::where('semester_id', $term->id)->sum('amount') : 0.0;
            $collected = $term ? (float) StudentPayment::where('semester_id', $term->id)->sum('amount') : 0.0;

            // Outstanding across all terms (only pupils who owe, not netted against credits).
            $owed = DB::table('students')
                ->where('status', 'active')
                ->selectRaw('students.id,
                    COALESCE((SELECT SUM(amount) FROM student_fee_charges c WHERE c.student_id = students.id), 0)
                  + COALESCE((SELECT SUM(amount) FROM student_arrears a WHERE a.student_id = students.id), 0)
                  - COALESCE((SELECT SUM(amount) FROM student_payments p WHERE p.student_id = students.id), 0) AS balance')
                ->get()
                ->where('balance', '>', 0.009);

            $fees = [
                'billed' => $billed,
                'collected' => $collected,
                'rate' => $billed > 0 ? round($collected / $billed * 100, 1) : 0,
                'outstanding' => (float) $owed->sum('balance'),
                'debtors' => $owed->count(),
                'today' => (float) StudentPayment::whereDate('payment_date', today())->sum('amount'),
                'todayCount' => StudentPayment::whereDate('payment_date', today())->count(),
                'week' => (float) StudentPayment::where('payment_date', '>=', now()->startOfWeek())->sum('amount'),
                'recent' => StudentPayment::with('student.classGroup')->latest('id')->take(8)->get(),
                'unbilled' => $term ? Student::where('status', 'active')
                    ->whereDoesntHave('feeCharges', fn ($q) => $q->where('semester_id', $term->id))->count() : 0,
                // Charts (same figures as the Collection Report).
                'byClass' => $term ? \App\Services\FeeReportService::byClass($term)['rows'] : collect(),
                'items' => $term ? \App\Services\FeeReportService::byItem($term) : collect(),
                'methods' => $term ? \App\Services\FeeReportService::byMethod($term) : collect(),
                'dailyFrom' => $dailyFrom = ($term?->start_date && $term->start_date->gt(now()->subDays(29)) && $term->start_date->lte(now()))
                    ? $term->start_date->copy() : now()->subDays(29)->startOfDay(),
                'daily' => \App\Services\FeeReportService::daily($dailyFrom, now()),
            ];
        }

        $classProgress = collect();
        if ($showAcademics && $term) {
            $classProgress = ClassGroup::with('classTeacher')->orderBy('level')->orderBy('name')->get()->map(function (ClassGroup $class) use ($term) {
                $roll = TerminalReportService::classRoll($class, $term);
                $subjectCount = Subject::where('level', $class->level)->count();
                $expected = $roll->count() * $subjectCount;
                $entered = $expected ? Result::where('semester_id', $term->id)->whereIn('student_id', $roll->pluck('id'))->count() : 0;
                $remarks = TermReport::where('semester_id', $term->id)->whereIn('student_id', $roll->pluck('id'))
                    ->whereNotNull('class_teacher_remark')->count();

                return [
                    'class' => $class,
                    'roll' => $roll->count(),
                    'scores' => $expected ? min(100, round($entered / $expected * 100)) : 0,
                    'remarks' => $roll->count() ? round($remarks / $roll->count() * 100) : 0,
                ];
            });
        }

        // Best pupils this term by average score (any class).
        $topPupils = collect();
        if ($showAcademics && $term) {
            $topPupils = Result::where('semester_id', $term->id)
                ->selectRaw('student_id, AVG(score) as average, COUNT(*) as subjects')
                ->groupBy('student_id')
                ->havingRaw('COUNT(*) >= 3')
                ->orderByDesc('average')
                ->take(5)
                ->get()
                ->map(fn ($row) => ['student' => Student::with('classGroup')->find($row->student_id), 'average' => round((float) $row->average, 1)])
                ->filter(fn ($row) => $row['student']);
        }

        return view('dashboard', compact(
            'term', 'sections', 'stats', 'fees', 'classProgress', 'showFees', 'showAcademics',
            'levelChart', 'termProgress', 'yearProgress', 'topPupils'
        ));
    }

    /**
     * Percentage of the way from $start to $end today (0-100), or null without both dates.
     */
    public static function progress($start, $end): ?int
    {
        if (!$start || !$end || $end->lte($start)) {
            return null;
        }

        return (int) max(0, min(100, round($start->diffInDays(now()) / $start->diffInDays($end) * 100)));
    }

    /**
     * A teacher's own classes (as class teacher) and subjects, with score-entry progress.
     */
    protected function teacherDashboard()
    {
        $teacherId = $this->authTeacherId() ?? 0;
        $term = Semester::with('academicYear')->where('is_current', true)->first();

        $myClasses = ClassGroup::where('class_teacher_id', $teacherId)->orderBy('level')->get();
        $subjects = Subject::whereHas('teachers', fn ($q) => $q->where('teachers.id', $teacherId))
            ->orderBy('level')->orderBy('title')->get();

        $classCards = $myClasses->map(function (ClassGroup $class) use ($term) {
            $roll = $term ? TerminalReportService::classRoll($class, $term) : collect();
            $subjectCount = Subject::where('level', $class->level)->count();
            $expected = $roll->count() * $subjectCount;
            $entered = ($term && $expected) ? Result::where('semester_id', $term->id)->whereIn('student_id', $roll->pluck('id'))->count() : 0;

            return ['class' => $class, 'roll' => $roll->count(), 'scores' => $expected ? min(100, round($entered / $expected * 100)) : 0];
        });

        $subjectIds = $subjects->pluck('id');
        $activeQuizzesCount = Quiz::whereIn('subject_id', $subjectIds)->where('is_active', true)->count();
        $activeAssignmentsCount = Assignment::whereIn('subject_id', $subjectIds)->where('is_active', true)->count();
        $pendingGradingCount = AssignmentSubmission::whereHas('assignment', fn ($q) => $q->whereIn('subject_id', $subjectIds))
            ->whereNotNull('submitted_at')->whereNull('graded_at')->count();

        return view('dashboard-teacher', compact('term', 'classCards', 'subjects', 'activeQuizzesCount', 'activeAssignmentsCount', 'pendingGradingCount'));
    }
}
