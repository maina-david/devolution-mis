<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supply_chain_scans', function (Blueprint $table): void {
            $table->uuid('id')->primary('supply_chain_scans_pkey');
            $table->string('environment', 40);
            $table->string('source_revision', 64)->nullable();
            $table->string('source_state', 30);
            $table->char('composer_lock_checksum', 64);
            $table->char('javascript_lock_checksum', 64);
            $table->string('javascript_lockfile', 255);
            $table->integer('composer_component_count');
            $table->integer('javascript_component_count');
            $table->integer('composer_advisory_count');
            $table->integer('npm_info_count');
            $table->integer('npm_low_count');
            $table->integer('npm_moderate_count');
            $table->integer('npm_high_count');
            $table->integer('npm_critical_count');
            $table->jsonb('finding_codes');
            $table->jsonb('tool_versions');
            $table->string('sbom_format', 40);
            $table->string('sbom_spec_version', 20);
            $table->string('disk', 255);
            $table->string('path', 255)->nullable();
            $table->string('mime_type', 255);
            $table->bigInteger('size_bytes')->nullable();
            $table->char('artifact_checksum', 64)->nullable();
            $table->string('outcome', 20);
            $table->string('failure_category', 255)->nullable();
            $table->uuid('initiated_by')->nullable();
            $table->string('initiated_by_name', 255);
            $table->timestampTz('started_at', 0);
            $table->timestampTz('completed_at', 0);
            $table->char('evidence_checksum', 64);
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->unique(['evidence_checksum'], 'supply_chain_scans_evidence_checksum_unique');
            $table->foreign(['initiated_by'], 'supply_chain_scans_initiated_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX supply_chain_scans_environment_index ON public.supply_chain_scans USING btree (environment);
CREATE INDEX supply_chain_scans_outcome_index ON public.supply_chain_scans USING btree (outcome);
CREATE INDEX supply_chain_scans_outcome_started_at_index ON public.supply_chain_scans USING btree (outcome, started_at);
CREATE INDEX supply_chain_scans_source_revision_index ON public.supply_chain_scans USING btree (source_revision);
CREATE INDEX supply_chain_scans_source_state_index ON public.supply_chain_scans USING btree (source_state);
CREATE INDEX supply_chain_scans_started_at_index ON public.supply_chain_scans USING btree (started_at);
CREATE TRIGGER supply_chain_scans_immutable BEFORE DELETE OR UPDATE ON supply_chain_scans FOR EACH ROW EXECUTE FUNCTION protect_supply_chain_scans();
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('supply_chain_scans');
    }
};
