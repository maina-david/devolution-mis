<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_offline_packages', function (Blueprint $table): void {
            $table->uuid('id')->primary('learning_offline_packages_pkey');
            $table->uuid('learning_course_id');
            $table->uuid('generated_by');
            $table->integer('package_version');
            $table->string('status', 255)->default('generating');
            $table->string('locale', 12);
            $table->string('storage_disk', 255)->nullable();
            $table->string('path', 255)->nullable();
            $table->string('original_name', 255)->nullable();
            $table->string('mime_type', 255)->nullable();
            $table->bigInteger('size_bytes')->nullable();
            $table->string('content_checksum', 64)->nullable();
            $table->string('manifest_checksum', 64)->nullable();
            $table->string('course_content_checksum', 64);
            $table->jsonb('manifest_summary')->nullable();
            $table->timestampTz('generated_at', 0)->nullable();
            $table->timestampTz('failed_at', 0)->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->unique(['learning_course_id', 'package_version'], 'learning_offline_packages_learning_course_id_package_version_un');
            $table->foreign(['generated_by'], 'learning_offline_packages_generated_by_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('no action')
                ->onUpdate('no action');
            $table->foreign(['learning_course_id'], 'learning_offline_packages_learning_course_id_foreign')
                ->references(['id'])
                ->on('learning_courses')
                ->onDelete('cascade')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX learning_offline_packages_learning_course_id_status_generated_a ON public.learning_offline_packages USING btree (learning_course_id, status, generated_at);
CREATE INDEX learning_offline_packages_status_index ON public.learning_offline_packages USING btree (status);
CREATE TRIGGER learning_offline_packages_immutable BEFORE DELETE OR UPDATE ON learning_offline_packages FOR EACH ROW EXECUTE FUNCTION protect_ready_learning_offline_packages();
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_offline_packages');
    }
};
