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
        Schema::create('vacation_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->smallInteger('anniversary_years');
            $table->decimal('days_granted', 5, 2);
            $table->decimal('days_adjustment', 5, 2)->default(0);
            $table->string('legal_reference')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'period_start']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vacation_periods');
    }
};
