@extends('layouts.app')
@section('title', 'Cartão de Crédito | Mia')
@section('content')
<div class="page-heading">
    <div><span class="page-kicker">CARTÃO DE CRÉDITO</span><h1>Cartões e faturas</h1><p>Cadastre seus cartões e transforme a fatura em gastos categorizados pela Mia.</p></div>
    <button class="btn btn-primary" type="button" data-modal-open="card-modal">+ Novo cartão</button>
</div>

<section class="credit-card-grid">
    @forelse($cards as $card)
        <article class="credit-card-tile {{ $card->active ? '' : 'inactive' }}" style="--card-color: {{ $card->color }}">
            <div class="credit-card-top"><span>{{ $card->brand ?: 'Cartão' }}</span><span class="credit-card-chip"></span></div>
            <strong>{{ $card->name }}</strong>
            <p>•••• •••• •••• {{ $card->last_four }}</p>
            <div class="credit-card-meta"><span>Fecha dia {{ $card->closing_day ?: '—' }}</span><span>Vence dia {{ $card->due_day ?: '—' }}</span></div>
            <div class="credit-card-actions">
                <small>{{ $card->invoices_count }} fatura(s) · {{ $card->active ? 'Ativo' : 'Inativo' }}</small>
                <div><a href="{{ route('credit-cards.edit', $card) }}">Editar</a><form method="POST" action="{{ route('credit-cards.destroy', $card) }}" data-confirm="Remover este cartão e suas revisões de fatura? Os gastos já importados serão preservados.">@csrf @method('DELETE')<button type="submit">Excluir</button></form></div>
            </div>
        </article>
    @empty
        <article class="panel empty-state credit-card-empty"><strong>Nenhum cartão cadastrado</strong><p>Cadastre o primeiro cartão para importar uma fatura.</p><button class="btn btn-primary" type="button" data-modal-open="card-modal">Cadastrar cartão</button></article>
    @endforelse
</section>

<div class="credit-card-columns">
    <section class="panel invoice-upload-panel">
        <div class="panel-heading"><div><h2>Enviar fatura</h2><p>A Mia lê o PDF e prepara os lançamentos para sua conferência.</p></div><span class="ai-badge">✦ COGNIÇÃO MIA</span></div>
        @if($cards->where('active', true)->isNotEmpty())
            <form method="POST" action="{{ route('credit-card-invoices.store') }}" enctype="multipart/form-data" class="stack-form" data-upload-form>
                @csrf
                <div class="form-grid">
                    <label>Cartão<select name="credit_card_id" required>@foreach($cards->where('active', true) as $card)<option value="{{ $card->id }}" @selected((string) old('credit_card_id') === (string) $card->id)>{{ $card->name }} · final {{ $card->last_four }}</option>@endforeach</select></label>
                    <label>Mês da fatura<input type="month" name="reference_month" value="{{ old('reference_month', now()->format('Y-m')) }}" required></label>
                </div>
                <label class="invoice-dropzone">Arquivo PDF<input type="file" name="invoice" accept="application/pdf,.pdf" required><span>PDF de até 10 MB</span></label>
                <button class="btn btn-primary btn-block" type="submit" data-upload-submit>Interpretar fatura</button>
                <small class="privacy-note">O PDF é usado somente durante a leitura e não fica armazenado no MIA.</small>
            </form>
        @else
            <div class="empty-state compact"><strong>Cadastre um cartão ativo primeiro</strong><p>Depois você poderá escolher o mês e enviar a fatura.</p></div>
        @endif
    </section>

    <section class="panel">
        <div class="panel-heading"><div><h2>Faturas recentes</h2><p>Revisões e importações realizadas</p></div></div>
        <div class="invoice-history">
            @forelse($recentInvoices as $invoice)
                <a href="{{ route('credit-card-invoices.review', $invoice) }}"><span class="invoice-file-icon">PDF</span><div><strong>{{ $invoice->creditCard->name }}</strong><small>{{ ucfirst($invoice->reference_month->translatedFormat('F Y')) }} · {{ $invoice->original_filename }}</small></div><span class="status-label {{ $invoice->status === 'imported' ? 'active' : 'pending' }}">{{ $invoice->status === 'imported' ? 'Importada' : 'Revisar' }}</span></a>
            @empty
                <div class="empty-state compact"><p>Nenhuma fatura enviada ainda.</p></div>
            @endforelse
        </div>
    </section>
</div>

<div class="modal {{ $editCard ? 'open' : '' }}" id="card-modal" aria-hidden="{{ $editCard ? 'false' : 'true' }}">
    <div class="modal-backdrop" data-modal-close></div>
    <div class="modal-card small">
        <div class="modal-heading"><div><span class="page-kicker">{{ $editCard ? 'EDITANDO' : 'NOVO CARTÃO' }}</span><h2>{{ $editCard ? 'Editar cartão' : 'Cadastrar cartão' }}</h2></div><button type="button" data-modal-close aria-label="Fechar">×</button></div>
        <form method="POST" action="{{ $editCard ? route('credit-cards.update', $editCard) : route('credit-cards.store') }}" class="stack-form">
            @csrf @if($editCard)@method('PUT')@endif
            <input type="hidden" name="_card_form" value="1">
            <label>Nome do cartão<input name="name" maxlength="80" value="{{ old('name', $editCard?->name) }}" placeholder="Ex.: Nubank principal" required></label>
            <div class="form-grid"><label>Bandeira<input name="brand" maxlength="40" value="{{ old('brand', $editCard?->brand) }}" placeholder="Visa, Mastercard..."></label><label>Últimos 4 dígitos<input name="last_four" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" value="{{ old('last_four', $editCard?->last_four) }}" placeholder="1234" required></label></div>
            <div class="form-grid"><label>Dia de fechamento<input type="number" name="closing_day" min="1" max="31" value="{{ old('closing_day', $editCard?->closing_day) }}"></label><label>Dia de vencimento<input type="number" name="due_day" min="1" max="31" value="{{ old('due_day', $editCard?->due_day) }}"></label></div>
            <div class="form-grid"><label>Cor<input type="color" name="color" value="{{ old('color', $editCard?->color ?? '#16a34a') }}" required></label><label>Status<select name="active" required><option value="1" @selected((string) old('active', (int) ($editCard?->active ?? true)) === '1')>Ativo</option><option value="0" @selected((string) old('active', (int) ($editCard?->active ?? true)) === '0')>Inativo</option></select></label></div>
            <button class="btn btn-primary btn-block" type="submit">{{ $editCard ? 'Salvar alterações' : 'Cadastrar cartão' }}</button>
            @if($editCard)<a class="modal-cancel-link" href="{{ route('credit-cards.index') }}">Cancelar edição</a>@endif
        </form>
    </div>
</div>
@endsection
