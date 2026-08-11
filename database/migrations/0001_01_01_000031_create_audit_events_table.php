<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table): void {
            $table->uuid('id')->primary('audit_events_pkey');
            $table->uuid('actor_id')->nullable();
            $table->uuid('county_id')->nullable();
            $table->string('subject_type', 255)->nullable();
            $table->uuid('subject_id')->nullable();
            $table->string('action', 255);
            $table->string('description', 255);
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->timestampTz('occurred_at', 6)->nullable();
            $table->char('previous_hash', 64)->nullable();
            $table->char('event_hash', 64)->nullable();
            $table->smallInteger('hash_version')->nullable();
            $table->unique(['event_hash'], 'audit_events_event_hash_unique');
            $table->foreign(['actor_id'], 'audit_events_actor_id_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('set null')
                ->onUpdate('no action');
            $table->foreign(['county_id'], 'audit_events_county_id_foreign')
                ->references(['id'])
                ->on('counties')
                ->onDelete('set null')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX audit_events_action_index ON public.audit_events USING btree (action);
CREATE INDEX audit_events_hash_version_index ON public.audit_events USING btree (hash_version);
CREATE INDEX audit_events_occurred_at_index ON public.audit_events USING btree (occurred_at);
CREATE INDEX audit_events_previous_hash_index ON public.audit_events USING btree (previous_hash);
CREATE INDEX audit_events_subject_type_subject_id_index ON public.audit_events USING btree (subject_type, subject_id);
CREATE TRIGGER audit_events_append_only BEFORE DELETE OR UPDATE ON audit_events FOR EACH ROW EXECUTE FUNCTION prevent_audit_event_mutation();
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
    }
};
