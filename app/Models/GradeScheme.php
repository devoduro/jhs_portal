<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GradeScheme extends Model
{
    use HasFactory;
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'section',
        'is_default',
    ];
    
    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_default' => 'boolean',
    ];
    
    /**
     * Get the grades for this scheme.
     */
    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class)->orderBy('min_score', 'desc');
    }
    
    /**
     * Get the default grade scheme.
     */
    public static function getDefault(): ?self
    {
        return static::where('is_default', true)->first();
    }
    
    /**
     * Get the grade for a given score.
     */
    public function getGradeForScore(float $score): ?string
    {
        $grade = $this->grades()
            ->where('min_score', '<=', $score)
            ->orderBy('min_score', 'desc')
            ->first();
            
        return $grade ? $grade->grade : null;
    }
    
    /**
     * Get the grade point for a given score.
     */
    public function getGradePointForScore(float $score): ?float
    {
        $grade = $this->grades()
            ->where('min_score', '<=', $score)
            ->orderBy('min_score', 'desc')
            ->first();
            
        return $grade ? $grade->grade_value : null;
    }
    
    /**
     * Static methods for compatibility with existing code
     */
    public static function getGrade(float $score): ?string
    {
        $defaultScheme = self::getDefault();
        return $defaultScheme ? $defaultScheme->getGradeForScore($score) : null;
    }
    
    public static function getGradePoint(float $score): ?float
    {
        $defaultScheme = self::getDefault();
        return $defaultScheme ? $defaultScheme->getGradePointForScore($score) : null;
    }
    
    public static function getRemark(float $score, ?int $level = null): ?string
    {
        $scheme = self::forLevel($level);
        $band = $scheme?->bandForScore($score);

        if ($band && $band->remark) {
            return $band->remark;
        }

        if ($score >= 80) return 'Excellent';
        if ($score >= 70) return 'Very Good';
        if ($score >= 60) return 'Good';
        if ($score >= 50) return 'Credit';
        if ($score >= 40) return 'Pass';
        return 'Weak';
    }

    /**
     * The grading scheme for a class level: the scheme assigned to that level's section
     * (pre-school / primary / JHS), falling back to the default scheme.
     */
    public static function forLevel(?int $level): ?self
    {
        static $cache = [];

        $section = \App\Support\FormLabel::section($level) ?? 'default';

        if (!array_key_exists($section, $cache)) {
            $scheme = $section !== 'default'
                ? static::with('grades')->where('section', $section)->first()
                : null;

            if (!$scheme && $section === \App\Support\FormLabel::SECTION_PRESCHOOL) {
                $scheme = static::with('grades')->where('section', \App\Support\FormLabel::SECTION_PRIMARY)->first();
            }

            $cache[$section] = $scheme ?? static::with('grades')->where('is_default', true)->first();
        }

        return $cache[$section];
    }

    /**
     * The grade band a score falls in (highest band whose minimum the score reaches).
     */
    public function bandForScore(float $score): ?Grade
    {
        return $this->grades
            ->sortByDesc('min_score')
            ->first(fn (Grade $grade) => $score >= (float) $grade->min_score);
    }

    /**
     * Grade, grade value and remark for a score at a given class level, in one lookup.
     *
     * @return array{grade: ?string, grade_point: ?float, remark: ?string}
     */
    public static function gradeFor(float $score, ?int $level): array
    {
        $band = self::forLevel($level)?->bandForScore($score);

        return [
            'grade' => $band?->grade,
            'grade_point' => $band ? (float) $band->grade_value : null,
            'remark' => $band?->remark ?? self::getRemark($score),
        ];
    }

    public function getScoreFromGrade(string $grade): ?float
    {
        $gradeRecord = $this->grades()
            ->where('grade', strtoupper($grade))
            ->first();
            
        return $gradeRecord ? $gradeRecord->min_score : null;
    }
}
