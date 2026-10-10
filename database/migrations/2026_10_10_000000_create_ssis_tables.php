<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained();
            $table->string('code', 15)->unique();
            $table->string('name', 120);
        });

        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained();
            $table->string('student_no', 15)->unique();
            $table->string('last_name', 50);
            $table->string('first_name', 50);
            $table->string('middle_name', 50)->nullable();
            $table->enum('sex', ['M', 'F'])->nullable();
            $table->date('birth_date')->nullable();
            $table->string('contact_no', 15)->nullable();
            $table->string('address')->nullable();
            $table->foreignId('program_id')->constrained();
            $table->unsignedTinyInteger('year_level')->default(1);
            $table->enum('status', ['Enrolled', 'Not Enrolled', 'LOA', 'Graduated', 'Dropped'])->default('Enrolled');
            $table->timestamps();
        });

        Schema::create('academic_terms', function (Blueprint $table) {
            $table->id();
            $table->char('school_year', 9);
            $table->enum('semester', ['1st', '2nd', 'Summer']);
            $table->boolean('is_active')->default(false);
            $table->boolean('encoding_open')->default(false);
            $table->boolean('grades_released')->default(false);
            $table->unique(['school_year', 'semester']);
        });

        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained();
            $table->string('code', 15)->unique();
            $table->string('title', 120);
            $table->unsignedTinyInteger('units')->default(3);
        });

        Schema::create('class_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained();
            $table->foreignId('term_id')->constrained('academic_terms');
            $table->string('section_code', 15);
            $table->foreignId('faculty_id')->constrained('users');
            $table->enum('sheet_status', ['Draft', 'Submitted', 'Approved', 'Returned'])->default('Draft');
            $table->string('return_remarks')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->dateTime('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained();
            $table->foreignId('section_id')->constrained('class_sections');
            $table->timestamps();
            $table->unique(['student_id', 'section_id']);
        });

        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->unique()->constrained();
            $table->decimal('midterm_grade', 3, 2)->nullable();
            $table->decimal('final_grade', 3, 2)->nullable();
            $table->decimal('final_rating', 3, 2)->nullable();
            $table->enum('remarks', ['Passed', 'Failed', 'INC', 'Dropped'])->nullable();
            $table->foreignId('encoded_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        Schema::create('document_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->decimal('fee_per_copy', 8, 2);
            $table->unsignedTinyInteger('processing_days')->default(3);
            $table->boolean('is_active')->default(true);
        });

        Schema::create('document_requests', function (Blueprint $table) {
            $table->id();
            $table->char('reference_no', 16)->unique();
            $table->foreignId('student_id')->constrained();
            $table->foreignId('document_type_id')->constrained();
            $table->unsignedTinyInteger('copies');
            $table->string('purpose', 150);
            $table->decimal('total_fee', 8, 2);
            $table->enum('status', ['Pending Payment', 'Paid', 'Processing', 'Ready for Pickup', 'Released', 'Rejected', 'Cancelled'])->default('Pending Payment');
            $table->string('reject_reason')->nullable();
            $table->date('claim_date')->nullable();
            $table->foreignId('released_by')->nullable()->constrained('users');
            $table->dateTime('released_at')->nullable();
            $table->timestamps();
        });

        Schema::create('request_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_request_id')->constrained();
            $table->string('status', 30);
            $table->string('remarks')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained();
            $table->foreignId('term_id')->constrained('academic_terms');
            $table->enum('fee_type', ['Tuition', 'Miscellaneous', 'Laboratory', 'Document', 'Other']);
            $table->string('description', 150);
            $table->decimal('amount', 10, 2);
            $table->foreignId('document_request_id')->nullable()->constrained();
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained();
            $table->string('or_number', 20)->nullable()->unique();
            $table->decimal('amount', 10, 2);
            $table->enum('method', ['Cash', 'Bank Deposit', 'E-wallet']);
            $table->string('proof_file')->nullable();
            $table->enum('status', ['For Verification', 'Posted', 'Voided'])->default('Posted');
            $table->foreignId('posted_by')->nullable()->constrained('users');
            $table->dateTime('paid_at');
            $table->timestamps();
        });

        Schema::create('clearances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained();
            $table->foreignId('term_id')->constrained('academic_terms');
            $table->enum('overall_status', ['Pending', 'Cleared'])->default('Pending');
            $table->timestamps();
            $table->unique(['student_id', 'term_id']);
        });

        Schema::create('clearance_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clearance_id')->constrained();
            $table->foreignId('office_id')->constrained();
            $table->enum('status', ['Pending', 'Cleared', 'On Hold'])->default('Pending');
            $table->string('remarks')->nullable();
            $table->foreignId('signed_by')->nullable()->constrained('users');
            $table->dateTime('signed_at')->nullable();
            $table->timestamps();
            $table->unique(['clearance_id', 'office_id']);
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('title', 120);
            $table->string('message');
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->string('action', 50);
            $table->string('table_name', 50)->nullable();
            $table->unsignedBigInteger('record_id')->nullable();
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        foreach ([
            'audit_logs', 'notifications', 'clearance_items', 'clearances', 'payments', 'assessments',
            'request_status_history', 'document_requests', 'document_types', 'grades', 'enrollments',
            'class_sections', 'subjects', 'academic_terms', 'students', 'programs',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
