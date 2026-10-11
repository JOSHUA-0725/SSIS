<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            // Regular students take every subject of one block section; irregular students are advised one by one.
            $table->enum('student_type', ['Regular', 'Irregular'])->default('Regular');
            $table->string('block_section', 15)->nullable();
        });

        // A department's faculty asks the Registrar how many irregular and first-year students it may enroll.
        Schema::create('slot_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('term_id')->constrained('academic_terms');
            $table->foreignId('department_id')->constrained();
            $table->enum('category', ['Irregular', 'First Year']);
            $table->unsignedSmallInteger('slots');
            $table->enum('status', ['Pending', 'Approved', 'Rejected'])->default('Pending');
            $table->string('remarks')->nullable();
            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('decided_by')->nullable()->constrained('users');
            $table->dateTime('decided_at')->nullable();
            $table->timestamps();
            $table->index(['term_id', 'department_id', 'category', 'status']);
        });

        // Whether regular students of a department may enroll in their block section this term.
        Schema::create('block_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('term_id')->constrained('academic_terms');
            $table->foreignId('department_id')->constrained();
            $table->boolean('is_open')->default(false);
            $table->foreignId('updated_by')->constrained('users');
            $table->timestamps();
            $table->unique(['term_id', 'department_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('block_enrollments');
        Schema::dropIfExists('slot_requests');
        Schema::table('students', fn (Blueprint $table) => $table->dropColumn(['student_type', 'block_section']));
    }
};
