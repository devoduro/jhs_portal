<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\FeeCategory;
use App\Models\FeeStructure;
use App\Models\Quiz;
use App\Models\Result;
use App\Models\Student;
use App\Models\StudentArrear;
use App\Models\StudentFeeCharge;
use App\Models\StudentPayment;
use App\Models\Subject;
use App\Models\Semester;
use App\Models\Programme;
use App\Models\TimetableEntry;
use App\Traits\ScopesToTeacher;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    use ScopesToTeacher;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware(['auth', 'admin']);
    }
    /**
     * Display the dashboard with key metrics and analytics.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        // Teachers get a scoped dashboard showing only their own subjects/classes/students -
        // no school-wide aggregate data.
        if ($this->isScopedTeacher()) {
            return $this->teacherDashboard();
        }

        // Accountants get a finance-focused dashboard (collections, arrears, debtors) instead
        // of the academic results-focused view below, which isn't relevant to their role.
        if ($this->isAccountant()) {
            return $this->accountantDashboard();
        }

        // Get filter parameters
        $timeRange = $request->input('time_range', 'all');
        $programmeId = $request->input('programme_id');

        // Date filters based on time range
        $dateFilter = null;
        if ($timeRange !== 'all') {
            $now = Carbon::now();
            switch ($timeRange) {
                case 'week':
                    $dateFilter = $now->subDays(7);
                    break;
                case 'month':
                    $dateFilter = $now->subDays(30);
                    break;
                case 'quarter':
                    $dateFilter = $now->subMonths(3);
                    break;
                case 'year':
                    $dateFilter = $now->subYear();
                    break;
            }
        }

        // Get current academic year and semester
        $currentAcademicYear = AcademicYear::where('is_current', true)->first();
        $currentSemester = Semester::where('is_current', true)->first();

        // Calculate academic year progress percentage
        $academicYearProgress = 0;
        if ($currentAcademicYear) {
            $startDate = Carbon::parse($currentAcademicYear->start_date);
            $endDate = Carbon::parse($currentAcademicYear->end_date);
            $today = Carbon::now();

            if ($today->lt($startDate)) {
                $academicYearProgress = 0;
            } elseif ($today->gt($endDate)) {
                $academicYearProgress = 100;
            } else {
                $totalDays = $startDate->diffInDays($endDate);
                $daysPassed = $startDate->diffInDays($today);
                $academicYearProgress = round(($daysPassed / $totalDays) * 100);
            }
        }

        // Calculate semester progress percentage
        $semesterProgress = 0;
        if ($currentSemester) {
            $startDate = Carbon::parse($currentSemester->start_date);
            $endDate = Carbon::parse($currentSemester->end_date);
            $today = Carbon::now();

            if ($today->lt($startDate)) {
                $semesterProgress = 0;
            } elseif ($today->gt($endDate)) {
                $semesterProgress = 100;
            } else {
                $totalDays = $startDate->diffInDays($endDate);
                $daysPassed = $startDate->diffInDays($today);
                $semesterProgress = round(($daysPassed / $totalDays) * 100);
            }
        }

        // Get total number of students with growth percentage
        $studentsQuery = Student::query();
        if ($programmeId) {
            $studentsQuery->where('programme_id', $programmeId);
        }
        $totalStudents = $studentsQuery->count();

        // Calculate student growth percentage
        $lastMonthStudents = Student::where('created_at', '<', Carbon::now()->subMonth())->count();
        $studentGrowth = $lastMonthStudents > 0 ?
            round((($totalStudents - $lastMonthStudents) / $lastMonthStudents) * 100, 1) : 0;

        // Get total subjects with growth percentage
        $subjectsQuery = Subject::query();
        if ($programmeId) {
            $subjectsQuery->whereHas('programmes', fn ($q) => $q->where('programmes.id', $programmeId));
        }
        $totalSubjects = $subjectsQuery->count();

        // Calculate subject growth
        $lastMonthSubjects = Subject::where('created_at', '<', Carbon::now()->subMonth())->count();
        $subjectGrowth = $lastMonthSubjects > 0 ?
            round((($totalSubjects - $lastMonthSubjects) / $lastMonthSubjects) * 100, 1) : 0;

        // Get total transcripts with growth percentage
        $transcriptsQuery = Result::query();
        if ($dateFilter) {
            $transcriptsQuery->where('created_at', '>=', $dateFilter);
        }
        if ($programmeId) {
            $transcriptsQuery->whereHas('student', function ($query) use ($programmeId) {
                $query->where('programme_id', $programmeId);
            });
        }
        $totalTranscripts = $transcriptsQuery->count();

        // Calculate transcript growth
        $lastMonthTranscripts = Result::where('created_at', '<', Carbon::now()->subMonth())->count();
        $transcriptGrowth = $lastMonthTranscripts > 0 ?
            round((($totalTranscripts - $lastMonthTranscripts) / $lastMonthTranscripts) * 100, 1) : 0;

        // Bucket students into WASSCE divisions by their all-time aggregate (best 6 subjects) -
        // the SHS-appropriate equivalent of a GPA distribution. Mirrors ReportController's
        // bucketByDivision() bands (Division 1: 6-12 ... Division 4: 37+).
        $aggregateStudentsQuery = Student::with('results');
        if ($programmeId) {
            $aggregateStudentsQuery->where('programme_id', $programmeId);
        }

        $aggregateDistribution = [
            'Division 1' => 0,
            'Division 2' => 0,
            'Division 3' => 0,
            'Division 4' => 0,
            'Ungraded' => 0,
        ];

        $gradedAggregates = [];

        foreach ($aggregateStudentsQuery->get() as $student) {
            $aggregate = $student->calculateAggregate();

            $standing = match (true) {
                $aggregate === null => 'Ungraded',
                $aggregate <= 12 => 'Division 1',
                $aggregate <= 24 => 'Division 2',
                $aggregate <= 36 => 'Division 3',
                default => 'Division 4',
            };

            $aggregateDistribution[$standing]++;

            if ($aggregate !== null) {
                $gradedAggregates[] = $aggregate;
            }
        }

        // Average aggregate across students who have a complete best-6 (the SHS-appropriate
        // headline figure in place of a tertiary GPA average).
        $averageAggregate = count($gradedAggregates) > 0 ? array_sum($gradedAggregates) / count($gradedAggregates) : 0;

        // Get top 5 performing students (lowest WASSCE aggregate) with optional programme filter
        $studentsQuery = Student::with(['results.subject', 'programme']);

        if ($programmeId) {
            $studentsQuery->where('programme_id', $programmeId);
        }

        $topStudents = $studentsQuery->get()
            ->map(function ($student) {
                $aggregate = $student->calculateAggregate();
                return [
                    'id' => $student->id,
                    'name' => $student->full_name,
                    'email' => $student->email,
                    'index_number' => $student->index_number,
                    'aggregate' => $aggregate,
                    'programme' => $student->programme
                ];
            })
            ->filter(fn ($row) => $row['aggregate'] !== null)
            ->sortBy('aggregate')
            ->take(5)
            ->values();

        // Get recent activities (latest 5 results added) with more details
        $recentActivitiesQuery = Result::with(['student', 'subject', 'semester', 'academicYear']);

        if ($dateFilter) {
            $recentActivitiesQuery->where('created_at', '>=', $dateFilter);
        }

        if ($programmeId) {
            $recentActivitiesQuery->whereHas('student', function ($query) use ($programmeId) {
                $query->where('programme_id', $programmeId);
            });
        }

        $recentActivities = $recentActivitiesQuery
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($result) {
                $activityType = 'grade';
                if ($result->created_at->diffInHours() < 24) {
                    $activityType = 'registration';
                }

                $studentName = $result->student ? $result->student->full_name : 'Unknown';
                $subjectName = $result->subject ? $result->subject->title : 'Unknown Subject';
                $subjectCode = $result->subject ? $result->subject->code : 'Unknown';
                $grade = $result->grade;

                // Create a descriptive message based on activity type
                $message = '';
                if ($activityType === 'registration') {
                    $message = "$studentName registered for $subjectName ($subjectCode)";
                } else { // grade
                    $message = "$studentName received grade $grade in $subjectName ($subjectCode)";
                }

                return [
                    'id' => $result->id,
                    'student_name' => $studentName,
                    'subject_code' => $subjectCode,
                    'description' => $subjectName,
                    'grade' => $grade,
                    'created_at' => $result->created_at->diffForHumans(),
                    'type' => $activityType,
                    'message' => $message,
                    'user' => $studentName,
                    'time' => $result->created_at->diffForHumans()
                ];
            });

        // Get all programmes for filter dropdown
        $programmes = Programme::all();

        return view('dashboard', [
            'totalStudents' => $totalStudents,
            'totalSubjects' => $totalSubjects,
            'averageAggregate' => $averageAggregate,
            'currentAcademicYear' => $currentAcademicYear,
            'currentSemester' => $currentSemester,
            'academicYearProgress' => $academicYearProgress,
            'semesterProgress' => $semesterProgress,
            'aggregateDistribution' => $aggregateDistribution,
            'programmes' => $programmes,
            'recentActivities' => $recentActivities ?? [],
            'topStudents' => $topStudents ?? [],
            'timeRange' => $timeRange,
            'programmeId' => $programmeId
        ]);
    }

    /**
     * Dashboard for a teacher-linked account: only their own subjects, classes, students
     * and workload. Deliberately excludes school-wide aggregate/results data.
     */
    protected function teacherDashboard()
    {
        $teacherId = $this->authTeacherId();
        $currentSemester = Semester::where('is_current', true)->first();

        $subjects = Subject::whereHas('teachers', fn ($q) => $q->where('teachers.id', $teacherId))
            ->with('semester')
            ->orderBy('code')
            ->get();

        $classGroups = TimetableEntry::where('teacher_id', $teacherId)
            ->with('classGroup')
            ->get()
            ->pluck('classGroup')
            ->filter()
            ->unique('id')
            ->values();

        $studentCount = count($this->teacherStudentIds());

        $workload = $currentSemester
            ? TimetableController::calculateWorkload($teacherId, $currentSemester->id)
            : ['classes' => 0, 'workload' => 0.0];

        $todayEntries = collect();
        if ($currentSemester) {
            $todayEntries = TimetableController::fetchEntries([
                'teacher_id' => $teacherId,
                'semester_id' => $currentSemester->id,
            ])->where('day_of_week', now()->dayOfWeek)->sortBy('start_time')->values();
        }

        $subjectIds = $subjects->pluck('id');

        $activeQuizzesCount = Quiz::whereIn('subject_id', $subjectIds)->where('is_active', true)->count();
        $activeAssignmentsCount = Assignment::whereIn('subject_id', $subjectIds)->where('is_active', true)->count();
        $pendingGradingCount = AssignmentSubmission::whereHas('assignment', fn ($q) => $q->whereIn('subject_id', $subjectIds))
            ->whereNotNull('submitted_at')
            ->whereNull('graded_at')
            ->count();

        return view('dashboard-teacher', compact(
            'currentSemester', 'subjects', 'classGroups', 'studentCount', 'workload', 'todayEntries',
            'activeQuizzesCount', 'activeAssignmentsCount', 'pendingGradingCount'
        ));
    }

    /**
     * Determine whether the authenticated user should see the finance-focused dashboard
     * instead of the academic one - an Accountant, unless they're also a Super Admin (who
     * should keep seeing the full school-wide view).
     */
    protected function isAccountant(): bool
    {
        $user = Auth::user();

        return $user && $user->hasRole('Accountant') && !$user->hasRole('Super Admin');
    }

    /**
     * Finance dashboard for Accountant-role users: collections, outstanding balances, debtors,
     * and revenue breakdowns for the current academic year - no results/grade data.
     *
     * Every figure here is computed via grouped bulk queries plus a single pass over active
     * students, mirroring StudentPaymentController::buildFeeRows() - never a per-student query
     * in a loop, since that would mean thousands of queries across the whole student body.
     */
    protected function accountantDashboard()
    {
        $currentAcademicYear = AcademicYear::where('is_current', true)->first();
        $academicYears = AcademicYear::orderByDesc('start_date')->get();

        if (!$currentAcademicYear) {
            return view('dashboard-accountant', [
                'currentAcademicYear' => null,
                'academicYears' => $academicYears,
            ]);
        }

        $yearId = $currentAcademicYear->id;

        $students = Student::where('status', 'active')
            ->whereNotNull('programme_id')
            ->get(['id', 'programme_id', 'level']);

        $tuitionStructures = FeeStructure::where('academic_year_id', $yearId)
            ->where('category', 'tuition')
            ->get();

        $paymentSums = StudentPayment::where('academic_year_id', $yearId)
            ->selectRaw('student_id, SUM(amount) as total')
            ->groupBy('student_id')
            ->pluck('total', 'student_id');

        $tuitionChargeSums = StudentFeeCharge::where('academic_year_id', $yearId)
            ->where('category', 'tuition')
            ->selectRaw('student_id, SUM(amount) as total')
            ->groupBy('student_id')
            ->pluck('total', 'student_id');

        $arrearSums = StudentArrear::selectRaw('student_id, SUM(amount) as total')
            ->groupBy('student_id')
            ->pluck('total', 'student_id');

        $programmes = Programme::all()->keyBy('id');

        $totalBilled = 0.0;
        $totalCollected = 0.0;
        $totalArrearsNet = 0.0;
        $debtorCount = 0;
        $creditorCount = 0;
        $settledCount = 0;
        $programmeSummary = [];
        $debtorBalances = [];

        foreach ($students as $student) {
            $structure = $tuitionStructures->first(fn (FeeStructure $s) => (int) $s->programme_id === (int) $student->programme_id && (int) $s->level === (int) $student->level)
                ?? $tuitionStructures->first(fn (FeeStructure $s) => (int) $s->programme_id === (int) $student->programme_id && $s->level === null);

            $feeAmount = ($structure ? (float) $structure->amount : 0.0) + (float) ($tuitionChargeSums[$student->id] ?? 0);
            $paid = (float) ($paymentSums[$student->id] ?? 0);
            $arrears = (float) ($arrearSums[$student->id] ?? 0);
            $balance = ($feeAmount + $arrears) - $paid;

            $totalBilled += $feeAmount;
            $totalCollected += $paid;
            $totalArrearsNet += $arrears;

            if ($balance > 0.01) {
                $debtorCount++;
                $debtorBalances[$student->id] = $balance;
            } elseif ($balance < -0.01) {
                $creditorCount++;
            } else {
                $settledCount++;
            }

            $pid = $student->programme_id;
            if (!isset($programmeSummary[$pid])) {
                $programmeSummary[$pid] = [
                    'name' => $programmes[$pid]->name ?? 'Unknown',
                    'billed' => 0.0,
                    'collected' => 0.0,
                    'students' => 0,
                ];
            }
            $programmeSummary[$pid]['billed'] += $feeAmount;
            $programmeSummary[$pid]['collected'] += $paid;
            $programmeSummary[$pid]['students']++;
        }

        $totalOutstanding = $totalBilled - $totalCollected;
        $collectionRate = $totalBilled > 0 ? round(($totalCollected / $totalBilled) * 100, 1) : 0.0;

        arsort($debtorBalances);
        $topDebtorIds = array_slice(array_keys($debtorBalances), 0, 10, true);
        $topDebtorStudents = Student::whereIn('id', $topDebtorIds)->with('programme')->get()->keyBy('id');
        $topDebtors = collect($topDebtorIds)
            ->map(fn ($id) => isset($topDebtorStudents[$id]) ? [
                'student' => $topDebtorStudents[$id],
                'balance' => $debtorBalances[$id],
            ] : null)
            ->filter()
            ->values();

        $programmeSummary = collect($programmeSummary)
            ->map(function ($row) {
                $row['outstanding'] = $row['billed'] - $row['collected'];
                $row['collection_rate'] = $row['billed'] > 0 ? round(($row['collected'] / $row['billed']) * 100, 1) : 0.0;
                return $row;
            })
            ->sortByDesc('outstanding')
            ->values();

        $recentPayments = StudentPayment::with(['student', 'academicYear'])
            ->latest('id')
            ->take(10)
            ->get();

        $monthlyCollections = [];
        for ($i = 11; $i >= 0; $i--) {
            $month = Carbon::now()->subMonths($i);
            $monthlyCollections[] = [
                'month' => $month->format('M Y'),
                'total' => (float) StudentPayment::whereYear('payment_date', $month->year)
                    ->whereMonth('payment_date', $month->month)
                    ->sum('amount'),
            ];
        }

        $paymentMethodBreakdown = StudentPayment::where('academic_year_id', $yearId)
            ->selectRaw('payment_method, SUM(amount) as total')
            ->groupBy('payment_method')
            ->pluck('total', 'payment_method');

        $categoryBreakdown = StudentFeeCharge::where('academic_year_id', $yearId)
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->get()
            ->mapWithKeys(fn ($row) => [(FeeCategory::options()[$row->category] ?? ucfirst($row->category)) => (float) $row->total]);

        // Programme/level combinations with active students but no tuition fee structure set
        // for the current year - an actionable gap (those students would show "Not set").
        $missingStructures = Student::where('status', 'active')
            ->whereNotNull('programme_id')
            ->whereNotNull('level')
            ->select('programme_id', 'level')
            ->distinct()
            ->get()
            ->reject(fn ($row) => $tuitionStructures->contains(fn (FeeStructure $s) => (int) $s->programme_id === (int) $row->programme_id && ((int) $s->level === (int) $row->level || $s->level === null)))
            ->map(fn ($row) => [
                'programme' => $programmes[$row->programme_id]->name ?? 'Unknown',
                'level' => $row->level,
            ])
            ->values();

        return view('dashboard-accountant', [
            'currentAcademicYear' => $currentAcademicYear,
            'academicYears' => $academicYears,
            'totalBilled' => $totalBilled,
            'totalCollected' => $totalCollected,
            'totalOutstanding' => $totalOutstanding,
            'totalArrearsNet' => $totalArrearsNet,
            'collectionRate' => $collectionRate,
            'debtorCount' => $debtorCount,
            'creditorCount' => $creditorCount,
            'settledCount' => $settledCount,
            'topDebtors' => $topDebtors,
            'programmeSummary' => $programmeSummary,
            'recentPayments' => $recentPayments,
            'monthlyCollections' => $monthlyCollections,
            'paymentMethodBreakdown' => $paymentMethodBreakdown,
            'categoryBreakdown' => $categoryBreakdown,
            'missingStructures' => $missingStructures,
        ]);
    }
}
