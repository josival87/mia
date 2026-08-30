<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('telegram_user_id')->nullable()->unique();
        });

        Schema::table('verification_codes', function (Blueprint $table) {
            $table->string('purpose', 20)->default('registration');
        });

        Schema::table('finance_records', function (Blueprint $table) {
            $table->decimal('ai_confidence', 4, 3)->nullable();
            $table->string('source_reference')->nullable()->unique();
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->decimal('ai_confidence', 4, 3)->nullable();
            $table->string('source_reference')->nullable()->unique();
        });

        Schema::create('pending_telegram_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('token')->unique();
            $table->string('kind', 20);
            $table->json('payload');
            $table->decimal('confidence', 4, 3);
            $table->string('reason', 30);
            $table->string('status', 30)->default('pending');
            $table->string('telegram_chat_id');
            $table->string('origin_update_id')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        Schema::create('processed_telegram_updates', function (Blueprint $table) {
            $table->id();
            $table->string('update_id')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('processed_telegram_updates');
        Schema::dropIfExists('pending_telegram_records');

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropUnique(['source_reference']);
            $table->dropColumn(['ai_confidence', 'source_reference']);
        });
        Schema::table('finance_records', function (Blueprint $table) {
            $table->dropUnique(['source_reference']);
            $table->dropColumn(['ai_confidence', 'source_reference']);
        });
        Schema::table('verification_codes', fn (Blueprint $table) => $table->dropColumn('purpose'));
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['telegram_user_id']);
            $table->dropColumn('telegram_user_id');
        });
    }
};
