<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('release_records', function (Blueprint $table): void {
            $table->uuid('id')->primary('release_records_pkey');
            $table->uuid('deployed_by')->nullable();
            $table->uuid('validated_by')->nullable();
            $table->uuid('rolled_back_by')->nullable();
            $table->string('version', 255);
            $table->char('git_sha', 40);
            $table->string('environment', 30);
            $table->char('artifact_checksum', 64);
            $table->string('change_reference', 255);
            $table->integer('migration_batch')->nullable();
            $table->string('status', 30)->default('deployed');
            $table->timestampTz('deployed_at', 0);
            $table->timestampTz('validated_at', 0)->nullable();
            $table->timestampTz('rolled_back_at', 0)->nullable();
            $table->string('rollback_to_version', 255)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->unique(['environment', 'version'], 'release_records_environment_version_unique');
            $table->foreign(['deployed_by'], 'release_records_deployed_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['rolled_back_by'], 'release_records_rolled_back_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['validated_by'], 'release_records_validated_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX release_records_environment_status_deployed_at_index ON public.release_records USING btree (environment, status, deployed_at);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('release_records');
    }
};
