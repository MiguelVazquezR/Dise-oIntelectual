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
        Schema::create('aws_usage_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('operation', ['index_faces', 'search_faces_by_image', 'detect_faces', 'delete_faces']);
            $table->unsignedInteger('quantity')->default(1);
            $table->enum('status', ['succeeded', 'failed']);
            $table->enum('source', ['demo', 'real']);
            $table->string('aws_request_id')->nullable();
            $table->string('idempotency_key')->unique();
            $table->decimal('unit_price_mxn', 10, 4);
            $table->decimal('amount_mxn', 12, 6);
            $table->timestamp('occurred_at');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['occurred_at']);
            $table->index(['branch_id', 'occurred_at']);
            $table->index('source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('aws_usage_records');
    }
};
