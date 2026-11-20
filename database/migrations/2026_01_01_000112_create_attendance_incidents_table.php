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
        Schema::create('attendance_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->enum('type', [
                'vacation',
                'justified_absence',
                'unjustified_absence',
                'sick_leave',
                'paid_leave',
                'unpaid_leave',
                'worked_rest_day',
                'worked_holiday',
                'other',
            ]);
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('quantity', 6, 2);
            $table->string('reason')->nullable();
            $table->string('evidence_path')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'type', 'start_date', 'end_date']);
            $table->index(['branch_id', 'start_date']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_incidents');
    }
};
