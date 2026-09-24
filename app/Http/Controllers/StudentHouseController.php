<?php

namespace App\Http\Controllers;

use App\Exports\HouseAssignmentTemplateExport;
use App\Imports\HouseAssignmentImport;
use App\Models\House;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class StudentHouseController extends Controller
{
    /**
     * Display a listing of students grouped/filterable by house, with per-house counts.
     */
    public function index(Request $request)
    {
        $query = $this->filteredQuery($request);

        $students = $query->with('house')->orderBy('house_id')->orderBy('full_name')->paginate(20)->withQueryString();

        $houseCounts = House::withCount('students')->orderBy('name')->get();

        $unassignedCount = Student::whereNull('house_id')->count();

        return view('student-houses.index', compact('students', 'houseCounts', 'unassignedCount'));
    }

    /**
     * Show the bulk-upload form.
     */
    public function uploadForm()
    {
        return view('student-houses.upload');
    }

    /**
     * Handle the bulk upload of index number -> house mappings.
     */
    public function import(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'excel_file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        if ($validator->fails()) {
            return redirect()->route('student-houses.upload')
                ->withErrors($validator)
                ->withInput();
        }

        try {
            $import = new HouseAssignmentImport();
            Excel::import($import, $request->file('excel_file'));

            $stats = $import->getStats();
            $message = "Processed {$stats['processed']} record(s), skipped {$stats['skipped']}.";

            if (!empty($stats['errors'])) {
                $message .= ' Issues: ' . implode(' | ', array_slice($stats['errors'], 0, 5));
                if (count($stats['errors']) > 5) {
                    $message .= ' (+' . (count($stats['errors']) - 5) . ' more)';
                }

                return redirect()->route('student-houses.index')->with('warning', $message);
            }

            return redirect()->route('student-houses.index')->with('success', $message);
        } catch (\Exception $e) {
            return redirect()->route('student-houses.upload')
                ->with('error', 'Error importing houses: ' . $e->getMessage());
        }
    }

    /**
     * Download the house upload template.
     */
    public function downloadTemplate()
    {
        return Excel::download(new HouseAssignmentTemplateExport, 'student_houses_template.xlsx');
    }

    /**
     * Printable list of students, respecting the current house/search filters.
     */
    public function print(Request $request)
    {
        $students = $this->filteredQuery($request)->with('house')->orderBy('house_id')->orderBy('full_name')->get();

        $house = $request->filled('house_id') ? House::find($request->house_id) : null;

        return view('student-houses.print', compact('students', 'house'));
    }

    /**
     * Build the filtered student query shared by the on-screen list and the print view.
     */
    protected function filteredQuery(Request $request)
    {
        $query = Student::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('index_number', 'like', "%{$search}%");
            });
        }

        if ($request->filled('house_id')) {
            $query->where('house_id', $request->house_id);
        } elseif ($request->filled('status') && $request->status === 'unassigned') {
            $query->whereNull('house_id');
        }

        return $query;
    }
}
