<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dswg_working_groups', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code', 255);
            $table->string('name', 255);
            $table->text('mandate');
            $table->string('scope', 255)->default('national');
            $table->uuid('lead_organization_id')->nullable();
            $table->uuid('secretariat_user_id');
            $table->string('meeting_frequency', 255)->nullable();
            $table->string('status', 255)->default('active');
            $table->uuid('created_by');
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->uuid('reference_data_release_id')->nullable();
            $table->unique(['code'], 'dswg_working_groups_code_unique');
            $table->foreign(['created_by'], 'dswg_working_groups_created_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['lead_organization_id'], 'dswg_working_groups_lead_organization_id_foreign')
                ->references(['id'])
                ->on('organizations')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['reference_data_release_id'], 'dswg_working_groups_reference_data_release_id_foreign')
                ->references(['id'])
                ->on('reference_data_releases')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['secretariat_user_id'], 'dswg_working_groups_secretariat_user_id_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX dswg_working_groups_scope_index ON public.dswg_working_groups USING btree (scope);
CREATE INDEX dswg_working_groups_scope_status_index ON public.dswg_working_groups USING btree (scope, status);
CREATE INDEX dswg_working_groups_status_index ON public.dswg_working_groups USING btree (status);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('dswg_working_groups');
    }
};
