<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('model_has_roles', function (Blueprint $table): void {
            $table->uuid('role_id');
            $table->string('model_type', 255);
            $table->uuid('model_uuid');
            $table->primary(['role_id', 'model_uuid', 'model_type'], 'model_has_roles_pkey');
            $table->foreign(['role_id'], 'model_has_roles_role_id_foreign')
                ->references(['id'])
                ->on('roles')
                ->onDelete('cascade')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX model_has_roles_model_uuid_model_type_index ON public.model_has_roles USING btree (model_uuid, model_type);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('model_has_roles');
    }
};
