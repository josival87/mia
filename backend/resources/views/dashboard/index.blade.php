@extends('layouts.app')
@section('title', 'Dashboard — Mia Assistente')
@section('content')
<div class="page-heading"><div><span class="page-kicker">VISÃO DO MÊS</span><h1>Olá, {{ explode(' ', auth()->user()->name)[0] }}! <span>👋</span></h1><p>Aqui está o resumo da sua vida financeira e das suas atividades.</p></div><div class="month-switcher"><a href="{{ route('dashboard', ['month' => $month->copy()->subMonth()->format('Y-m')]) }}">‹</a><strong>{{ ucfirst($month->translatedFormat('F Y')) }}</strong><a href="{{ route('dashboard', ['month' => $month->copy()->addMonth()->format('Y-m')]) }}">›</a></div></div>

<section class="metric-grid">
    <article class="metric-card dark-card"><div class="metric-head"><span>Saldo do mês</span><i>R$</i></div><strong>R$ {{ number_format($income - $expense, 2, ',', '.') }}</strong><p>{{ $income >= $expense ? 'Seu mês está no positivo' : 'Atenção às saídas deste mês' }}</p><div class="spark-bars">@foreach([42,58,47,66,54,78,64,83,71,90,76,86] as $bar)<i style="height:{{ $bar }}%"></i>@endforeach</div></article>
    <article class="metric-card"><div class="metric-head"><span>Entradas</span><i class="metric-icon income">↓</i></div><strong>R$ {{ number_format($income, 2, ',', '.') }}</strong><p class="positive">Valores recebidos no mês</p></article>
    <article class="metric-card"><div class="metric-head"><span>Saídas</span><i class="metric-icon expense">↑</i></div><strong>R$ {{ number_format($expense, 2, ',', '.') }}</strong><p class="muted">Despesas registradas</p></article>
    <article class="metric-card"><div class="metric-head"><span>Atividades</span><i class="metric-icon tasks">✓</i></div><strong>{{ $completed }} <small>concluídas</small></strong><p><b>{{ $open }}</b> ainda em andamento</p></article>
</section>

<section class="dashboard-columns">
    <article class="panel"><div class="panel-heading"><div><h2>Movimentações recentes</h2><p>Últimos registros do mês</p></div><a href="{{ route('finance.index') }}">Ver tudo →</a></div>
        <div class="record-list">
        @forelse($recentFinances as $record)
            <div class="record-row"><span class="record-icon {{ $record->type }}">{{ $record->type === 'income' ? '↓' : '↑' }}</span><div><strong>{{ $record->title ?: $record->description }}</strong><span>@if($record->title){{ $record->description }} • @endif{{ $record->category?->name ?? 'Sem categoria' }} • {{ $record->occurred_on->format('d/m') }}</span></div><b class="amount {{ $record->type }}">{{ $record->type === 'income' ? '+' : '-' }} R$ {{ number_format($record->amount, 2, ',', '.') }}</b></div>
        @empty<div class="empty-state compact"><strong>Nenhuma movimentação</strong><p>Seus lançamentos deste mês aparecerão aqui.</p><a href="{{ route('finance.index') }}">Adicionar lançamento</a></div>@endforelse
        </div>
    </article>
    <article class="panel"><div class="panel-heading"><div><h2>Próximas atividades</h2><p>O que merece sua atenção</p></div><a href="{{ route('tasks.index') }}">Ver quadro →</a></div>
        <div class="record-list">
        @forelse($priorityTasks as $task)
            <div class="record-row task-row"><span class="task-check"></span><div><strong>{{ $task->name }}</strong><span>{{ $task->due_on ? 'Até '.$task->due_on->format('d/m') : 'Sem prazo' }}</span></div><b class="priority {{ $task->priority }}">{{ ['low'=>'Baixa','medium'=>'Média','high'=>'Alta'][$task->priority] }}</b></div>
        @empty<div class="empty-state compact"><strong>Tudo em dia</strong><p>Você não possui atividades pendentes.</p><a href="{{ route('tasks.index') }}">Criar atividade</a></div>@endforelse
        </div>
    </article>
</section>
<section class="mia-callout"><div class="mia-orb">✦</div><div><span>ATALHO INTELIGENTE</span><h2>É mais fácil falando com a Mia.</h2><p>Envie “Paguei 85 reais de internet” ou um áudio pelo Telegram. A Mia interpreta e organiza para você.</p></div><a class="btn btn-light" href="https://t.me/{{ \App\Models\SystemSetting::read('telegram_bot_username', 'bot_Mia_Assistente') }}" target="_blank">Abrir Telegram ↗</a></section>
@endsection
