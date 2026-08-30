@extends('layouts.app')
@section('title', 'Alertas — Mia Assistente')
@section('content')
<div class="page-heading">
    <div><span class="page-kicker">ACOMPANHAMENTO</span><h1>Alertas</h1><p>Acompanhe os marcos alcançados nas suas metas semanais.</p></div>
    @if(auth()->user()->unreadNotifications()->exists())
        <form method="POST" action="{{ route('alerts.read-all') }}">@csrf @method('PATCH')<button class="btn btn-ghost" type="submit">Marcar todos como lidos</button></form>
    @endif
</div>

<section class="panel alert-center">
    @forelse($alerts as $alert)
        <article class="alert-item {{ $alert->read_at ? '' : 'unread' }}">
            <span class="alert-marker" style="background: {{ data_get($alert->data, 'category_color', '#16a34a') }}"></span>
            <div>
                <strong>{{ data_get($alert->data, 'message', 'Atualização da sua meta') }}</strong>
                <p>Porcentagem: {{ number_format((float) data_get($alert->data, 'percentage', 0), 1, ',', '.') }}% · Restante: R$ {{ number_format((float) data_get($alert->data, 'remaining_amount', 0), 2, ',', '.') }}</p>
                <small>{{ $alert->created_at?->translatedFormat('d \d\e F \à\s H:i') }}</small>
            </div>
            @if(!$alert->read_at)
                <form method="POST" action="{{ route('alerts.read', $alert->id) }}">@csrf @method('PATCH')<button type="submit">Marcar como lido</button></form>
            @else
                <span class="read-label">Lido</span>
            @endif
        </article>
    @empty
        <div class="empty-state"><strong>Nenhum alerta por enquanto</strong><p>Quando uma meta atingir 50%, 80%, 90% ou 100%, o aviso aparecerá aqui.</p></div>
    @endforelse
</section>
{{ $alerts->links() }}
@endsection
