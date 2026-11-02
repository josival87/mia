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
        Schema::create('credit_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('brand', 40)->nullable();
            $table->char('last_four', 4);
            $table->unsignedTinyInteger('closing_day')->nullable();
            $table->unsignedTinyInteger('due_day')->nullable();
            $table->string('color', 7)->default('#16a34a');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['user_id', 'active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_cards');
    }
};
