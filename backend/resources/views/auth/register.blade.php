@extends('layouts.public')
@section('title', 'Criar conta — Mia Assistente')
@section('content')
<main class="auth-shell">
    <section class="auth-aside register-aside"><a class="brand brand-light" href="{{ route('landing') }}"><span class="brand-mark">M</span><span>Mia<small>Assistente</small></span></a><div><span class="eyebrow light"><i></i> Comece em minutos</span><h1>Um espaço para tudo que importa.</h1><ol class="signup-steps"><li class="active"><b>1</b><span>Seus dados<small>Identificação segura</small></span></li><li><b>2</b><span>Confirmar Telegram<small>Validação pelo bot</small></span></li><li><b>3</b><span>Criar sua senha<small>Acesso liberado</small></span></li></ol></div></section>
    <section class="auth-panel"><div class="auth-card wide">
        <a class="mobile-auth-brand brand" href="{{ route('landing') }}"><span class="brand-mark">M</span><span>Mia</span></a><span class="auth-kicker">PASSO 1 DE 3</span><h2>Vamos criar sua conta</h2><p>Preencha seus dados. No próximo passo, o próprio Telegram informará seu ID permanente à Mia.</p>
        @if($errors->any())<div class="alert error"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <form method="POST" action="{{ route('register.store') }}" class="stack-form">@csrf
            <div class="form-grid"><label>Nome completo<input name="name" value="{{ old('name') }}" placeholder="Como podemos chamar você?" required></label><label>CPF<input name="cpf" value="{{ old('cpf') }}" placeholder="000.000.000-00" data-mask="cpf" required></label></div>
            <label>E-mail<input type="email" name="email" value="{{ old('email') }}" placeholder="voce@exemplo.com" required></label>
            <button class="btn btn-primary btn-block" type="submit">Continuar para validação <span>→</span></button>
        </form><p class="auth-switch">Já possui uma conta? <a href="{{ route('login') }}">Entrar</a></p>
    </div></section>
</main>
@endsection
