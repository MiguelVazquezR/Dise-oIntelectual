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
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('date');
            $table->enum('type', ['mandatory', 'company'])->default('mandatory');
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('legal_basis')->nullable();
            $table->string('source_url')->nullable();
            $table->timestamp('source_checked_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['date', 'name', 'branch_id']);
            $table->index(['date', 'branch_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};
