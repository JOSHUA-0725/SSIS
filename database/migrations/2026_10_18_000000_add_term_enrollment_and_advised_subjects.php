<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academic_terms', function (Blueprint $table) {
            // The Registrar opens enrollment of a term; regular students can then enroll in the subjects of their year level.
            $table->boolean('enrollment_open')->default(false);
        });

        // Subjects a department's Faculty opened for one irregular student. The student enrolls only in these.
        Schema::create('advised_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained();
            $table->foreignId('section_id')->constrained('class_sections');
            $table->foreignId('advised_by')->constrained('users');
            $table->timestamps();
            $table->unique(['student_id', 'section_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advised_subjects');
        Schema::table('academic_terms', function (Blueprint $table) {
            $table->dropColumn('enrollment_open');
        });
    }
};
