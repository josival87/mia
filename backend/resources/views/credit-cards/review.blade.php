@extends('layouts.app')
@section('title', 'Revisar fatura | Mia')
@section('content')
<div class="page-heading">
    <div><span class="page-kicker">REVISÃO DA FATURA</span><h1>{{ $invoice->creditCard->name }}</h1><p>{{ ucfirst($invoice->reference_month->translatedFormat('F Y')) }} · {{ $invoice->original_filename }}</p></div>
    <a class="btn btn-ghost" href="{{ route('credit-cards.index') }}">Voltar aos cartões</a>
</div>

<section class="panel invoice-review-summary">
    <div><span class="invoice-file-icon large">PDF</span><div><strong>{{ $invoice->items->count() }} lançamento(s) encontrados</strong><p>A Mia sugeriu as categorias e identificou compras parceladas. Confira o que deseja guardar.</p></div></div>
    <span class="status-label {{ $invoice->status === 'imported' ? 'active' : 'pending' }}">{{ $invoice->status === 'imported' ? 'Importada' : 'Aguardando revisão' }}</span>
</section>

@if($invoice->status === 'imported')
    <section class="panel empty-state"><strong>Esta fatura já foi importada</strong><p>Os lançamentos escolhidos já estão no Financeiro, inclusive as parcelas futuras.</p><a href="{{ route('finance.index', ['month' => $invoice->reference_month->format('Y-m')]) }}">Ver gastos do mês →</a></section>
@else
    <form method="POST" action="{{ route('credit-card-invoices.import', $invoice) }}" data-invoice-review>
        @csrf
        <section class="panel invoice-review-panel">
            <div class="invoice-review-toolbar"><div><button class="btn btn-ghost" type="button" data-invoice-select="all">Marcar tudo</button><button class="btn btn-ghost" type="button" data-invoice-select="none">Desabilitar tudo</button></div><strong data-selected-summary>0 selecionados</strong></div>
            <div class="responsive-table invoice-review-table"><table><thead><tr><th class="select-column">Guardar</th><th>Lançamento</th><th>Categoria sugerida</th><th>Parcela</th><th>Valor</th></tr></thead><tbody>
            @foreach($invoice->items as $item)
                @php
                    $oldItems = old('items');
                    $isSelected = is_array($oldItems) ? in_array((string) $item->id, array_map('strval', $oldItems), true) : !$item->possibleDuplicate;
                @endphp
                <tr>
                    <td class="select-column"><input type="checkbox" name="items[]" value="{{ $item->id }}" data-invoice-item data-amount="{{ $item->amount }}" @checked($isSelected) aria-label="Guardar {{ $item->description }}"></td>
                    <td><div class="invoice-item-description"><strong>{{ $item->description }}</strong><small>{{ $item->purchase_date?->format('d/m/Y') ?? 'Data não informada' }}</small>@if($item->possibleDuplicate)<div class="duplicate-warning"><strong>Possível duplicidade</strong><span>Já existe “{{ $item->possibleDuplicate->description }}” de R$ {{ number_format((float) $item->possibleDuplicate->amount, 2, ',', '.') }} lançado avulso neste cartão.</span><label><input type="checkbox" name="confirm_duplicates[]" value="{{ $item->id }}" data-duplicate-confirm="{{ $item->id }}" @checked(in_array((string) $item->id, array_map('strval', old('confirm_duplicates', [])), true))> Importar mesmo assim</label></div>@endif</div></td>
                    <td><select name="category_ids[{{ $item->id }}]" aria-label="Categoria de {{ $item->description }}"><option value="">Sem categoria</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected((string) old('category_ids.'.$item->id, $item->category_id) === (string) $category->id)>{{ $category->name }}</option>@endforeach</select></td>
                    <td>@if($item->installment_total > 1)<span class="installment-badge">{{ $item->installment_current }}/{{ $item->installment_total }}</span>@else<span class="muted">À vista</span>@endif</td>
                    <td><strong>R$ {{ number_format((float) $item->amount, 2, ',', '.') }}</strong></td>
                </tr>
            @endforeach
            </tbody></table></div>
            <div class="invoice-review-footer"><div><span>Total selecionado nesta fatura</span><strong data-selected-total>R$ 0,00</strong><small>Parcelas futuras serão lançadas automaticamente nos meses seguintes.</small></div><button class="btn btn-primary" type="submit" data-import-submit>Importar selecionados</button></div>
        </section>
    </form>
@endif
@endsection
