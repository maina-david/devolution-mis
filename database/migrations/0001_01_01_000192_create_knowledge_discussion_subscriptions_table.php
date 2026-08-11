<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_discussion_subscriptions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('knowledge_discussion_id');
            $table->uuid('user_id');
            $table->string('delivery_frequency', 255)->default('instant');
            $table->timestamp('subscribed_at', 0);
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->unique(['knowledge_discussion_id', 'user_id'], 'knowledge_discussion_subscriber_unique');
            $table->foreign(['knowledge_discussion_id'], 'knowledge_discussion_subscriptions_knowledge_discussion_id_fore')
                ->references(['id'])
                ->on('knowledge_discussions')
                ->onDelete('cascade')
                ->onUpdate('no action');
            $table->foreign(['user_id'], 'knowledge_discussion_subscriptions_user_id_foreign')
                ->references(['id'])
                ->on('users')
                ->onDelete('restrict')
                ->onUpdate('no action');
        });

        DB::unprepared(<<<'SQL'
CREATE INDEX knowledge_discussion_subscriptions_user_id_delivery_frequency_i ON public.knowledge_discussion_subscriptions USING btree (user_id, delivery_frequency);
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_discussion_subscriptions');
    }
};
