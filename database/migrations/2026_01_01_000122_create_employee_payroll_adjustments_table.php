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
        Schema::create('employee_payroll_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_period_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payroll_concept_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->decimal('quantity', 8, 2)->default(1);
            $table->string('reason');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('applied_payroll_item_id')->nullable()->constrained('payroll_items')->nullOnDelete();
            $table->boolean('is_sensitive')->default(false);
            $table->timestamps();

            $table->index(['employee_id', 'status']);
            $table->index(['payroll_period_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_payroll_adjustments');
    }
};
