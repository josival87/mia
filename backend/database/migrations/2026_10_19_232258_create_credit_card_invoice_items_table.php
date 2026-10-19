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
        Schema::create('credit_card_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_card_invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('possible_duplicate_finance_record_id')->nullable()->constrained('finance_records')->nullOnDelete();
            $table->string('description', 180);
            $table->decimal('amount', 14, 2);
            $table->date('purchase_date')->nullable();
            $table->unsignedSmallInteger('installment_current')->default(1);
            $table->unsignedSmallInteger('installment_total')->default(1);
            $table->decimal('ai_confidence', 5, 4)->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();
            $table->index('credit_card_invoice_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_card_invoice_items');
    }
};
