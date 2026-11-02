<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/** Apaga o cliente de demonstração (e seus dados) de um ambiente já populado. */
class RemoveDemoUser extends Command
{
    protected $signature = 'mia:remove-demo-user {--email=cliente@mia.local}';

    protected $description = 'Remove o cliente de demonstração e todos os dados dele';

    public function handle(): int
    {
        $user = User::where('email', $this->option('email'))->where('role', 'client')->first();
        if (! $user) {
            $this->info('Nenhum cliente de demonstração encontrado.');

            return self::SUCCESS;
        }
        $user->delete();
        $this->info('Cliente de demonstração removido.');

        return self::SUCCESS;
    }
}
