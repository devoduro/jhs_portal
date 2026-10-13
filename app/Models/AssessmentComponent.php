<?php

namespace App\Models;

use App\Services\TerminalReportService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * One column on a class level's score sheet, defined by the admin (Settings > Score Sheets).
 * `type` says whether it counts towards the pupil's class (continuous assessment) score or the
 * exam score on the terminal report.
 */
class AssessmentComponent extends Model
{
    use HasFactory;

    public const TYPE_CLASS = 'class';
    public const TYPE_EXAM = 'exam';

    public const TYPES = [
        self::TYPE_CLASS => 'Class score (continuous assessment)',
        self::TYPE_EXAM => 'Exam score',
    ];

    protected $fillable = ['level', 'label', 'type', 'max_mark', 'position'];

    protected $casts = [
        'level' => 'integer',
        'max_mark' => 'decimal:2',
        'position' => 'integer',
    ];

    public function scores(): HasMany
    {
        return $this->hasMany(ResultComponentScore::class);
    }

    /**
     * Components defined for a level, in the order the admin arranged them.
     *
     * @return Collection<int, AssessmentComponent>
     */
    public static function forLevel(?int $level): Collection
    {
        if ($level === null) {
            return collect();
        }

        return static::where('level', $level)->orderBy('position')->orderBy('id')->get();
    }

    /**
     * The columns a level's score sheet is entered on. With no components defined this is the
     * classic two columns (class score / exam score, split from School Settings) - the same
     * shape as a configured sheet, so callers never have to special-case it.
     *
     * @return Collection<int, array{key: string, id: ?int, label: string, type: string, max: float}>
     */
    public static function sheetFor(?int $level): Collection
    {
        $components = static::forLevel($level);

        if ($components->isEmpty()) {
            return collect([
                ['key' => 'class_score', 'id' => null, 'label' => 'Class Score', 'type' => self::TYPE_CLASS, 'max' => TerminalReportService::classScoreMax()],
                ['key' => 'exam_score', 'id' => null, 'label' => 'Exam Score', 'type' => self::TYPE_EXAM, 'max' => TerminalReportService::examScoreMax()],
            ]);
        }

        return $components->map(fn (self $c) => [
            'key' => 'c' . $c->id,
            'id' => $c->id,
            'label' => $c->label,
            'type' => $c->type,
            'max' => (float) $c->max_mark,
        ]);
    }

    /**
     * Whether a level has its own configured sheet (as opposed to the default two columns).
     */
    public static function isConfigured(?int $level): bool
    {
        return $level !== null && static::where('level', $level)->exists();
    }
}
