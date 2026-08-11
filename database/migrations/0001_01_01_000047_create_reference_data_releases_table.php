<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reference_data_releases', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->integer('version');
            $table->uuid('submitted_by');
            $table->uuid('approved_by')->nullable();
            $table->string('status', 255)->default('submitted');
            $table->text('change_summary');
            $table->jsonb('snapshot');
            $table->char('checksum', 64);
            $table->string('approval_reference', 255)->nullable();
            $table->timestampTz('effective_from', 0)->nullable();
            $table->timestampTz('submitted_at', 0);
            $table->timestampTz('published_at', 0)->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->unique(['version'], 'reference_data_releases_version_unique');
            $table->foreign(['approved_by'], 'reference_data_releases_approved_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['submitted_by'], 'reference_data_releases_submitted_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX reference_data_releases_checksum_index ON public.reference_data_releases USING btree (checksum);
CREATE INDEX reference_data_releases_effective_from_index ON public.reference_data_releases USING btree (effective_from);
CREATE INDEX reference_data_releases_status_index ON public.reference_data_releases USING btree (status);
CREATE TRIGGER reference_data_releases_immutable BEFORE DELETE OR UPDATE ON reference_data_releases FOR EACH ROW EXECUTE FUNCTION prevent_published_reference_release_mutation();
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('reference_data_releases');
    }
};
