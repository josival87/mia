@extends('layouts.app')
@section('title', 'Minha conta — Mia Assistente')
@section('content')
<div class="page-heading"><div><span class="page-kicker">PRIVACIDADE</span><h1>Minha conta e meus dados</h1><p>Você controla seus dados: baixe uma cópia completa ou exclua tudo quando quiser.</p></div></div>
@if(session('success'))<div class="alert success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert error"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

<section class="dashboard-columns">
    <article class="panel"><div class="panel-heading"><div><h2>Renda principal</h2><p>Usada para comparar o valor da sua hora principal com o lucro por hora do Plano B</p></div></div>
        @if(auth()->user()->primaryHourlyRate() !== null)
            <div class="alert success" style="margin-bottom:1rem">
                Sua hora de trabalho principal vale <strong>R$ {{ number_format(auth()->user()->primaryHourlyRate(), 2, ',', '.') }}/h</strong>.
            </div>
        @endif
        <form method="POST" action="{{ route('account.income.update') }}" class="stack-form">@csrf @method('PUT')
            <div class="form-grid">
                <label>Salário mensal (R$)<input name="primary_salary" type="number" step="0.01" min="0" value="{{ old('primary_salary', auth()->user()->primary_salary) }}" placeholder="Ex.: 5000,00"></label>
                <label>Horas trabalhadas por mês<input name="primary_monthly_hours" type="number" step="1" min="1" max="744" value="{{ old('primary_monthly_hours', auth()->user()->primary_monthly_hours) }}" placeholder="Ex.: 160, 220"></label>
            </div>
            <button class="btn btn-primary" type="submit">Salvar renda principal</button>
        </form>
    </article>

    <article class="panel"><div class="panel-heading"><div><h2>Baixar meus dados</h2><p>Arquivo JSON com conta, lançamentos, atividades, metas e Plano B</p></div></div>
        <a class="btn btn-primary" href="{{ route('account.export') }}">Baixar arquivo</a>
        <p class="muted">Leia como tratamos seus dados na <a href="{{ route('legal.privacy') }}">Política de Privacidade</a>.</p>
    </article>

    <article class="panel"><div class="panel-heading"><div><h2>Excluir minha conta</h2><p>Apaga a conta e todos os dados de forma definitiva. Não dá para desfazer.</p></div></div>
        <form method="POST" action="{{ route('account.destroy') }}" class="stack-form" data-confirm="Excluir sua conta e todos os seus dados? Esta ação não pode ser desfeita.">@csrf @method('DELETE')
            <label>Sua senha<input type="password" name="password" autocomplete="current-password" required></label>
            <label>Digite EXCLUIR para confirmar<input name="confirmation" autocomplete="off" required></label>
            <button class="btn" type="submit">Excluir definitivamente</button>
        </form>
    </article>
</section>
@endsection
