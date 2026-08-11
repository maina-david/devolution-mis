<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('igr_resolution_updates', function (Blueprint $table): void {
            $table->uuid('id')->primary('igr_resolution_updates_pkey');
            $table->uuid('igr_resolution_id');
            $table->smallInteger('progress_percentage');
            $table->text('narrative');
            $table->text('implementation_gap')->nullable();
            $table->text('evidence_reference')->nullable();
            $table->uuid('reported_by');
            $table->timestamp('reported_at', 0);
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->foreign(['igr_resolution_id'], 'igr_resolution_updates_igr_resolution_id_foreign')
                ->references(['id'])
                ->on('igr_resolutions')
                ->onDelete('cascade')
                ->onUpdate('no action');
            $table->foreign(['reported_by'], 'igr_resolution_updates_reported_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX igr_resolution_updates_igr_resolution_id_reported_at_index ON public.igr_resolution_updates USING btree (igr_resolution_id, reported_at);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('igr_resolution_updates');
    }
};
