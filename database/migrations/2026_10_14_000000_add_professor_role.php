<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Professors teach the class sections and encode their grades; Faculty runs the department's enrollment.
        if (! DB::table('roles')->where('name', 'Professor')->exists()) {
            DB::table('roles')->insert(['name' => 'Professor']);
        }
    }

    public function down(): void
    {
        DB::table('roles')->where('name', 'Professor')->delete();
    }
};
