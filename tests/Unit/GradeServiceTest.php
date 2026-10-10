<?php

namespace Tests\Unit;

use App\Services\GradeService;
use PHPUnit\Framework\TestCase;

class GradeServiceTest extends TestCase
{
    public function test_final_rating_is_the_average_of_midterm_and_final(): void
    {
        $this->assertSame(1.75, GradeService::finalRating(2.00, 1.50));
    }

    public function test_final_rating_is_null_until_both_grades_exist(): void
    {
        $this->assertNull(GradeService::finalRating(2.00, null));
        $this->assertNull(GradeService::finalRating(null, 1.50));
    }

    public function test_rating_of_three_is_passed(): void
    {
        $this->assertSame('Passed', GradeService::remarks(3.00));
        $this->assertSame('Passed', GradeService::remarks(1.00));
    }

    public function test_rating_above_three_is_failed(): void
    {
        $this->assertSame('Failed', GradeService::remarks(3.25));
        $this->assertSame('Failed', GradeService::remarks(5.00));
    }

    public function test_grades_outside_the_scale_are_invalid(): void
    {
        $this->assertTrue(GradeService::isValid(1.00));
        $this->assertTrue(GradeService::isValid('5.00'));
        $this->assertFalse(GradeService::isValid(0.50));
        $this->assertFalse(GradeService::isValid(6.00));
        $this->assertFalse(GradeService::isValid('A'));
    }

    public function test_gwa_is_weighted_by_units(): void
    {
        $this->assertSame(1.75, GradeService::gwa([[1.50, 3], [2.00, 3], [1.75, 2]]));
    }

    public function test_gwa_skips_subjects_without_a_rating(): void
    {
        $this->assertSame(1.50, GradeService::gwa([[1.50, 3], [null, 3]]));
        $this->assertNull(GradeService::gwa([[null, 3]]));
    }
}
