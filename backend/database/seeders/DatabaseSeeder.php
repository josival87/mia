<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Company;
use App\Models\FinanceRecord;
use App\Models\Menu;
use App\Models\SystemSetting;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        Company::firstOrCreate(['name' => 'Mia Assistente'], [
            'email' => 'contato@mia.local',
            'phone' => '(11) 4000-2026',
        ]);

        $admin = User::firstOrCreate(['email' => env('MIA_ADMIN_EMAIL', 'admin@mia.local')], [
            'name' => 'Administrador Mia',
            'cpf' => '00000000000',
            'password' => env('MIA_ADMIN_PASSWORD', 'Mia@12345'),
            'role' => 'admin',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $client = User::firstOrCreate(['email' => env('MIA_DEMO_EMAIL', 'cliente@mia.local')], [
            'name' => 'Marina Souza',
            'cpf' => '11111111111',
            'telegram' => 'marina_demo',
            'password' => env('MIA_DEMO_PASSWORD', 'Mia@12345'),
            'role' => 'client',
            'status' => 'active',
            'email_verified_at' => now(),
            'telegram_verified_at' => now(),
        ]);

        $menus = [
            ['audience' => 'client', 'label' => 'Dashboard', 'route' => 'dashboard', 'icon' => 'grid', 'position' => 1],
            ['audience' => 'client', 'label' => 'Financeiro', 'route' => 'finance.index', 'icon' => 'wallet', 'position' => 2],
            ['audience' => 'client', 'label' => 'Atividades', 'route' => 'tasks.index', 'icon' => 'tasks', 'position' => 3],
            ['audience' => 'client', 'label' => 'Categorias', 'route' => 'categories.index', 'icon' => 'tag', 'position' => 4],
            ['audience' => 'admin', 'label' => 'Dashboard', 'route' => 'admin.dashboard', 'icon' => 'grid', 'position' => 1],
            ['audience' => 'admin', 'label' => 'Usuários', 'route' => 'admin.users', 'icon' => 'shield', 'position' => 2],
            ['audience' => 'admin', 'label' => 'Clientes', 'route' => 'admin.clients', 'icon' => 'users', 'position' => 3],
            ['audience' => 'admin', 'label' => 'Categorias', 'route' => 'admin.categories', 'icon' => 'tag', 'position' => 4],
            ['audience' => 'admin', 'label' => 'Configurações', 'route' => 'admin.settings', 'icon' => 'settings', 'position' => 5],
        ];
        foreach ($menus as $menu) {
            Menu::updateOrCreate(['audience' => $menu['audience'], 'route' => $menu['route']], $menu);
        }

        $categorySets = [
            'income' => [['Salário', '#16a34a'], ['Freelance', '#22c55e'], ['Investimentos', '#14b8a6'], ['Vendas', '#84cc16'], ['Aluguel', '#0ea5e9'], ['credpix', '#8b5cf6'], ['Outras entradas', '#65a30d']],
            'expense' => [['Alimentação', '#f97316'], ['Moradia', '#ef4444'], ['Transporte', '#3b82f6'], ['Saúde', '#ec4899'], ['Educação', '#8b5cf6'], ['Lazer', '#eab308'], ['Assinaturas', '#6366f1'], ['Outras saídas', '#64748b']],
            'task' => [['Trabalho', '#2563eb'], ['Pessoal', '#16a34a'], ['Casa', '#f97316'], ['Saúde', '#ec4899'], ['Estudos', '#8b5cf6']],
        ];
        $categoryIds = [];
        foreach ($categorySets as $kind => $items) {
            foreach ($items as [$name, $color]) {
                $category = Category::firstOrCreate(['user_id' => null, 'name' => $name, 'kind' => $kind], ['color' => $color, 'active' => true]);
                $categoryIds[$kind][$name] = $category->id;
            }
        }

        $financeSeeds = [
            ['type' => 'income', 'category_id' => $categoryIds['income']['Salário'], 'description' => 'Salário mensal', 'amount' => 7850, 'occurred_on' => now()->startOfMonth()->addDays(4)],
            ['type' => 'expense', 'category_id' => $categoryIds['expense']['Moradia'], 'description' => 'Aluguel', 'amount' => 1800, 'occurred_on' => now()->startOfMonth()->addDays(5)],
            ['type' => 'expense', 'category_id' => $categoryIds['expense']['Alimentação'], 'description' => 'Supermercado', 'amount' => 486.70, 'occurred_on' => now()->startOfMonth()->addDays(9)],
            ['type' => 'expense', 'category_id' => $categoryIds['expense']['Transporte'], 'description' => 'Combustível', 'amount' => 240, 'occurred_on' => now()->startOfMonth()->addDays(12)],
            ['type' => 'expense', 'category_id' => $categoryIds['expense']['Assinaturas'], 'description' => 'Serviços digitais', 'amount' => 89.90, 'occurred_on' => now()->startOfMonth()->addDays(14)],
        ];
        foreach ($financeSeeds as $seed) {
            FinanceRecord::firstOrCreate(['user_id' => $client->id, 'description' => $seed['description'], 'occurred_on' => $seed['occurred_on']->toDateString()], $seed + ['source' => 'seed']);
        }

        $taskSeeds = [
            ['name' => 'Enviar proposta comercial', 'description' => 'Revisar valores e enviar para o cliente.', 'priority' => 'high', 'status' => 'doing', 'category_id' => $categoryIds['task']['Trabalho'], 'due_on' => now()->addDays(2)],
            ['name' => 'Organizar documentos', 'description' => 'Separar documentos pessoais do mês.', 'priority' => 'medium', 'status' => 'todo', 'category_id' => $categoryIds['task']['Pessoal'], 'due_on' => now()->addDays(5)],
            ['name' => 'Revisar despesas', 'description' => 'Conferir os lançamentos da semana.', 'priority' => 'low', 'status' => 'done', 'category_id' => $categoryIds['task']['Pessoal'], 'due_on' => now(), 'completed_at' => now()->subDay()],
        ];
        foreach ($taskSeeds as $seed) {
            Task::firstOrCreate(['user_id' => $client->id, 'name' => $seed['name']], $seed + ['source' => 'seed']);
        }

        foreach ([
            'ai_primary_provider' => 'gemini',
            'openai_model' => 'gpt-5-mini',
            'gemini_model' => 'gemini-3.6-flash',
            'telegram_bot_username' => 'bot_Mia_Assistente',
            'telegram_confirmation_amount' => '100',
            'telegram_min_confidence' => '0.70',
            'telegram_direct_confidence' => '0.90',
            'telegram_update_mode' => env('TELEGRAM_UPDATE_MODE', app()->environment('production') ? 'webhook' : 'polling'),
            'telegram_webhook_url' => env('TELEGRAM_WEBHOOK_URL'),
            'cognition_url' => 'http://cognition:8000',
        ] as $key => $value) {
            SystemSetting::firstOrCreate(['key' => $key], ['value' => $value, 'encrypted' => false]);
        }

        $this->command?->info('Dados iniciais da Mia conferidos.');
        unset($admin);
    }
}
