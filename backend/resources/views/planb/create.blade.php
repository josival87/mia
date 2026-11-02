@extends('layouts.app')
@section('title', 'Novo negócio — Plano B')
@section('content')
<div class="page-heading"><div><span class="page-kicker">PLANO B</span><h1>Novo negócio</h1><p>Separe os custos e o lucro desta renda extra da sua vida pessoal.</p></div><a class="btn" href="{{ route('planb.index') }}">Voltar</a></div>

@if($errors->any())<div class="alert error"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

<article class="panel">
<form method="POST" action="{{ route('planb.store') }}" class="stack-form">@csrf
    <div class="form-grid">
        <label>Tipo
            <select name="type" required>
                @foreach(\App\Models\Business::TYPES as $key => $label)<option value="{{ $key }}" @selected(old('type', 'driver') === $key)>{{ $label }}</option>@endforeach
            </select>
        </label>
        <label>Nome<input name="name" value="{{ old('name') }}" placeholder="Ex.: Uber, Freela de design" maxlength="80" required></label>
    </div>
    <div class="form-grid">
        <label>Regime de imposto
            <select name="tax_regime">
                @foreach(\App\Models\Business::TAX_REGIMES as $key => $label)<option value="{{ $key }}" @selected(old('tax_regime', 'none') === $key)>{{ $label }}</option>@endforeach
            </select>
        </label>
        <label>Reserva para imposto (% de cada recebimento)<input name="reserve_tax_percent" type="number" step="0.5" min="0" max="50" value="{{ old('reserve_tax_percent', 0) }}"></label>
    </div>
    <div class="form-grid">
        <label>Reserva para meses fracos (%)<input name="reserve_lean_percent" type="number" step="0.5" min="0" max="80" value="{{ old('reserve_lean_percent', 10) }}"></label>
        <label>Meta de lucro mensal (R$)<input name="goal_monthly_profit" type="number" step="0.01" min="0" value="{{ old('goal_monthly_profit') }}"></label>
    </div>

    <h3>Carro (só para motorista de app)</h3>
    <p class="muted">A Mia usa estes dados para calcular depreciação, custo fixo e o custo real por km.</p>
    <div class="form-grid">
        <label>Carro<input name="vehicle_name" value="{{ old('vehicle_name') }}" placeholder="Ex.: Onix 2021"></label>
        <label>Situação
            <select name="ownership">
                <option value="owned" @selected(old('ownership') === 'owned')>Próprio, quitado</option>
                <option value="financed" @selected(old('ownership') === 'financed')>Financiado</option>
                <option value="rented" @selected(old('ownership') === 'rented')>Alugado</option>
            </select>
        </label>
    </div>
    <div class="form-grid">
        <label>Valor atual do carro (R$)<input name="current_value" type="number" step="0.01" min="0" value="{{ old('current_value') }}"></label>
        <label>Valor de revenda ao fim da vida útil (R$)<input name="residual_value" type="number" step="0.01" min="0" value="{{ old('residual_value') }}"></label>
    </div>
    <div class="form-grid">
        <label>Vida útil restante (meses)<input name="useful_life_months" type="number" min="1" max="240" value="{{ old('useful_life_months', 60) }}"></label>
        <label>Custo fixo mensal: aluguel ou parcela + seguro + IPVA/12 (R$)<input name="fixed_monthly_cost" type="number" step="0.01" min="0" value="{{ old('fixed_monthly_cost') }}"></label>
    </div>
    <div class="form-grid">
        <label>Manutenção provisionada por km (R$; ex.: 0,12)<input name="maintenance_per_km" type="number" step="0.0001" min="0" max="10" value="{{ old('maintenance_per_km', '0.12') }}"></label>
        <label>Consumo (km/l)<input name="fuel_km_per_l" type="number" step="0.1" min="0" value="{{ old('fuel_km_per_l') }}"></label>
    </div>
    <button class="btn btn-primary btn-block" type="submit">Criar negócio</button>
</form>
</article>
@endsection
