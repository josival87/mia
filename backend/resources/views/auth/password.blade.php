@extends('layouts.public')
@section('title', 'Criar senha — Mia Assistente')
@section('content')
<main class="auth-center"><div class="verify-card">
    <a class="brand" href="{{ route('landing') }}"><span class="brand-mark">M</span><span>Mia<small>Assistente</small></span></a><div class="verify-icon success-icon">✓</div><span class="auth-kicker">PASSO 3 DE 3</span><h1>Proteja sua conta</h1><p>Crie uma senha com pelo menos 8 caracteres.</p>
    @if($errors->any())<div class="alert error">{{ $errors->first() }}</div>@endif
    <form method="POST" action="{{ route('password.store') }}" class="stack-form">@csrf<label>Senha<input type="password" name="password" minlength="8" required autofocus></label><label>Confirmar senha<input type="password" name="password_confirmation" minlength="8" required></label><button class="btn btn-primary btn-block" type="submit">Concluir e acessar</button></form>
</div></main>
@endsection
