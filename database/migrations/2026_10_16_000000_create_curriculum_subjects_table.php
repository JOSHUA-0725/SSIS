<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The subjects a program requires, placed in the year level and semester they are taken.
        Schema::create('curriculum_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained();
            $table->foreignId('subject_id')->constrained();
            $table->unsignedTinyInteger('year_level');
            $table->enum('semester', ['1st', '2nd', 'Summer']);
            $table->unique(['program_id', 'subject_id']);
            $table->index(['program_id', 'year_level', 'semester']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('curriculum_subjects');
    }
};
