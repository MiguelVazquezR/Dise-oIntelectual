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
        Schema::create('attendance_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->enum('type', ['clock_in', 'break_start', 'break_end', 'clock_out']);
            $table->timestamp('occurred_at');
            $table->date('work_date');
            $table->enum('method', ['pin', 'face']);
            $table->decimal('gps_latitude', 10, 7)->nullable();
            $table->decimal('gps_longitude', 10, 7)->nullable();
            $table->decimal('gps_accuracy_m', 8, 2)->nullable();
            $table->enum('validation_status', ['valid', 'flagged', 'rejected'])->default('valid');
            $table->text('validation_notes')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('device_label')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'work_date']);
            $table->index(['branch_id', 'work_date']);
            $table->index(['employee_id', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_events');
    }
};
