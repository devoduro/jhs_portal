<?php

namespace App\Http\Controllers;

use App\Models\AssessmentComponent;
use App\Services\TerminalReportService;
use App\Support\FormLabel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Lets the admin design the score sheet teachers type marks into, class level by class level:
 * which columns it has (Exercises, Class Test, Project, Exam...), what each is called, how many
 * marks it carries, and whether it counts towards the class (continuous assessment) score or the
 * exam score on the terminal report. A level with no sheet of its own uses the default
 * class score + exam score split from School Settings.
 *
 * A sheet is always saved whole and must add up to 100, because grades and positions are worked
 * out from a mark out of 100.
 */
class AssessmentSettingController extends Controller
{
    public function index()
    {
        $components = AssessmentComponent::orderBy('level')->orderBy('position')->orderBy('id')->get()->groupBy('level');

        $levels = collect(FormLabel::options())->map(fn ($label, $level) => [
            'level' => $level,
            'label' => $label,
            'components' => $components->get($level, collect()),
            'total' => (float) $components->get($level, collect())->sum('max_mark'),
        ])->values();

        return view('assessment-settings.index', [
            'levels' => $levels,
            'defaultClass' => TerminalReportService::classScoreMax(),
            'defaultExam' => TerminalReportService::examScoreMax(),
        ]);
    }

    public function edit(Request $request, int $level)
    {
        $this->authorizeLevel($level);

        $configured = AssessmentComponent::isConfigured($level);
        $copyFrom = $request->filled('copy_from') ? (int) $request->copy_from : null;

        if ($copyFrom && $copyFrom !== $level) {
            $source = AssessmentComponent::forLevel($copyFrom);
            $rows = $source->map(fn ($c) => ['id' => null, 'label' => $c->label, 'type' => $c->type, 'max_mark' => $this->plain($c->max_mark)])->all();
        } elseif ($configured) {
            $rows = AssessmentComponent::forLevel($level)->map(fn ($c) => ['id' => $c->id, 'label' => $c->label, 'type' => $c->type, 'max_mark' => $this->plain($c->max_mark)])->all();
        } else {
            // Start from the current default split so the admin edits something sensible.
            $rows = [
                ['id' => null, 'label' => 'Class Score', 'type' => 'class', 'max_mark' => $this->plain(TerminalReportService::classScoreMax())],
                ['id' => null, 'label' => 'Exam Score', 'type' => 'exam', 'max_mark' => $this->plain(TerminalReportService::examScoreMax())],
            ];
        }

        $markedIds = $this->markedIds($level);
        $otherLevels = collect(FormLabel::options())->except($level);
        $configuredLevels = AssessmentComponent::distinct()->pluck('level')->map(fn ($l) => (int) $l)->all();

        return view('assessment-settings.edit', [
            'level' => $level,
            'levelLabel' => FormLabel::label($level),
            'rows' => $rows,
            'markedIds' => $markedIds,
            'configured' => $configured,
            'otherLevels' => $otherLevels,
            'configuredLevels' => $configuredLevels,
            'copyFrom' => $copyFrom,
        ]);
    }

    public function update(Request $request, int $level)
    {
        $this->authorizeLevel($level);

        $request->validate([
            'components' => 'required|array|min:1',
            'components.*.id' => 'nullable|integer',
            'components.*.label' => 'required|string|max:60',
            'components.*.type' => 'required|in:' . implode(',', array_keys(AssessmentComponent::TYPES)),
            'components.*.max_mark' => 'required|numeric|min:0.5|max:100',
            'apply_to' => 'nullable|array',
            'apply_to.*' => 'integer|in:' . implode(',', array_keys(FormLabel::OPTIONS)),
        ], [
            'components.required' => 'Add at least one column to the score sheet.',
            'components.*.label.required' => 'Every column needs a name.',
            'components.*.max_mark.required' => 'Every column needs a maximum mark.',
            'components.*.max_mark.min' => 'A column must be worth at least half a mark.',
        ]);

        $rows = collect($request->input('components'))->values()->map(fn ($r) => [
            'id' => !empty($r['id']) ? (int) $r['id'] : null,
            'label' => trim($r['label']),
            'type' => $r['type'],
            'max_mark' => (float) $r['max_mark'],
        ]);

        if ($rows->pluck('label')->map(fn ($l) => mb_strtolower($l))->duplicates()->isNotEmpty()) {
            return back()->withInput()->withErrors(['components' => 'Two columns have the same name. Give each column its own name.']);
        }

        $total = round($rows->sum('max_mark'), 2);
        if (abs($total - 100) > 0.001) {
            return back()->withInput()->withErrors(['components' => 'The columns add up to ' . $this->plain($total) . ', but a score sheet must add up to 100 marks so grades and positions work out correctly.']);
        }

        if ($rows->where('type', AssessmentComponent::TYPE_CLASS)->isEmpty() || $rows->where('type', AssessmentComponent::TYPE_EXAM)->isEmpty()) {
            return back()->withInput()->withErrors(['components' => 'Include at least one class (continuous assessment) column and one exam column - the terminal report shows both.']);
        }

        // Marks already entered against a column pin that column in place: it can't be dropped
        // (the marks are keyed to it) and can't switch between class and exam score.
        $marked = $this->markedIds($level);
        $kept = $rows->pluck('id')->filter()->all();
        $dropped = array_diff($marked, $kept);
        if ($dropped) {
            $names = AssessmentComponent::whereIn('id', $dropped)->pluck('label')->implode(', ');

            return back()->withInput()->withErrors(['components' => "Marks have already been entered for: {$names}. Those columns can be renamed or re-weighted but not removed."]);
        }
        $existingTypes = AssessmentComponent::where('level', $level)->pluck('type', 'id');
        foreach ($rows as $row) {
            if ($row['id'] && in_array($row['id'], $marked, true) && ($existingTypes[$row['id']] ?? $row['type']) !== $row['type']) {
                return back()->withInput()->withErrors(['components' => "\"{$row['label']}\" already has marks, so it cannot move between class score and exam score."]);
            }
        }

        $notes = [];
        DB::transaction(function () use ($level, $rows, $request, &$notes) {
            $this->saveComponents($level, $rows->all());

            foreach ((array) $request->input('apply_to', []) as $target) {
                $target = (int) $target;
                if ($target === $level) {
                    continue;
                }
                // Other levels are matched by column name so their own marks survive.
                $mapped = $rows->map(function ($r) use ($target) {
                    $r['id'] = AssessmentComponent::where('level', $target)->where('label', $r['label'])->value('id');

                    return $r;
                })->all();

                $blocked = array_diff($this->markedIds($target), array_filter(array_column($mapped, 'id')));
                if ($blocked) {
                    $notes[] = FormLabel::label($target) . ' skipped (it already has marks on columns this sheet does not include)';
                    continue;
                }

                $this->saveComponents($target, $mapped);
                $notes[] = FormLabel::label($target) . ' updated';
            }
        });

        $message = FormLabel::label($level) . ' score sheet saved.';
        if ($notes) {
            $message .= ' Also: ' . implode('; ', $notes) . '.';
        }

        return redirect()->route('assessment-settings.index')->with('success', $message);
    }

    public function destroy(int $level)
    {
        $this->authorizeLevel($level);

        if ($this->markedIds($level)) {
            return back()->with('error', FormLabel::label($level) . ' already has marks entered on its columns. Edit the sheet instead of removing it so those marks are not lost.');
        }

        AssessmentComponent::where('level', $level)->delete();

        return redirect()->route('assessment-settings.index')
            ->with('success', FormLabel::label($level) . ' is back on the default class score + exam score sheet.');
    }

    /**
     * Write a level's columns, keeping the ids of rows that already exist so marks entered
     * against them survive renames and re-ordering; columns left out are deleted.
     *
     * @param  array<int, array{id: ?int, label: string, type: string, max_mark: float}>  $rows
     */
    private function saveComponents(int $level, array $rows): void
    {
        $keptIds = [];

        // Park existing labels first so renames/swaps can't trip the (level, label) unique key.
        foreach (AssessmentComponent::where('level', $level)->get() as $existing) {
            $existing->update(['label' => '__tmp_' . $existing->id]);
        }

        foreach (array_values($rows) as $position => $row) {
            $attributes = ['level' => $level, 'label' => $row['label'], 'type' => $row['type'], 'max_mark' => $row['max_mark'], 'position' => $position];
            $existing = $row['id'] ? AssessmentComponent::where('level', $level)->find($row['id']) : null;

            if ($existing) {
                $existing->update($attributes);
                $keptIds[] = $existing->id;
            } else {
                $keptIds[] = AssessmentComponent::create($attributes)->id;
            }
        }

        AssessmentComponent::where('level', $level)->whereNotIn('id', $keptIds ?: [0])->delete();
    }

    /**
     * Component ids of a level that have marks recorded against them.
     *
     * @return array<int, int>
     */
    private function markedIds(int $level): array
    {
        return AssessmentComponent::where('level', $level)
            ->whereHas('scores', fn ($q) => $q->whereNotNull('score'))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function authorizeLevel(int $level): void
    {
        abort_unless(array_key_exists($level, FormLabel::OPTIONS), 404);
    }

    private function plain($number): string
    {
        return rtrim(rtrim(number_format((float) $number, 2, '.', ''), '0'), '.');
    }
}
