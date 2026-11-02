<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('businesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20); // driver, freelancer, rental, generic
            $table->string('name', 80);
            $table->string('status', 20)->default('active');
            $table->string('tax_regime', 20)->default('none'); // none, mei, carne_leao
            $table->decimal('reserve_tax_percent', 5, 2)->default(0);
            $table->decimal('reserve_lean_percent', 5, 2)->default(0);
            $table->decimal('goal_monthly_profit', 14, 2)->nullable();
            $table->decimal('goal_weekly_hours', 6, 2)->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'name']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('business_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20)->default('vehicle');
            $table->string('name', 80);
            $table->string('ownership', 20)->default('owned'); // owned, financed, rented
            $table->decimal('current_value', 14, 2)->default(0);
            $table->decimal('residual_value', 14, 2)->default(0);
            $table->unsignedSmallInteger('useful_life_months')->default(60);
            $table->decimal('fixed_monthly_cost', 14, 2)->default(0); // aluguel ou parcela + seguro + IPVA/12
            $table->decimal('maintenance_per_km', 8, 4)->default(0); // óleo, pneus, freios provisionados
            $table->decimal('fuel_km_per_l', 6, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('driver_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('worked_on');
            $table->decimal('hours', 5, 2);
            $table->decimal('km', 8, 1);
            $table->decimal('gross', 14, 2);
            $table->string('platform', 40)->nullable();
            $table->foreignId('finance_record_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['business_id', 'worked_on']);
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('client_name', 120)->nullable();
            $table->string('status', 20)->default('active'); // proposal, active, delivered, paid
            $table->decimal('contract_value', 14, 2)->default(0);
            $table->decimal('estimated_hours', 8, 2)->nullable();
            $table->date('due_on')->nullable();
            $table->timestamps();
            $table->index(['business_id', 'status']);
        });

        Schema::create('time_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('worked_on');
            $table->unsignedInteger('minutes');
            $table->string('source', 20)->default('manual');
            $table->timestamps();
            $table->index(['project_id', 'worked_on']);
        });

        Schema::create('receivables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description', 160);
            $table->decimal('amount', 14, 2);
            $table->date('due_on');
            $table->string('status', 20)->default('open'); // open, paid, canceled
            $table->foreignId('paid_record_id')->nullable()->constrained('finance_records')->nullOnDelete();
            $table->timestamp('last_reminder_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status', 'due_on']);
        });

        Schema::table('finance_records', function (Blueprint $table) {
            // Nulo = vida pessoal. Preenchido = lançamento de um negócio do Plano B.
            $table->foreignId('business_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('entry_kind', 20)->default('operational'); // operational, withdrawal, contribution
            $table->uuid('transfer_group_id')->nullable()->index();
            $table->index(['user_id', 'business_id', 'occurred_on'], 'finance_user_business_date_idx');
        });
    }

    public function down(): void
    {
        Schema::table('finance_records', function (Blueprint $table) {
            $table->dropIndex('finance_user_business_date_idx');
            $table->dropConstrainedForeignId('business_id');
            $table->dropColumn(['entry_kind', 'transfer_group_id']);
        });
        Schema::dropIfExists('receivables');
        Schema::dropIfExists('time_entries');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('driver_shifts');
        Schema::dropIfExists('business_assets');
        Schema::dropIfExists('businesses');
    }
};
