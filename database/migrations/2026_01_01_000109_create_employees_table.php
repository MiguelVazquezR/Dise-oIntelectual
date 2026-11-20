<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_number')->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('second_last_name')->nullable();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('position');
            $table->string('department')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->date('hire_date');
            $table->date('termination_date')->nullable();
            $table->enum('status', ['active', 'inactive', 'terminated'])->default('active');
            $table->string('pin_hash')->nullable();
            $table->timestamp('pin_updated_at')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['branch_id', 'status']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
