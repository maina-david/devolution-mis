<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programmes', function (Blueprint $table): void {
            $table->uuid('id')->primary('programmes_pkey');
            $table->string('code', 255);
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->uuid('lead_organization_id')->nullable();
            $table->uuid('sector_id')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('status', 255)->default('planned');
            $table->decimal('budget_amount', 18, 2)->nullable();
            $table->char('currency', 3);
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->unique(['code'], 'programmes_code_unique');
            $table->foreign(['lead_organization_id'], 'programmes_lead_organization_id_foreign')
                ->references(['id'])
                ->on('organizations')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['sector_id'], 'programmes_sector_id_foreign')
                ->references(['id'])
                ->on('sectors')
                ->onDelete('set null')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX programmes_ends_on_index ON public.programmes USING btree (ends_on);
CREATE INDEX programmes_name_index ON public.programmes USING btree (name);
CREATE INDEX programmes_starts_on_index ON public.programmes USING btree (starts_on);
CREATE INDEX programmes_status_index ON public.programmes USING btree (status);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('programmes');
    }
};
