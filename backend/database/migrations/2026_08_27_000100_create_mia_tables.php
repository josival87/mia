<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('cnpj', 18)->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->longText('logo_base64')->nullable();
            $table->string('pix_key')->nullable();
            $table->timestamps();
        });

        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('menus')->nullOnDelete();
            $table->string('audience', 20);
            $table->string('label');
            $table->string('route');
            $table->string('icon')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('kind', 20);
            $table->string('color', 7)->default('#16a34a');
            $table->string('icon')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['user_id', 'name', 'kind']);
        });

        Schema::create('finance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 10);
            $table->string('description');
            $table->decimal('amount', 14, 2);
            $table->date('occurred_on');
            $table->string('source', 20)->default('manual');
            $table->timestamps();
            $table->index(['user_id', 'occurred_on']);
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('priority', 20)->default('medium');
            $table->string('status', 20)->default('todo');
            $table->date('due_on')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('source', 20)->default('manual');
            $table->timestamps();
            $table->index(['user_id', 'status', 'created_at']);
        });

        Schema::create('verification_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('code', 6);
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamps();
        });

        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->boolean('encrypted')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('verification_codes');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('finance_records');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('menus');
        Schema::dropIfExists('companies');
    }
};
