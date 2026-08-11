<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('travel_itineraries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('travel_request_id');
            $table->smallInteger('sequence');
            $table->string('origin', 255);
            $table->string('destination', 255);
            $table->timestamp('departs_at', 0);
            $table->timestamp('arrives_at', 0);
            $table->string('transport_mode', 255);
            $table->string('carrier', 255)->nullable();
            $table->string('booking_reference', 255)->nullable();
            $table->decimal('estimated_cost', 18, 2)->default(DB::raw('\'0\'::numeric'));
            $table->jsonb('metadata')->nullable();
            $table->timestamp('created_at', 0)->nullable();
            $table->timestamp('updated_at', 0)->nullable();
            $table->softDeletes('deleted_at', 0);
            $table->unique(['travel_request_id', 'sequence'], 'travel_itineraries_travel_request_id_sequence_unique');
            $table->foreign(['travel_request_id'], 'travel_itineraries_travel_request_id_foreign')
                ->references(['id'])
                ->on('travel_requests')
                ->onDelete('cascade')
                ->onUpdate('no action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_itineraries');
    }
};
