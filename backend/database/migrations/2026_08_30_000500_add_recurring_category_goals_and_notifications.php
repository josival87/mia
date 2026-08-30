<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->decimal('monthly_amount', 14, 2);
            $table->timestamps();

            $table->unique(['user_id', 'category_id']);
        });

        Schema::create('category_goal_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_goal_id')->constrained()->cascadeOnDelete();
            $table->date('period_month');
            $table->date('week_start');
            $table->unsignedSmallInteger('threshold');
            $table->decimal('spent_amount', 14, 2);
            $table->decimal('weekly_target', 14, 2);
            $table->foreignId('finance_record_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['category_goal_id', 'period_month', 'week_start', 'threshold'],
                'category_goal_milestone_unique'
            );
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('category_goal_milestones');
        Schema::dropIfExists('category_goals');
    }
};
