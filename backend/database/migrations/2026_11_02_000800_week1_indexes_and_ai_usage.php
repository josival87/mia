<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_records', function (Blueprint $table) {
            $table->index(['user_id', 'type', 'occurred_on'], 'finance_user_type_date_idx');
            $table->index(['user_id', 'category_id', 'occurred_on'], 'finance_user_category_date_idx');
        });
        Schema::table('tasks', fn (Blueprint $table) => $table->index(['user_id', 'completed_at'], 'tasks_user_completed_idx'));
        Schema::table('pending_telegram_records', fn (Blueprint $table) => $table->index(['user_id', 'status', 'expires_at'], 'pending_tg_user_status_exp_idx'));
        Schema::table('categories', fn (Blueprint $table) => $table->index(['user_id', 'kind', 'active'], 'categories_user_kind_active_idx'));
        Schema::table('processed_telegram_updates', fn (Blueprint $table) => $table->index('created_at'));

        Schema::create('ai_usage_daily', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('day');
            $table->string('route', 20)->default('record');
            $table->string('provider', 40);
            $table->string('model', 80)->default('');
            $table->unsignedInteger('requests')->default(0);
            $table->unsignedBigInteger('input_tokens')->default(0);
            $table->unsignedBigInteger('output_tokens')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'day', 'route', 'provider', 'model'], 'ai_usage_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_daily');
        Schema::table('processed_telegram_updates', fn (Blueprint $table) => $table->dropIndex(['created_at']));
        Schema::table('categories', fn (Blueprint $table) => $table->dropIndex('categories_user_kind_active_idx'));
        Schema::table('pending_telegram_records', fn (Blueprint $table) => $table->dropIndex('pending_tg_user_status_exp_idx'));
        Schema::table('tasks', fn (Blueprint $table) => $table->dropIndex('tasks_user_completed_idx'));
        Schema::table('finance_records', function (Blueprint $table) {
            $table->dropIndex('finance_user_type_date_idx');
            $table->dropIndex('finance_user_category_date_idx');
        });
    }
};
