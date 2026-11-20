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
        Schema::create('biometric_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('provider')->default('aws_rekognition');
            $table->string('collection_id');
            $table->string('external_face_id')->nullable();
            $table->enum('status', ['pending', 'enrolled', 'failed', 'revoked'])->default('pending');
            $table->timestamp('consent_given_at')->nullable();
            $table->string('consent_version')->nullable();
            $table->timestamp('enrolled_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('biometric_enrollments');
    }
};
