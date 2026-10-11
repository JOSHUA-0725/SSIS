<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The enrollment line at the Registrar window. Numbers start again at 1 every day.
        Schema::create('queue_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained();
            $table->foreignId('term_id')->constrained('academic_terms');
            $table->date('queue_date');
            $table->unsignedSmallInteger('number');
            $table->enum('status', ['Waiting', 'Serving', 'Done', 'Skipped', 'Cancelled'])->default('Waiting');
            $table->foreignId('served_by')->nullable()->constrained('users');
            $table->dateTime('called_at')->nullable();
            $table->dateTime('finished_at')->nullable();
            $table->timestamps();
            $table->unique(['queue_date', 'number']);
            $table->index(['queue_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_tickets');
    }
};
