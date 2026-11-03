@extends('layouts.app')
@section('title', 'Plano B — Mia Assistente')
@section('content')
@php($brl = fn ($v) => 'R$ '.number_format((float) $v, 2, ',', '.'))
<div class="page-heading"><div><span class="page-kicker">RENDA EXTRA</span><h1>Plano B</h1><p>Cada renda extra com caixa próprio: quanto ela realmente deixa, por hora, depois de custos e impostos.</p></div><a class="btn btn-primary" href="{{ route('planb.create') }}">+ Novo negócio</a></div>

@if($businesses->isEmpty())
    <article class="panel"><div class="panel-heading"><div><h2>Comece pelo seu primeiro negócio</h2><p>Motorista de app, freelancer, locação ou qualquer outra renda extra.</p></div></div>
        <p>Depois de criar, você registra tudo pelo site ou pelo Telegram, sem gastar a cota de IA:</p>
        <ul>
            <li><code>/turno 320 180km 6h uber</code> registra um turno.</li>
            <li><code>/corrida 18 12km 25min</code> diz se a corrida vale a pena.</li>
            <li><code>/horas 3 logo</code> registra horas num projeto.</li>
            <li><code>/retirada</code> mostra quanto você pode passar para o pessoal.</li>
        </ul>
    </article>
@else
    <section class="metric-grid">
    @foreach($businesses as $business)
        @php($s = $summaries[$business->id])
        <a class="metric-card" href="{{ route('planb.show', $business) }}" style="text-decoration:none;color:inherit">
            <div class="metric-head"><span>{{ $business->name }}</span><i>{{ \App\Models\Business::TYPES[$business->type] ?? '' }}</i></div>
            <strong>{{ $brl($s['profit']) }}</strong>
            <p class="{{ $s['profit'] >= 0 ? 'positive' : 'muted' }}">Lucro líquido no mês
                @if($s['profit_per_hour'] !== null) · {{ $brl($s['profit_per_hour']) }} por hora @endif
            </p>
        </a>
    @endforeach
    </section>
    @if($survival && $survival['monthly_personal_expenses'] > 0)
    <article class="panel"><div class="panel-heading"><div><h2>Índice de sobrevivência</h2><p>Se a renda principal parar, quanto tempo você se mantém? (média dos 3 últimos meses)</p></div></div>
        <p>O Plano B cobre <strong>{{ number_format($survival['coverage_percent'] ?? 0, 0, ',', '.') }}%</strong> dos seus gastos pessoais ({{ $brl($survival['monthly_plan_b_profit']) }} de {{ $brl($survival['monthly_personal_expenses']) }} por mês).</p>
        <p>@if($survival['months'] === null)<strong>Seu Plano B já paga todos os seus gastos.</strong>@else Com o caixa atual do Plano B ({{ $brl($survival['reserve']) }}), você se mantém por <strong>{{ number_format($survival['months'], 1, ',', '.') }} meses</strong>.@endif</p>
    </article>
    @endif
@endif
@endsection
