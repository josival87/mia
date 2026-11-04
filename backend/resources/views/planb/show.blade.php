@extends('layouts.app')
@section('title', $business->name.' — Plano B')
@section('content')
@php($brl = fn ($v) => 'R$ '.number_format((float) $v, 2, ',', '.'))
<div class="page-heading"><div><span class="page-kicker">PLANO B · {{ mb_strtoupper(\App\Models\Business::TYPES[$business->type] ?? '') }}</span><h1>{{ $business->name }}</h1><p>Resultado de {{ now()->translatedFormat('F') }}, separado da sua vida pessoal.</p></div><a class="btn" href="{{ route('planb.index') }}">Voltar</a></div>

@if(session('success'))<div class="alert success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert error"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

<section class="metric-grid">
    <article class="metric-card dark-card"><div class="metric-head"><span>Lucro líquido do mês</span><i>R$</i></div><strong>{{ $brl($summary['profit']) }}</strong>
        <p>Mês anterior: {{ $brl($previous['profit']) }}@if($summary['goal_progress'] !== null) · {{ number_format($summary['goal_progress'], 0) }}% da meta @endif</p></article>
    <article class="metric-card"><div class="metric-head"><span>Lucro por hora</span><i class="metric-icon income">⏱</i></div><strong>{{ $summary['profit_per_hour'] !== null ? $brl($summary['profit_per_hour']) : '—' }}</strong><p>{{ number_format($summary['hours'], 1, ',', '.') }} h trabalhadas</p></article>
    @if($business->type === 'driver')
    <article class="metric-card"><div class="metric-head"><span>Custo real por km</span><i class="metric-icon expense">km</i></div><strong>{{ $costPerKm !== null ? $brl($costPerKm) : '—' }}</strong><p>{{ $costPerKm !== null ? 'Corridas abaixo disso dão prejuízo' : 'Registre 100 km em turnos para calcular' }}</p></article>
    @endif
    <article class="metric-card"><div class="metric-head"><span>Retirada segura</span><i class="metric-icon tasks">→</i></div><strong>{{ $brl($withdrawal['safe']) }}</strong><p>Caixa {{ $brl($withdrawal['cash']) }} − reservas {{ $brl($withdrawal['reserves']) }}</p></article>
</section>

<section class="dashboard-columns">
    <article class="panel"><div class="panel-heading"><div><h2>Como o lucro foi calculado</h2><p>Receitas menos todos os custos do mês</p></div></div>
        <table class="data-table"><tbody>
            <tr><td>Receitas</td><td>{{ $brl($summary['income']) }}</td></tr>
            <tr><td>Despesas lançadas</td><td>− {{ $brl($summary['expenses']) }}</td></tr>
            <tr><td>Custos fixos (aluguel, parcela, seguro, IPVA)</td><td>− {{ $brl($summary['fixed']) }}</td></tr>
            <tr><td>Depreciação</td><td>− {{ $brl($summary['depreciation']) }}</td></tr>
            <tr><td>Manutenção provisionada</td><td>− {{ $brl($summary['maintenance_provision']) }}</td></tr>
            <tr><td>Imposto estimado</td><td>− {{ $brl($summary['tax_estimate']) }}</td></tr>
            <tr><td><strong>Lucro líquido</strong></td><td><strong>{{ $brl($summary['profit']) }}</strong></td></tr>
        </tbody></table>
        <p class="muted">Estimativa para organização pessoal; não substitui um contador.</p>
    </article>

    <article class="panel"><div class="panel-heading"><div><h2>Passar dinheiro para o pessoal</h2><p>A retirada sai daqui e entra como receita no seu financeiro, na categoria Plano B</p></div></div>
        <form method="POST" action="{{ route('planb.withdraw', $business) }}" class="stack-form">@csrf
            <label>Valor (R$)<input name="amount" type="number" step="0.01" min="0.01" value="{{ $withdrawal['safe'] > 0 ? $withdrawal['safe'] : '' }}" required></label>
            <button class="btn btn-primary" type="submit">Registrar retirada</button>
        </form>
    </article>
</section>

@if($business->type === 'driver')
<section class="dashboard-columns">
    <article class="panel"><div class="panel-heading"><div><h2>Registrar turno</h2><p>Pelo Telegram: <code>/turno 320 180km 6h uber</code></p></div></div>
        <form method="POST" action="{{ route('planb.shifts.store', $business) }}" class="stack-form">@csrf
            <div class="form-grid"><label>Ganho bruto (R$)<input name="gross" type="number" step="0.01" min="0.01" required></label><label>Km rodados<input name="km" type="number" step="0.1" min="0" required></label></div>
            <div class="form-grid"><label>Horas online<input name="hours" type="number" step="0.1" min="0.1" max="24" required></label><label>Plataforma<input name="platform" maxlength="40" placeholder="Uber, 99, iFood"></label></div>
            <label>Data<input name="worked_on" type="date" value="{{ today()->toDateString() }}"></label>
            <button class="btn btn-primary" type="submit">Salvar turno</button>
        </form>
    </article>
    <article class="panel"><div class="panel-heading"><div><h2>Vale a pena essa corrida?</h2><p>Pelo Telegram: <code>/corrida 18 12km 25min</code></p></div></div>
        <form method="POST" action="{{ route('planb.ride', $business) }}" class="stack-form">@csrf
            <div class="form-grid"><label>Valor (R$)<input name="value" type="number" step="0.01" value="{{ old('value') }}" required></label><label>Km<input name="km" type="number" step="0.1" value="{{ old('km') }}" required></label></div>
            <label>Minutos (opcional)<input name="minutes" type="number" value="{{ old('minutes') }}"></label>
            <button class="btn btn-primary" type="submit">Avaliar</button>
        </form>
        @if(session('ride_result') === 'sem_historico')
            <div class="alert error">Ainda não sei seu custo por km: registre turnos que somem pelo menos 100 km nos últimos 30 dias.</div>
        @elseif(is_array(session('ride_result')))
            @php($r = session('ride_result'))
            <div class="alert {{ $r['worth'] ? 'success' : 'error' }}"><strong>{{ $r['worth'] ? 'Vale a pena.' : 'Não compensa.' }}</strong> Lucro {{ $brl($r['profit']) }} ({{ $brl($r['profit_per_km']) }} por km{{ $r['profit_per_hour'] !== null ? ', '.$brl($r['profit_per_hour']).' por hora' : '' }}). Seu custo: {{ $brl($r['cost_per_km']) }} por km.</div>
        @endif
    </article>
</section>
@endif

@if($business->type === 'freelancer')
<section class="dashboard-columns">
    <article class="panel"><div class="panel-heading"><div><h2>Projetos</h2><p>Pelo Telegram: <code>/horas 3 nome do projeto</code></p></div></div>
        @forelse($projects as $project)
            @php($hours = round(($project->time_entries_sum_minutes ?? 0) / 60, 1))
            <div class="record-row"><div><strong>{{ $project->name }}</strong><span>{{ $project->client_name ?: 'Sem cliente' }} · {{ \App\Models\Project::STATUSES[$project->status] ?? $project->status }} · {{ number_format($hours, 1, ',', '.') }} h
                @if($hours > 0) · {{ $brl($project->contract_value / $hours) }} por hora efetivos @endif
                @if($project->estimated_hours) · orçado {{ $brl($project->contract_value / max(0.1, $project->estimated_hours)) }} por hora @endif</span></div>
                <form method="POST" action="{{ route('planb.projects.time', [$business, $project]) }}" class="inline-form">@csrf<input name="hours" type="number" step="0.1" min="0.1" max="24" placeholder="h" style="width:70px" required><button class="text-button" type="submit">+ horas</button></form></div>
        @empty
            <p class="muted">Nenhum projeto ainda.</p>
        @endforelse
    </article>
    <article class="panel"><div class="panel-heading"><div><h2>Novo projeto</h2><p>Gera as parcelas de sinal e entrega a receber</p></div></div>
        <form method="POST" action="{{ route('planb.projects.store', $business) }}" class="stack-form">@csrf
            <div class="form-grid"><label>Projeto<input name="name" maxlength="120" required></label><label>Cliente<input name="client_name" maxlength="120"></label></div>
            <div class="form-grid"><label>Valor fechado (R$)<input name="contract_value" type="number" step="0.01" min="0" required></label><label>Sinal (%)<input name="deposit_percent" type="number" min="0" max="100" value="50"></label></div>
            <div class="form-grid"><label>Horas estimadas<input name="estimated_hours" type="number" step="0.5" min="0"></label><label>Entrega<input name="due_on" type="date"></label></div>
            <button class="btn btn-primary" type="submit">Criar projeto</button>
        </form>
    </article>
</section>
@endif

<section class="dashboard-columns">
    @if($receivables->isNotEmpty() || $business->type === 'freelancer')
    <article class="panel"><div class="panel-heading"><div><h2>A receber</h2><p>A Mia avisa no Telegram no dia do vencimento</p></div></div>
        @forelse($receivables as $receivable)
            <div class="record-row"><div><strong>{{ $receivable->description }}</strong><span>Vence {{ $receivable->due_on->format('d/m/Y') }}@if($receivable->due_on->isPast() && ! $receivable->due_on->isToday()) · atrasado @endif</span></div><b>{{ $brl($receivable->amount) }}</b>
                <form method="POST" action="{{ route('planb.receivables.pay', [$business, $receivable]) }}">@csrf<button class="text-button" type="submit">Recebi</button></form></div>
        @empty
            <p class="muted">Nada em aberto.</p>
        @endforelse
    </article>
    @endif
    <article class="panel"><div class="panel-heading"><div><h2>Lançar receita ou custo do negócio</h2><p>Combustível, manutenção, ferramentas, recebimentos avulsos</p></div></div>
        <form method="POST" action="{{ route('planb.entries.store', $business) }}" class="stack-form">@csrf
            <div class="form-grid"><label>Tipo<select name="type"><option value="expense">Custo</option><option value="income">Receita</option></select></label><label>Valor (R$)<input name="amount" type="number" step="0.01" min="0.01" required></label></div>
            <div class="form-grid"><label>Descrição<input name="description" maxlength="180" placeholder="Ex.: Combustível" required></label><label>Data<input name="occurred_on" type="date" value="{{ today()->toDateString() }}"></label></div>
            <button class="btn btn-primary" type="submit">Salvar</button>
        </form>
    </article>
</section>

<article class="panel"><div class="panel-heading"><div><h2>Últimos lançamentos do negócio</h2></div></div>
    <div class="record-list">
    @forelse($records as $record)
        <div class="record-row"><span class="record-icon {{ $record->type }}">{{ $record->type === 'income' ? '↓' : '↑' }}</span><div><strong>{{ $record->description }}</strong><span>{{ $record->occurred_on->format('d/m') }}@if($record->entry_kind === 'withdrawal') · retirada para o pessoal @endif</span></div><b class="{{ $record->type }}">{{ $record->type === 'income' ? '+' : '−' }} {{ $brl($record->amount) }}</b>
            <form method="POST" action="{{ route('planb.entries.destroy', [$business, $record]) }}" data-confirm="Remover este lançamento?">@csrf @method('DELETE')<button class="text-button" type="submit">Remover</button></form></div>
    @empty
        <p class="muted">Nenhum lançamento ainda.</p>
    @endforelse
    </div>
</article>

<form method="POST" action="{{ route('planb.archive', $business) }}" data-confirm="Arquivar este negócio? O histórico continua salvo." style="margin-top:24px">@csrf<button class="text-button" type="submit">Arquivar negócio</button></form>
@endsection
