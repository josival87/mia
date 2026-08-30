<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_records', function (Blueprint $table) {
            $table->string('title', 180)->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('finance_records', function (Blueprint $table) {
            $table->dropColumn('title');
        });
    }
};
