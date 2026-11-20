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
        Schema::create('branch_schedule_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_schedule_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday');
            $table->boolean('is_rest_day')->default(false);
            $table->integer('late_tolerance_minutes')->default(10);
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique(['branch_schedule_id', 'weekday']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branch_schedule_days');
    }
};
