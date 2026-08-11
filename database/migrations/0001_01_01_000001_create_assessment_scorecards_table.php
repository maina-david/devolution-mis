<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_scorecards', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code', 255);
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('status', 255)->default('active');
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->unique(['code'], 'assessment_scorecards_code_unique');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX assessment_scorecards_name_index ON public.assessment_scorecards USING btree (name);
CREATE INDEX assessment_scorecards_status_index ON public.assessment_scorecards USING btree (status);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_scorecards');
    }
};
