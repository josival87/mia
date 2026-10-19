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
        Schema::create('credit_card_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('credit_card_id')->constrained()->cascadeOnDelete();
            $table->string('original_filename');
            $table->char('file_hash', 64);
            $table->date('reference_month');
            $table->string('status', 20)->default('review');
            $table->string('provider', 20)->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();
            $table->unique(['credit_card_id', 'file_hash']);
            $table->index(['user_id', 'reference_month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_card_invoices');
    }
};
