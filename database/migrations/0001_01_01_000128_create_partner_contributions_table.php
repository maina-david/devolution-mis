<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_contributions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('partner_profile_id');
            $table->uuid('devolution_project_id');
            $table->string('financial_year', 255);
            $table->string('contribution_type', 255);
            $table->decimal('committed_amount', 20, 2)->default(DB::raw('\'0\'::numeric'));
            $table->decimal('disbursed_amount', 20, 2)->default(DB::raw('\'0\'::numeric'));
            $table->decimal('in_kind_value', 20, 2)->default(DB::raw('\'0\'::numeric'));
            $table->char('currency', 3);
            $table->text('description')->nullable();
            $table->string('status', 255)->default('planned');
            $table->uuid('reported_by');
            $table->jsonb('provenance');
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->unique(['partner_profile_id', 'devolution_project_id', 'financial_year', 'contribution_type'], 'partner_contribution_unique');
            $table->foreign(['devolution_project_id'], 'partner_contributions_devolution_project_id_foreign')
                ->references(['id'])
                ->on('devolution_projects')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['partner_profile_id'], 'partner_contributions_partner_profile_id_foreign')
                ->references(['id'])
                ->on('partner_profiles')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['reported_by'], 'partner_contributions_reported_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX partner_contributions_contribution_type_index ON public.partner_contributions USING btree (contribution_type);
CREATE INDEX partner_contributions_financial_year_index ON public.partner_contributions USING btree (financial_year);
CREATE INDEX partner_contributions_status_index ON public.partner_contributions USING btree (status);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_contributions');
    }
};
