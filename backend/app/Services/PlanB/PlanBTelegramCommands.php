<?php

namespace App\Services\PlanB;

use App\Models\Business;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Comandos rápidos do Plano B no Telegram. Resolvidos por regex, sem IA e sem gastar cota:
 *   /turno 320 180km 6h uber   /corrida 18 12km 25min   /horas 3 logo   /retirada [valor]   /planob
 */
class PlanBTelegramCommands
{
    private const NUMBER = '(\d+(?:[.,]\d+)?)';

    public function __construct(
        private readonly PlanBMetrics $metrics,
        private readonly PlanBLedger $ledger,
    ) {}

    /** Devolve a resposta ao usuário, ou null se o texto não for um comando do Plano B. */
    public function handle(User $user, string $text): ?string
    {
        if (! preg_match('/^\/(turno|corrida|horas|retirada|planob)(?:@\w+)?\b\s*(.*)$/isu', trim($text), $m)) {
            return null;
        }
        $args = trim($m[2]);

        try {
            return match (mb_strtolower($m[1])) {
                'turno' => $this->shift($user, $args),
                'corrida' => $this->ride($user, $args),
                'horas' => $this->hours($user, $args),
                'retirada' => $this->withdrawal($user, $args),
                'planob' => $this->summary($user),
            };
        } catch (ValidationException $e) {
            return collect($e->errors())->flatten()->first() ?? 'Não consegui registrar. Confira os valores.';
        }
    }

    private function shift(User $user, string $args): string
    {
        $business = $this->business($user, 'driver');
        if (! $business) {
            return 'Cadastre primeiro um negócio de motorista em Plano B no site.';
        }
        $gross = $this->first('/^'.self::NUMBER.'/u', $args);
        $km = $this->first('/'.self::NUMBER.'\s*km/iu', $args);
        $hours = $this->first('/'.self::NUMBER.'\s*h(?:oras?)?\b/iu', $args);
        if ($gross === null || $km === null || $hours === null) {
            return "Use: /turno GANHO KMkm HORASh [plataforma]\nEx.: /turno 320 180km 6h uber";
        }
        $platform = trim((string) preg_replace('/^'.self::NUMBER.'|'.self::NUMBER.'\s*(km|h(oras?)?)\b/iu', '', $args)) ?: null;
        $this->ledger->recordShift($business, $gross, $km, $hours, $platform, 'telegram');
        $s = $this->metrics->monthSummary($business, now());

        return sprintf(
            "Turno salvo: %s em %s km e %sh.\nNo mês: lucro %s%s%s.",
            $this->brl($gross), $this->num($km), $this->num($hours), $this->brl($s['profit']),
            $s['profit_per_hour'] !== null ? ', '.$this->brl($s['profit_per_hour']).' por hora' : '',
            $s['cost_per_km'] !== null ? ', custo de '.$this->brl($s['cost_per_km']).' por km' : '',
        );
    }

    private function ride(User $user, string $args): string
    {
        $business = $this->business($user, 'driver');
        if (! $business) {
            return 'Cadastre primeiro um negócio de motorista em Plano B no site.';
        }
        $value = $this->first('/^'.self::NUMBER.'/u', $args);
        $km = $this->first('/'.self::NUMBER.'\s*km/iu', $args);
        $minutes = $this->first('/'.self::NUMBER.'\s*min/iu', $args);
        if ($value === null || $km === null) {
            return "Use: /corrida VALOR KMkm [MINUTOSmin]\nEx.: /corrida 18 12km 25min";
        }
        $r = $this->metrics->evaluateRide($business, $value, $km, $minutes);
        if (! $r) {
            return 'Ainda não sei seu custo por km. Registre turnos que somem pelo menos 100 km nos últimos 30 dias (/turno) e tente de novo.';
        }

        return sprintf(
            "%s\nLucro: %s (%s por km%s). Seu custo: %s por km.",
            $r['worth'] ? 'Vale a pena.' : 'Não compensa: dá prejuízo.',
            $this->brl($r['profit']), $this->brl($r['profit_per_km']),
            $r['profit_per_hour'] !== null ? ', '.$this->brl($r['profit_per_hour']).' por hora' : '',
            $this->brl($r['cost_per_km']),
        );
    }

    private function hours(User $user, string $args): string
    {
        $business = $this->business($user, 'freelancer');
        if (! $business) {
            return 'Cadastre primeiro um negócio de freelancer em Plano B no site.';
        }
        $hours = $this->first('/^'.self::NUMBER.'/u', $args);
        if ($hours === null || $hours <= 0 || $hours > 24) {
            return "Use: /horas HORAS [nome do projeto]\nEx.: /horas 3 logo padaria";
        }
        $name = trim((string) preg_replace('/^'.self::NUMBER.'\s*h?/u', '', $args));
        $projects = Project::withoutGlobalScopes()->where('user_id', $user->id)->where('business_id', $business->id)->whereIn('status', ['active', 'proposal']);
        $project = $name !== '' ? (clone $projects)->where('name', 'ilike', '%'.addcslashes($name, '%_\\').'%')->latest('updated_at')->first() : null;
        $project ??= $projects->latest('updated_at')->first();
        if (! $project) {
            return 'Nenhum projeto ativo. Crie um em Plano B no site.';
        }
        TimeEntry::create(['project_id' => $project->id, 'user_id' => $user->id, 'worked_on' => today(), 'minutes' => (int) round($hours * 60), 'source' => 'telegram']);
        $rate = $project->effectiveHourlyRate();

        return sprintf('%sh registradas em "%s" (total %sh).%s', $this->num($hours), $project->name, $this->num($project->hoursWorked()),
            $rate !== null ? ' Valor/hora efetivo: '.$this->brl($rate).'.' : '');
    }

    private function withdrawal(User $user, string $args): string
    {
        $business = $this->business($user);
        if (! $business) {
            return 'Você ainda não tem um negócio no Plano B.';
        }
        $amount = $this->first('/^'.self::NUMBER.'/u', $args);
        $safe = $this->metrics->safeWithdrawal($business);
        if ($amount === null) {
            return sprintf("%s: caixa %s, reservas %s.\nPode retirar %s com segurança. Para registrar: /retirada VALOR",
                $business->name, $this->brl($safe['cash']), $this->brl($safe['reserves']), $this->brl($safe['safe']));
        }
        $this->ledger->withdraw($business, $amount, 'telegram');

        return sprintf('Retirada de %s registrada: saiu de %s e entrou no seu pessoal.%s', $this->brl($amount), $business->name,
            $amount > $safe['safe'] ? ' Atenção: acima do valor seguro ('.$this->brl($safe['safe']).').' : '');
    }

    private function summary(User $user): string
    {
        $lines = [];
        foreach (Business::withoutGlobalScopes()->where('user_id', $user->id)->where('status', 'active')->get() as $business) {
            $s = $this->metrics->monthSummary($business, now());
            $lines[] = sprintf('%s: lucro %s%s', $business->name, $this->brl($s['profit']),
                $s['profit_per_hour'] !== null ? ' ('.$this->brl($s['profit_per_hour']).' por hora)' : '');
        }

        return $lines ? "Plano B no mês:\n".implode("\n", $lines) : 'Você ainda não tem um negócio no Plano B.';
    }

    private function business(User $user, ?string $type = null): ?Business
    {
        return Business::withoutGlobalScopes()->where('user_id', $user->id)->where('status', 'active')
            ->when($type, fn ($q) => $q->where('type', $type))->oldest('id')->first();
    }

    private function first(string $pattern, string $text): ?float
    {
        return preg_match($pattern, $text, $m) ? (float) str_replace(',', '.', $m[1]) : null;
    }

    private function brl(float $v): string
    {
        return 'R$ '.number_format($v, 2, ',', '.');
    }

    private function num(float $v): string
    {
        return rtrim(rtrim(number_format($v, 1, ',', '.'), '0'), ',');
    }
}
