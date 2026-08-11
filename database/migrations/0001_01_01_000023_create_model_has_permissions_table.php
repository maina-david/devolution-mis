<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('model_has_permissions', function (Blueprint $table): void {
            $table->uuid('permission_id');
            $table->string('model_type', 255);
            $table->uuid('model_uuid');
            $table->primary(['permission_id', 'model_uuid', 'model_type'], 'model_has_permissions_pkey');
            $table->foreign(['permission_id'], 'model_has_permissions_permission_id_foreign')
                ->references(['id'])
                ->on('permissions')
                ->onDelete('cascade')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX model_has_permissions_model_uuid_model_type_index ON public.model_has_permissions USING btree (model_uuid, model_type);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('model_has_permissions');
    }
};
