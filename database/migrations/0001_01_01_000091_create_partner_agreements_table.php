<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_agreements', function (Blueprint $table): void {
            $table->uuid('id')->primary('partner_agreements_pkey');
            $table->uuid('partner_profile_id');
            $table->string('reference', 255);
            $table->string('title', 255);
            $table->string('agreement_type', 255);
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->decimal('committed_value', 20, 2)->nullable();
            $table->char('currency', 3);
            $table->text('summary');
            $table->string('document_reference', 255)->nullable();
            $table->string('status', 255)->default('draft');
            $table->uuid('created_by');
            $table->uuid('approved_by')->nullable();
            $table->timestampTz('approved_at', 0)->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->uuid('workflow_instance_id')->nullable();
            $table->unique(['reference'], 'partner_agreements_reference_unique');
            $table->foreign(['approved_by'], 'partner_agreements_approved_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['created_by'], 'partner_agreements_created_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['partner_profile_id'], 'partner_agreements_partner_profile_id_foreign')
                ->references(['id'])
                ->on('partner_profiles')
                ->onDelete('restrict')
                ->onUpdate('no action');
            $table->foreign(['workflow_instance_id'], 'partner_agreements_workflow_instance_id_foreign')
                ->references(['id'])
                ->on('workflow_instances')
                ->onDelete('set null')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX partner_agreements_agreement_type_index ON public.partner_agreements USING btree (agreement_type);
CREATE INDEX partner_agreements_ends_on_index ON public.partner_agreements USING btree (ends_on);
CREATE INDEX partner_agreements_starts_on_index ON public.partner_agreements USING btree (starts_on);
CREATE INDEX partner_agreements_status_index ON public.partner_agreements USING btree (status);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_agreements');
    }
};
