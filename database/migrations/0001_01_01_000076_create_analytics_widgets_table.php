<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_widgets', function (Blueprint $table): void {
            $table->uuid('id')->primary('analytics_widgets_pkey');
            $table->uuid('analytics_dashboard_id');
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('metric_key', 255);
            $table->string('visualization', 255);
            $table->string('disaggregation', 255)->nullable();
            $table->jsonb('filters')->nullable();
            $table->smallInteger('position')->default(DB::raw('\'1\'::smallint'));
            $table->smallInteger('width')->default(DB::raw('\'1\'::smallint'));
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletesTz('deleted_at', 0);
            $table->unique(['analytics_dashboard_id', 'position'], 'analytics_widgets_analytics_dashboard_id_position_unique');
            $table->foreign(['analytics_dashboard_id'], 'analytics_widgets_analytics_dashboard_id_foreign')
                ->references(['id'])
                ->on('analytics_dashboards')
                ->onDelete('cascade')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX analytics_widgets_metric_key_index ON public.analytics_widgets USING btree (metric_key);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_widgets');
    }
};
