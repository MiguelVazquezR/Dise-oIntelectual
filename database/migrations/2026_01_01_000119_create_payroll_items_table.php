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
        Schema::create('payroll_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->decimal('base_amount', 12, 2)->default(0);
            $table->decimal('overtime_amount', 12, 2)->default(0);
            $table->decimal('bonus_amount', 12, 2)->default(0);
            $table->decimal('adjustment_amount', 12, 2)->default(0);
            $table->decimal('absence_deduction', 12, 2)->default(0);
            $table->integer('late_minutes')->default(0);
            $table->decimal('absence_days', 5, 2)->default(0);
            $table->integer('overtime_minutes')->default(0);
            $table->decimal('net_amount', 12, 2)->default(0);
            $table->json('snapshot')->nullable();
            $table->timestamps();

            $table->unique(['payroll_run_id', 'employee_id']);
            $table->index('employee_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_items');
    }
};
