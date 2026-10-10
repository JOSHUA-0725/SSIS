<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Transaction reference of an online payment; one reference can cover several charges.
            $table->string('reference_no', 30)->nullable()->index();
            $table->enum('method', ['Cash', 'Bank Deposit', 'E-wallet', 'GCash', 'Maya', 'Online Banking'])->change();
        });

        Schema::table('document_requests', function (Blueprint $table) {
            $table->enum('release_method', ['Pickup', 'E-copy'])->default('Pickup');
            $table->string('ecopy_file')->nullable();
        });

        Schema::table('document_types', function (Blueprint $table) {
            $table->string('description', 120)->nullable();
        });

        foreach ([
            'Transcript of Records' => 'Official academic record, per copy',
            'Certificate of Enrollment' => 'Proof of current enrollment',
            'Certificate of Grades' => 'Grades for a specific term',
            'Certificate of Good Moral Character' => 'Signed by Student Affairs',
            'Honorable Dismissal' => 'Required for transfer',
            'Certified True Copy of Diploma' => 'Certified copy for graduates',
        ] as $name => $description) {
            DB::table('document_types')->where('name', $name)->update(['description' => $description]);
        }
    }

    public function down(): void
    {
        Schema::table('document_types', fn (Blueprint $table) => $table->dropColumn('description'));
        Schema::table('document_requests', fn (Blueprint $table) => $table->dropColumn(['release_method', 'ecopy_file']));
        Schema::table('payments', fn (Blueprint $table) => $table->dropColumn('reference_no'));
    }
};
