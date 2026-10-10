<?php

namespace App\Http\Controllers;

use App\Models\ClassGroup;
use App\Models\Student;
use App\Services\TranscriptService;
use App\Support\FormLabel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Printable academic transcript: a pupil's scores class by class over a range of levels,
 * e.g. Basic 1 to Basic 6 for a pupil leaving primary, or KG 1 to JHS 3 for a school leaver.
 * One pupil at a time, or a whole class at once (one pupil per page).
 */
class TranscriptController extends Controller
{
    public function show(Request $request, Student $student)
    {
        $request->validate([
            'from' => 'nullable|integer|' . FormLabel::rule(),
            'to' => 'nullable|integer|' . FormLabel::rule(),
        ]);

        [$from, $to] = $this->range($request);
        $transcript = TranscriptService::forStudent($student->load('classGroup'), $from, $to);

        return view('transcripts.print', [
            'transcripts' => collect([$transcript]),
            'settings' => $this->settings(),
            'from' => $from,
            'to' => $to,
            'single' => $student,
        ]);
    }

    /**
     * Choose a class (and level range) to print every pupil's transcript.
     */
    public function index(Request $request)
    {
        $classGroups = ClassGroup::withCount(['students' => fn ($q) => $q->where('status', 'active')])->orderBy('level')->orderBy('name')->get();

        return view('transcripts.index', compact('classGroups'));
    }

    public function bulk(Request $request)
    {
        $request->validate([
            'class_group_id' => 'required|exists:class_groups,id',
            'from' => 'nullable|integer|' . FormLabel::rule(),
            'to' => 'nullable|integer|' . FormLabel::rule(),
        ]);

        [$from, $to] = $this->range($request);
        $students = Student::where('class_group_id', $request->class_group_id)->where('status', 'active')->orderBy('full_name')->get();

        if ($students->isEmpty()) {
            return back()->with('error', 'There are no pupils in that class.');
        }

        return view('transcripts.print', [
            'transcripts' => $students->map(fn ($s) => TranscriptService::forStudent($s->load('classGroup'), $from, $to)),
            'settings' => $this->settings(),
            'from' => $from,
            'to' => $to,
            'single' => null,
        ]);
    }

    private function range(Request $request): array
    {
        $from = $request->integer('from') ?: null;
        $to = $request->integer('to') ?: null;

        if ($from && $to && $from > $to) {
            [$from, $to] = [$to, $from];
        }

        return [$from, $to];
    }

    private function settings(): array
    {
        return DB::table('settings')->whereIn('category', ['institution', 'system'])
            ->orderByRaw("category = 'institution'")->pluck('value', 'key')->toArray();
    }
}
