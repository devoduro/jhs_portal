<?php

namespace App\Imports;

use App\Models\AcademicYear;
use App\Models\AdmissionApplication;
use App\Models\Programme;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Validators\Failure;

/**
 * Bulk-loads the GES CSSPS placement list so placed applicants can find
 * themselves on the portal and pay. Mirrors the old system's "CSSPS
 * Placement List" import (index number, name, gender, aggregate, programme,
 * boarding status - the same columns GES placement exports actually carry),
 * with a few things the old system got wrong fixed:
 *  - columns are matched by header name (WithHeadingRow), not fixed position,
 *    so a reordered spreadsheet can't silently write data into the wrong field.
 *  - re-uploading a corrected file updates a row that's still just "placed"
 *    (nobody has paid or touched it yet) instead of being skipped outright -
 *    but never touches a row an applicant has already started progressing.
 */
class AdmissionImport implements ToCollection, WithHeadingRow, WithValidation, SkipsOnFailure, SkipsEmptyRows
{
    /**
     * CSSPS placement exports don't consistently spell programme names the
     * same way this school's Programme records do. Checked against the old
     * system's own programme list (which included "GENERAL AGRICULTURE").
     */
    protected const PROGRAMME_ALIASES = [
        'general agriculture' => 'agricultural science',
        'agriculture' => 'agricultural science',
        'agric' => 'agricultural science',
        'science' => 'general science',
        'arts' => 'general arts',
        'visual art' => 'visual arts',
        'home econs' => 'home economics',
        'home econ' => 'home economics',
    ];

    protected int $processed = 0;
    protected int $updated = 0;
    protected int $skipped = 0;
    protected array $errors = [];

    /**
     * @param  Failure[]  $failures
     */
    public function onFailure(Failure ...$failures)
    {
        foreach ($failures as $failure) {
            $this->errors[] = 'Row '.$failure->row().': '.implode(', ', $failure->errors());
            $this->skipped++;
        }
    }

    public function collection(Collection $rows)
    {
        $currentYear = AcademicYear::where('is_current', true)->first() ?? AcademicYear::latest('id')->first();

        foreach ($rows as $row) {
            $indexNumber = trim((string) ($row['index_number'] ?? ''));
            $fullName = trim((string) ($row['full_name'] ?? ''));
            $programmeName = trim((string) ($row['programme'] ?? ''));
            $boardingStatus = strtolower(trim((string) ($row['boarding_status'] ?? '')));
            $gender = $this->resolveGender((string) ($row['gender'] ?? ''));
            $aggregate = trim((string) ($row['aggregate_score'] ?? $row['aggregate'] ?? $row['best_six'] ?? ''));

            if ($indexNumber === '' || $fullName === '') {
                $this->skipped++;
                continue;
            }

            $existing = AdmissionApplication::where('index_number', $indexNumber)->first();

            if ($existing && $existing->status !== AdmissionApplication::STATUS_PLACED) {
                $this->errors[] = "Index number {$indexNumber} already has an application in progress (status: "
                    .(AdmissionApplication::STATUS_LABELS[$existing->status] ?? $existing->status).') - not overwritten.';
                $this->skipped++;
                continue;
            }

            $programme = $this->resolveProgramme($programmeName);

            if ($programmeName !== '' && ! $programme) {
                $this->errors[] = "Row for {$indexNumber}: programme \"{$programmeName}\" not found - imported without a programme assigned.";
            }

            $attributes = [
                'full_name' => strtoupper($fullName),
                'academic_year_id' => $currentYear?->id,
                'programme_id' => $programme?->id,
                'boarding_status' => in_array($boardingStatus, ['day', 'boarder'], true) ? $boardingStatus : null,
                'gender' => $gender,
                'aggregate_score' => $aggregate !== '' ? $aggregate : null,
            ];

            if ($existing) {
                $existing->update($attributes);
                $this->updated++;
            } else {
                AdmissionApplication::create($attributes + [
                    'index_number' => $indexNumber,
                    'status' => AdmissionApplication::STATUS_PLACED,
                ]);
                $this->processed++;
            }
        }
    }

    protected function resolveGender(string $raw): ?string
    {
        $normalized = strtoupper(trim($raw));

        return match (true) {
            in_array($normalized, ['M', 'MALE'], true) => 'Male',
            in_array($normalized, ['F', 'FEMALE'], true) => 'Female',
            default => null,
        };
    }

    protected function resolveProgramme(string $name): ?Programme
    {
        if ($name === '') {
            return null;
        }

        $normalized = strtolower($name);

        $programme = Programme::whereRaw('LOWER(name) = ?', [$normalized])->first();
        if ($programme) {
            return $programme;
        }

        if (isset(self::PROGRAMME_ALIASES[$normalized])) {
            $programme = Programme::whereRaw('LOWER(name) = ?', [self::PROGRAMME_ALIASES[$normalized]])->first();
            if ($programme) {
                return $programme;
            }
        }

        // Last resort: a loose match either direction, e.g. "GEN. SCIENCE" vs "General Science".
        return Programme::all()->first(function (Programme $programme) use ($normalized) {
            $candidate = strtolower($programme->name);

            return str_contains($normalized, $candidate) || str_contains($candidate, $normalized);
        });
    }

    public function rules(): array
    {
        return [
            'index_number' => 'required',
            'full_name' => 'required',
        ];
    }

    public function getStats(): array
    {
        return [
            'processed' => $this->processed,
            'updated' => $this->updated,
            'skipped' => $this->skipped,
            'errors' => $this->errors,
        ];
    }
}
