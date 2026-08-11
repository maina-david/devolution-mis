<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_attestations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('assessment_id');
            $table->uuid('attested_by');
            $table->string('attestor_title', 255);
            $table->text('statement');
            $table->string('signature_method', 255)->default('authenticated_account');
            $table->string('content_checksum', 64);
            $table->timestamp('attested_at', 0);
            $table->timestamp('revoked_at', 0)->nullable();
            $table->uuid('revoked_by')->nullable();
            $table->text('revocation_reason')->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->foreign(['assessment_id'], 'assessment_attestations_assessment_id_foreign')
                ->references(['id'])
                ->on('assessments')
                ->onDelete('cascade')
                ->onUpdate('no action');
            $table->foreign(['attested_by'], 'assessment_attestations_attested_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['revoked_by'], 'assessment_attestations_revoked_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX assessment_attestations_attested_at_index ON public.assessment_attestations USING btree (attested_at);
CREATE INDEX assessment_attestations_revoked_at_index ON public.assessment_attestations USING btree (revoked_at);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_attestations');
    }
};
