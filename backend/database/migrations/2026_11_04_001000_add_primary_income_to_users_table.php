<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('primary_salary', 14, 2)->nullable()->after('status');
            $table->decimal('primary_monthly_hours', 8, 2)->nullable()->after('primary_salary');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['primary_salary', 'primary_monthly_hours']);
        });
    }
};

