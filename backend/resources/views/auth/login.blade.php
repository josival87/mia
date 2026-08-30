@extends('layouts.public')
@section('title', ($adminMode ? 'Admin — ' : '').'Entrar na Mia')
@section('content')
<main class="auth-shell">
    <section class="auth-aside"><a class="brand brand-light" href="{{ route('landing') }}"><span class="brand-mark">M</span><span>Mia<small>Assistente</small></span></a><div><span class="eyebrow light"><i></i> Organização inteligente</span><h1>{{ $adminMode ? 'Gestão simples, visão completa.' : 'Sua vida em ordem, do seu jeito.' }}</h1><p>{{ $adminMode ? 'Acompanhe clientes, integrações e a saúde de toda a plataforma.' : 'Acesse suas finanças e atividades em poucos segundos.' }}</p></div><small>Seguro • Responsivo • Inteligente</small></section>
    <section class="auth-panel">
        <div class="auth-card">
            <a class="mobile-auth-brand brand" href="{{ route('landing') }}"><span class="brand-mark">M</span><span>Mia</span></a>
            <span class="auth-kicker">{{ $adminMode ? 'ÁREA ADMINISTRATIVA' : 'BEM-VINDO DE VOLTA' }}</span><h2>Entre na sua conta</h2><p>Use seu e-mail e senha para continuar.</p>
            @if($errors->any())<div class="alert error">{{ $errors->first() }}</div>@endif
            <form method="POST" action="{{ route('login.store') }}" class="stack-form">@csrf
                <label>E-mail<input type="email" name="email" value="{{ old('email') }}" placeholder="voce@exemplo.com" required autofocus></label>
                <label>Senha<div class="password-wrap"><input type="password" name="password" placeholder="Sua senha" required data-password><button type="button" data-password-toggle>Mostrar</button></div></label>
                <label class="check-row"><input type="checkbox" name="remember" value="1"><span>Lembrar de mim</span></label>
                <button class="btn btn-primary btn-block" type="submit">Entrar <span>→</span></button>
            </form>
            @unless($adminMode)<p class="auth-switch">Ainda não tem conta? <a href="{{ route('register') }}">Criar conta</a></p>@endunless
            @if($adminMode)<p class="auth-switch"><a href="{{ route('login') }}">Voltar ao acesso de cliente</a></p>@endif
        </div>
    </section>
</main>
@endsection
