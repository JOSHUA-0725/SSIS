<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 30)->unique();
        });

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name', 120);
        });

        Schema::create('offices', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name', 80);
            $table->unsignedTinyInteger('sequence_no');
            $table->boolean('is_active')->default(true);
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username', 30)->unique();
            $table->string('password');
            $table->foreignId('role_id')->constrained();
            $table->string('name', 120);
            $table->string('email', 120)->nullable()->unique();
            $table->foreignId('office_id')->nullable()->constrained();
            $table->foreignId('department_id')->nullable()->constrained();
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->unsignedTinyInteger('failed_attempts')->default(0);
            $table->dateTime('locked_until')->nullable();
            $table->dateTime('last_login')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
        Schema::dropIfExists('offices');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('roles');
    }
};
