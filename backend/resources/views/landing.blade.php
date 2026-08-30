@extends('layouts.public')
@section('title', 'Mia Assistente — Finanças e atividades em um só lugar')
@section('styles')
    <link rel="stylesheet" href="{{ asset('css/landing.css') }}">
@endsection
@section('content')
<header class="landing-header">
    <a class="brand" href="{{ route('landing') }}"><span class="brand-mark">M</span><span>Mia<small>Assistente</small></span></a>
    <nav><a href="#recursos">Recursos</a><a href="#como-funciona">Como funciona</a></nav>
    <div class="header-actions"><a class="btn btn-ghost" href="{{ route('login') }}">Entrar</a><a class="btn btn-primary" href="{{ route('register') }}">Criar conta</a></div>
</header>
<main>
    <section class="hero">
        <div class="hero-copy">
            <span class="eyebrow"><i></i> Sua rotina mais leve</span>
            <h1>Cuide do seu dinheiro.<br><em>Organize o seu dia.</em></h1>
            <p>A Mia reúne finanças e atividades em uma experiência simples — e entende seus lançamentos por texto ou áudio no Telegram.</p>
            <div class="hero-actions"><a class="btn btn-primary btn-lg" href="{{ route('register') }}">Começar gratuitamente <span>→</span></a><a class="btn btn-text" href="#como-funciona">Ver como funciona</a></div>
            <div class="trust-row"><span>✓ Sem planilhas complicadas</span><span>✓ Feito para o celular</span></div>
        </div>
        <div class="hero-visual">
            <div class="glow"></div>
            <div class="phone-card">
                <div class="phone-top"><div><span>Boa tarde,</span><strong>Marina 👋</strong></div><span class="mini-avatar">M</span></div>
                <div class="balance-card"><span>Saldo do mês</span><strong>R$ 4.280,50</strong><small><b>↗ 12%</b> comparado a julho</small><div class="bars"><i style="height:30%"></i><i style="height:45%"></i><i style="height:38%"></i><i style="height:72%"></i><i style="height:55%"></i><i style="height:88%"></i><i class="current" style="height:67%"></i></div></div>
                <div class="quick-metrics"><div><span class="income-dot">↙</span><p>Entradas<small>R$ 7.850,00</small></p></div><div><span class="expense-dot">↗</span><p>Saídas<small>R$ 3.569,50</small></p></div></div>
                <div class="phone-section-title"><strong>Prioridades de hoje</strong><span>Ver todas</span></div>
                <div class="mini-task"><i></i><div><strong>Enviar proposta comercial</strong><span>Trabalho • Alta prioridade</span></div><b>›</b></div>
                <div class="mini-task done"><i>✓</i><div><strong>Revisar despesas</strong><span>Financeiro • Concluída</span></div><b>›</b></div>
            </div>
            <div class="telegram-bubble"><span>✦</span><div><small>Mia • agora</small><strong>“Almoço de 48 reais”</strong><p>✓ Saída registrada em Alimentação</p></div></div>
        </div>
    </section>
    <section class="feature-strip" id="recursos">
        <article><span>01</span><h3>Finanças sem esforço</h3><p>Entradas, saídas e categorias com visão clara de cada mês.</p></article>
        <article><span>02</span><h3>Atividades que acompanham você</h3><p>Kanban mensal com prioridades e tarefas que seguem até a conclusão.</p></article>
        <article><span>03</span><h3>Converse, a Mia organiza</h3><p>Mande um texto ou áudio no Telegram e transforme fala em registro.</p></article>
    </section>
    <section class="how-section" id="como-funciona"><span class="eyebrow"><i></i> Feito para acontecer</span><h2>Menos tempo organizando.<br>Mais tempo realizando.</h2><a class="btn btn-primary btn-lg" href="{{ route('register') }}">Criar minha conta</a></section>
</main>
<footer class="landing-footer"><a class="brand brand-light" href="#"><span class="brand-mark">M</span><span>Mia<small>Assistente</small></span></a><p>© {{ date('Y') }} Mia Assistente. Controle com leveza.</p></footer>
@endsection
