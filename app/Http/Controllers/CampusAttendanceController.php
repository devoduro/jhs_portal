<?php

namespace App\Http\Controllers;

use App\Exports\CampusAttendanceExport;
use App\Models\House;
use App\Models\Student;
use App\Services\CampusAttendanceReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;

class CampusAttendanceController extends Controller
{
    /**
     * Display the campus gate attendance register, filterable by house,
     * student search, status, and date range.
     */
    public function index(Request $request)
    {
        $houses = House::orderBy('name')->get();

        [$from, $to, $houseId] = $this->resolveFilters($request);

        $students = $this->filteredStudents($houseId, $request->input('search'));
        $report = $this->filteredReport($students, $from, $to, $request->input('status'));

        return view('biometric.campus-attendance.index', compact('houses', 'report', 'from', 'to', 'houseId'));
    }

    /**
     * Printable version of the same register, respecting the current filters.
     */
    public function print(Request $request)
    {
        [$from, $to, $houseId] = $this->resolveFilters($request);

        $house = $houseId ? House::find($houseId) : null;
        $students = $this->filteredStudents($houseId, $request->input('search'));
        $report = $this->filteredReport($students, $from, $to, $request->input('status'));

        return view('biometric.campus-attendance.print', compact('report', 'from', 'to', 'house'));
    }

    /**
     * Download the register as an Excel workbook, respecting the current filters.
     */
    public function export(Request $request)
    {
        [$from, $to, $houseId] = $this->resolveFilters($request);

        $house = $houseId ? House::find($houseId) : null;
        $students = $this->filteredStudents($houseId, $request->input('search'));
        $report = $this->filteredReport($students, $from, $to, $request->input('status'));

        $filename = 'campus-attendance_' . $from->format('Y-m-d') . '_to_' . $to->format('Y-m-d') . '.xlsx';

        return Excel::download(new CampusAttendanceExport($report, $house), $filename);
    }

    /**
     * Parse the shared from/to/house_id filters, defaulting to today for the date range.
     *
     * @return array{0: Carbon, 1: Carbon, 2: ?int}
     */
    protected function resolveFilters(Request $request): array
    {
        $from = $request->filled('from') ? Carbon::parse($request->from) : Carbon::today();
        $to = $request->filled('to') ? Carbon::parse($request->to) : Carbon::today();

        if ($to->lt($from)) {
            $to = $from->copy();
        }

        $houseId = $request->filled('house_id') ? (int) $request->house_id : null;

        return [$from, $to, $houseId];
    }

    /**
     * Students in scope for the report - all students, or just one house's,
     * optionally narrowed further by a name/index number search.
     */
    protected function filteredStudents(?int $houseId, ?string $search)
    {
        $query = Student::with('house')->orderBy('full_name');

        if ($houseId) {
            $query->where('house_id', $houseId);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('index_number', 'like', "%{$search}%");
            });
        }

        return $query->get();
    }

    /**
     * Build the day-by-day register, then optionally drop rows that don't match
     * the requested status ("On Campus" / "Off Campus" / "No Record").
     */
    protected function filteredReport($students, Carbon $from, Carbon $to, ?string $status): array
    {
        $report = CampusAttendanceReportService::build($students, $from, $to);

        if (!$status) {
            return $report;
        }

        $statusLabels = [
            'on_campus' => 'On Campus',
            'off_campus' => 'Off Campus',
            'no_record' => 'No Record',
        ];

        $wanted = $statusLabels[$status] ?? null;

        if (!$wanted) {
            return $report;
        }

        return array_map(
            fn (array $rows) => array_values(array_filter($rows, fn (array $row) => $row['status'] === $wanted)),
            $report
        );
    }
}
