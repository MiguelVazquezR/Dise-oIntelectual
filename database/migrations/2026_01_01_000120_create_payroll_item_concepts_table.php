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
        Schema::create('payroll_item_concepts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_item_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->enum('type', ['earning', 'deduction']);
            $table->decimal('quantity', 8, 2)->default(0);
            $table->decimal('unit_amount', 12, 2)->default(0);
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('rule_applied')->nullable();
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->timestamps();

            $table->index(['payroll_item_id', 'type']);
            $table->index(['source_type', 'source_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_item_concepts');
    }
};
