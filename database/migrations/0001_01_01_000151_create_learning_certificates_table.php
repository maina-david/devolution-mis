<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_certificates', function (Blueprint $table): void {
            $table->uuid('id')->primary('learning_certificates_pkey');
            $table->uuid('learning_enrollment_id');
            $table->string('certificate_number', 255);
            $table->string('verification_code', 255);
            $table->string('content_checksum', 64);
            $table->decimal('final_score', 5, 2);
            $table->timestamp('issued_at', 0);
            $table->timestamp('expires_at', 0)->nullable();
            $table->uuid('issued_by');
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->unique(['certificate_number'], 'learning_certificates_certificate_number_unique');
            $table->unique(['learning_enrollment_id'], 'learning_certificates_learning_enrollment_id_unique');
            $table->unique(['verification_code'], 'learning_certificates_verification_code_unique');
            $table->foreign(['issued_by'], 'learning_certificates_issued_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['learning_enrollment_id'], 'learning_certificates_learning_enrollment_id_foreign')
                ->references(['id'])
                ->on('learning_enrollments')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_certificates');
    }
};
