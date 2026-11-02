<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('finance_records', function (Blueprint $table) {
            $table->foreignId('credit_card_id')->nullable()->after('category_id')->constrained()->nullOnDelete();
            $table->uuid('installment_group')->nullable()->after('source_reference');
            $table->unsignedSmallInteger('installment_number')->nullable()->after('installment_group');
            $table->unsignedSmallInteger('installment_total')->nullable()->after('installment_number');
            $table->date('purchased_on')->nullable()->after('occurred_on');
            $table->index(['user_id', 'credit_card_id', 'occurred_on']);
            $table->index('installment_group');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('finance_records', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'credit_card_id', 'occurred_on']);
            $table->dropIndex(['installment_group']);
            $table->dropConstrainedForeignId('credit_card_id');
            $table->dropColumn(['installment_group', 'installment_number', 'installment_total', 'purchased_on']);
        });
    }
};
