<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('document', 20);
            $table->string('version', 20);
            $table->timestamp('accepted_at');
            $table->char('ip_hash', 64)->nullable();
            $table->timestamps();
            $table->index(['user_id', 'document']);
        });

        // CPF deixa de ser coletado: torna a coluna opcional e apaga os valores já gravados.
        Schema::table('pending_registrations', function (Blueprint $table) {
            $table->string('cpf', 14)->nullable()->change();
        });
        DB::table('pending_registrations')->update(['cpf' => null]);
        DB::table('users')->update(['cpf' => null]);
    }

    public function down(): void
    {
        Schema::dropIfExists('consents');
        // Os CPFs apagados não são restaurados.
    }
};
