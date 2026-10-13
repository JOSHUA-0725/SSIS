<?php

namespace App\Services;

class GradeService
{
    public const PASSING = 3.00;

    /** College scale: 1.00 (highest) to 5.00 (failed). */
    public static function isValid(mixed $grade): bool
    {
        return is_numeric($grade) && $grade >= 1.00 && $grade <= 5.00;
    }

    /** The average of the three grading periods; null until all three are encoded. */
    public static function finalRating(?float $prelim, ?float $midterm, ?float $final): ?float
    {
        if ($prelim === null || $midterm === null || $final === null) {
            return null;
        }

        return round(($prelim + $midterm + $final) / 3, 2);
    }

    public static function remarks(?float $rating): ?string
    {
        if ($rating === null) {
            return null;
        }

        return $rating <= self::PASSING ? 'Passed' : 'Failed';
    }

    /**
     * Weighted average of ratings by units.
     *
     * @param  iterable<array{0: float|null, 1: int}>  $rows  [rating, units] pairs
     */
    public static function gwa(iterable $rows): ?float
    {
        $points = 0;
        $units = 0;
        foreach ($rows as [$rating, $subjectUnits]) {
            if ($rating === null) {
                continue;
            }
            $points += $rating * $subjectUnits;
            $units += $subjectUnits;
        }

        return $units > 0 ? round($points / $units, 2) : null;
    }
}
