@extends('layouts.public')
@section('title', 'Confirmar Telegram — Mia Assistente')
@section('content')
<main class="auth-center"><div class="verify-card">
    <a class="brand" href="{{ route('landing') }}"><span class="brand-mark">M</span><span>Mia<small>Assistente</small></span></a>
    <div class="verify-icon">✦</div><span class="auth-kicker">PASSO 2 DE 3</span><h1>Confirme seu Telegram</h1><p>Clique no botão abaixo e toque em <strong>Iniciar</strong> no Telegram. A Mia receberá seu ID permanente, vinculará sua conta e enviará o código nesta conversa.</p>
    @if(session('success'))<div class="alert success">{{ session('success') }}</div>@endif
    @if(session('dev_code'))<div class="dev-code">Código local: <strong>{{ session('dev_code') }}</strong></div>@endif
    @if($verificationCode)<a class="btn btn-ghost btn-block" href="https://t.me/{{ $botUsername }}?start={{ $verificationCode }}" target="_blank">Abrir bot, tocar em Iniciar e receber código ↗</a>@endif
    @if($errors->any())<div class="alert error">{{ $errors->first() }}</div>@endif
    <form method="POST" action="{{ route('verification.verify') }}" class="stack-form">@csrf<label>Código de confirmação<input class="code-input" name="code" inputmode="numeric" maxlength="6" pattern="\d{6}" placeholder="000000" required autofocus></label><button class="btn btn-primary btn-block" type="submit">Confirmar código</button></form>
    <form method="POST" action="{{ route('verification.renew') }}" class="renew-code-form">@csrf<button class="text-button" type="submit">Gerar novo código e tentar novamente</button></form>
    <small>Por segurança, bots do Telegram não podem iniciar a conversa. O código expira em 15 minutos.</small>
</div></main>
@endsection
