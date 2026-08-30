<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->char('iphone_ingest_token_hash', 64)->nullable()->unique();
            $table->timestamp('iphone_ingest_token_created_at')->nullable();
            $table->timestamp('iphone_ingest_last_used_at')->nullable();
        });

        Schema::create('bank_notification_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event_id', 120);
            $table->string('channel', 20);
            $table->string('sender', 120)->nullable();
            $table->char('content_hash', 64);
            $table->timestamp('received_at')->nullable();
            $table->string('status', 30)->default('processing');
            $table->timestamps();

            $table->unique(['user_id', 'event_id']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_notification_events');

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['iphone_ingest_token_hash']);
            $table->dropColumn([
                'iphone_ingest_token_hash',
                'iphone_ingest_token_created_at',
                'iphone_ingest_last_used_at',
            ]);
        });
    }
};
