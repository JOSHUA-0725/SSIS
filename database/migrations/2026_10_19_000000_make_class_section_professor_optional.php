<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_sections', function (Blueprint $table) {
            // A class is offered as soon as enrollment opens; Faculty gives it a professor afterwards.
            $table->unsignedBigInteger('faculty_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('class_sections', function (Blueprint $table) {
            $table->unsignedBigInteger('faculty_id')->nullable(false)->change();
        });
    }
};
