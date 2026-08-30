<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pending_registrations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('email', 180)->index();
            $table->string('cpf', 14)->index();
            $table->string('verification_code', 6)->index();
            $table->timestamp('verification_code_expires_at');
            $table->timestamp('verification_code_used_at')->nullable();
            $table->unsignedInteger('verification_attempts')->default(0);
            $table->string('telegram')->nullable();
            $table->string('telegram_chat_id')->nullable();
            $table->string('telegram_user_id')->nullable()->index();
            $table->timestamp('telegram_verified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_registrations');
    }
};
