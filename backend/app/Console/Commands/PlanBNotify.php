<?php

namespace App\Console\Commands;

use App\Http\Controllers\TelegramController;
use App\Models\Business;
use App\Models\Receivable;
use App\Models\User;
use App\Services\PlanB\PlanBMetrics;
use Illuminate\Console\Command;
use Throwable;

/**
 * Mensagens proativas do Plano B pelo Telegram (agendadas em routes/console.php):
 *   --weekly     resumo semanal com retirada segura (domingo à noite)
 *   --reminders  parcelas a receber que vencem hoje ou estão atrasadas (todo dia de manhã)
 */
class PlanBNotify extends Command
{
    protected $signature = 'mia:planb-notify {--weekly} {--reminders}';

    protected $description = 'Envia resumos semanais e lembretes de recebíveis do Plano B pelo Telegram';

    public function handle(PlanBMetrics $metrics, TelegramController $telegram): int
    {
        $sent = 0;
        if ($this->option('weekly')) {
            $sent += $this->weekly($metrics, $telegram);
        }
        if ($this->option('reminders')) {
            $sent += $this->reminders($telegram);
        }
        $this->info("Mensagens enviadas: {$sent}");

        return self::SUCCESS;
    }

    private function weekly(PlanBMetrics $metrics, TelegramController $telegram): int
    {
        $sent = 0;
        $users = User::whereNotNull('telegram_chat_id')->where('status', 'active')
            ->whereIn('id', Business::withoutGlobalScopes()->where('status', 'active')->select('user_id'))->get();
        foreach ($users as $user) {
            $lines = [];
            foreach (Business::withoutGlobalScopes()->where('user_id', $user->id)->where('status', 'active')->get() as $business) {
                $s = $metrics->monthSummary($business, now());
                $w = $metrics->safeWithdrawal($business);
                $line = sprintf('%s: lucro no mês %s%s. Retirada segura: %s.', $business->name, $this->brl($s['profit']),
                    $s['profit_per_hour'] !== null ? ' ('.$this->brl($s['profit_per_hour']).' por hora)' : '', $this->brl($w['safe']));

                $alert = $metrics->hourlyRateAlert($business, $user);
                if ($alert && $alert['is_underpaid']) {
                    $line .= sprintf("\n⚠️ Alerta de hora mal paga: %s/h vs sua hora principal de %s/h.",
                        $this->brl($alert['plan_b_rate']), $this->brl($alert['primary_rate']));
                }

                $lines[] = $line;
            }
            if ($lines && $this->send($telegram, $user, "Resumo semanal do seu Plano B\n".implode("\n", $lines)."\nPara retirar: /retirada VALOR")) {
                $sent++;
            }
        }

        return $sent;
    }

    private function reminders(TelegramController $telegram): int
    {
        $sent = 0;
        $due = Receivable::withoutGlobalScopes()->with('user')->where('status', 'open')
            ->whereDate('due_on', '<=', today())
            ->where(fn ($q) => $q->whereNull('last_reminder_at')->orWhere('last_reminder_at', '<', today()->subDays(3)))
            ->get();
        foreach ($due as $receivable) {
            $user = $receivable->user;
            if (! $user?->telegram_chat_id) {
                continue;
            }
            $late = $receivable->due_on->isBefore(today());
            $text = sprintf("%s: %s (%s).\nJá recebeu? Marque como recebido no site, em Plano B.",
                $late ? 'Recebimento atrasado' : 'Vence hoje', $receivable->description, $this->brl($receivable->amount));
            if ($this->send($telegram, $user, $text)) {
                $receivable->update(['last_reminder_at' => now()]);
                $sent++;
            }
        }

        return $sent;
    }

    private function send(TelegramController $telegram, User $user, string $text): bool
    {
        try {
            $telegram->notifyUser($user, $text);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function brl(float $v): string
    {
        return 'R$ '.number_format($v, 2, ',', '.');
    }
}
