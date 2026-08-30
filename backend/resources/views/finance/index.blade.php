@extends('layouts.app')
@section('title', 'Financeiro — Mia Assistente')
@section('content')
<div class="page-heading">
    <div><span class="page-kicker">CONTROLE FINANCEIRO</span><h1>Financeiro</h1><p>Entenda para onde seu dinheiro vai, mês a mês.</p></div>
    <div class="page-actions"><button class="btn btn-ghost" type="button" data-modal-open="goal-modal">Gerenciar metas</button><button class="btn btn-primary" type="button" data-modal-open="finance-modal">+ Novo lançamento</button></div>
</div>
<div class="month-switcher standalone"><a href="{{ route('finance.index', ['month' => $month->copy()->subMonth()->format('Y-m')]) }}">‹</a><strong>{{ ucfirst($month->translatedFormat('F Y')) }}</strong><a href="{{ route('finance.index', ['month' => $month->copy()->addMonth()->format('Y-m')]) }}">›</a></div>
<section class="metric-grid three compact-metrics">
    <article class="metric-card"><span>Entradas</span><strong class="positive">R$ {{ number_format($income, 2, ',', '.') }}</strong></article>
    <article class="metric-card"><span>Saídas</span><strong class="negative">R$ {{ number_format($expense, 2, ',', '.') }}</strong></article>
    <article class="metric-card dark-card"><span>Saldo</span><strong>R$ {{ number_format($income - $expense, 2, ',', '.') }}</strong></article>
</section>

<section class="goal-section" aria-labelledby="weekly-goals-title">
    <div class="section-heading"><div><h2 id="weekly-goals-title">Metas da semana</h2><p>As metas mensais continuam ativas e reiniciam o acompanhamento a cada mês.</p></div><button class="text-button" type="button" data-modal-open="goal-modal">+ Cadastrar meta</button></div>
    <div class="goal-grid">
        @forelse($categoryGoals as $goal)
            @php($progress = $goalProgress->get($goal->id))
            <article class="goal-card {{ $progress['status'] }}">
                <div class="goal-card-heading">
                    <div><i style="background: {{ $progress['category_color'] }}"></i><div><strong>{{ $progress['category_name'] }}</strong><span>Semana {{ $progress['week_number'] }} de {{ $progress['weeks_in_month'] }}</span></div></div>
                    <div class="goal-actions">
                        <button type="button" data-modal-open="goal-modal" data-goal-edit data-category-id="{{ $goal->category_id }}" data-monthly-amount="{{ $goal->monthly_amount }}">Editar</button>
                        <form method="POST" action="{{ route('finance.goals.destroy', $goal) }}" data-confirm="Apagar esta meta recorrente?">@csrf @method('DELETE')<button type="submit">Apagar</button></form>
                    </div>
                </div>
                <div class="goal-values"><div><span>Meta mensal</span><strong>R$ {{ number_format((float) $progress['monthly_amount'], 2, ',', '.') }}</strong></div><div><span>Meta semanal</span><strong>R$ {{ number_format((float) $progress['weekly_target'], 2, ',', '.') }}</strong></div><div><span>Restante</span><strong>R$ {{ number_format((float) $progress['remaining_amount'], 2, ',', '.') }}</strong></div></div>
                <div class="goal-progress-label"><span>R$ {{ number_format((float) $progress['spent_amount'], 2, ',', '.') }} utilizados</span><strong>{{ number_format((float) $progress['percentage'], 1, ',', '.') }}%</strong></div>
                <div class="goal-progress"><i style="width: {{ $progress['bar_percentage'] }}%"></i></div>
            </article>
        @empty
            <div class="panel empty-state goal-empty"><strong>Nenhuma meta cadastrada</strong><p>Defina um valor mensal para uma categoria. A Mia dividirá o valor igualmente entre as semanas e renovará o controle todo mês.</p><button class="btn btn-primary" type="button" data-modal-open="goal-modal">Cadastrar primeira meta</button></div>
        @endforelse
    </div>
</section>

<div class="content-main finance-content">
    <section class="category-tables-section" aria-labelledby="category-totals-title">
        <div class="section-heading"><div><h2 id="category-totals-title">Totais por categoria</h2><p>Ordenados do maior para o menor em {{ ucfirst($month->translatedFormat('F Y')) }}</p></div></div>
        <div class="category-tables-grid">
            @foreach([
                ['title' => 'Entradas', 'type' => 'income', 'label' => 'entrada', 'report' => $incomeCategoryReport],
                ['title' => 'Saídas', 'type' => 'expense', 'label' => 'saída', 'report' => $expenseCategoryReport],
            ] as $group)
                <article class="panel category-total-card {{ $group['type'] }}">
                    <div class="category-total-card-heading">
                        <div><span class="record-icon {{ $group['type'] }}">{{ $group['type'] === 'income' ? '↓' : '↑' }}</span><div><h3>{{ $group['title'] }}</h3><p>Da maior para a menor {{ $group['label'] }}</p></div></div>
                        <strong>R$ {{ number_format($group['report']['total'], 2, ',', '.') }}</strong>
                    </div>
                    <div class="responsive-table category-totals-table"><table><thead><tr><th>Categoria</th><th>Participação</th><th>Total</th></tr></thead><tbody>
                        @forelse($group['report']['items'] as $item)
                            <tr>
                                <td><span class="category-chip"><i style="background: {{ $item['color'] }}"></i>{{ $item['name'] }}</span></td>
                                <td><div class="category-share"><span class="category-percentage"><i style="width: {{ min(100, $item['percentage']) }}%"></i></span><strong>{{ number_format($item['percentage'], 1, ',', '.') }}%</strong></div></td>
                                <td><strong class="amount {{ $group['type'] }}">R$ {{ number_format($item['total'], 2, ',', '.') }}</strong></td>
                            </tr>
                        @empty
                            <tr><td colspan="3"><div class="empty-state compact"><strong>Nenhuma {{ $group['label'] }} neste mês</strong><p>Os lançamentos aparecerão aqui por categoria.</p></div></td></tr>
                        @endforelse
                    </tbody><tfoot><tr><th colspan="2">Total de {{ strtolower($group['title']) }}</th><td><strong class="{{ $group['type'] === 'income' ? 'positive' : 'negative' }}">R$ {{ number_format($group['report']['total'], 2, ',', '.') }}</strong></td></tr></tfoot></table></div>
                </article>
            @endforeach
        </div>
    </section>

    <article class="panel category-report">
        <div class="panel-heading"><div><h2>Relatório por categoria</h2><p>Gráficos de pizza das entradas e saídas do mês</p></div></div>
        <div class="category-report-grid">
            @foreach([
                ['title' => 'Entradas por categoria', 'type' => 'income', 'report' => $incomeCategoryReport],
                ['title' => 'Saídas por categoria', 'type' => 'expense', 'report' => $expenseCategoryReport],
            ] as $chart)
                <section class="pie-report {{ $chart['type'] }}">
                    <div class="pie-report-heading"><div><span class="record-icon {{ $chart['type'] }}">{{ $chart['type'] === 'income' ? '↓' : '↑' }}</span><h3>{{ $chart['title'] }}</h3></div><strong>R$ {{ number_format($chart['report']['total'], 2, ',', '.') }}</strong></div>
                    <div class="pie-report-body">
                        <div class="category-pie" style="--pie: {{ $chart['report']['gradient'] }}" role="img" aria-label="Gráfico de pizza de {{ $chart['type'] === 'income' ? 'entradas' : 'saídas' }} por categoria">
                            <div class="category-pie-center"><small>Total</small><strong>{{ count($chart['report']['items']) }}</strong><span>categoria(s)</span></div>
                        </div>
                        <div class="pie-legend">
                            @forelse($chart['report']['items'] as $item)
                                <div><i style="background: {{ $item['color'] }}"></i><span title="{{ $item['name'] }}">{{ $item['name'] }}</span><strong>{{ number_format($item['percentage'], 1, ',', '.') }}%</strong></div>
                            @empty
                                <p>Nenhum lançamento deste tipo no mês.</p>
                            @endforelse
                        </div>
                    </div>
                </section>
            @endforeach
        </div>
    </article>

    <article class="panel">
        <div class="panel-heading"><div><h2>Todos os lançamentos</h2><p>{{ $records->total() }} registro(s) no período</p></div></div>
        <div class="responsive-table"><table><thead><tr><th>Descrição</th><th>Categoria</th><th>Data</th><th>Valor</th><th></th></tr></thead><tbody>
            @forelse($records as $record)
                <tr><td><div class="table-title"><span class="record-icon {{ $record->type }}">{{ $record->type === 'income' ? '↓' : '↑' }}</span><div><strong>{{ $record->title ?: $record->description }}</strong>@if($record->title)<br><small class="muted">{{ $record->description }}</small>@endif</div></div></td><td><span class="category-chip"><i style="background:{{ $record->category?->color ?? '#94a3b8' }}"></i>{{ $record->category?->name ?? 'Sem categoria' }}</span></td><td>{{ $record->occurred_on->format('d/m/Y') }}</td><td><strong class="amount {{ $record->type }}">{{ $record->type === 'income' ? '+' : '-' }} R$ {{ number_format($record->amount, 2, ',', '.') }}</strong></td><td><div class="row-actions"><a href="{{ route('finance.edit', ['finance' => $record, 'month' => $month->format('Y-m')]) }}">Editar</a><form method="POST" action="{{ route('finance.destroy', $record) }}" data-confirm="Remover este lançamento?">@csrf @method('DELETE')<button>Excluir</button></form></div></td></tr>
            @empty
                <tr><td colspan="5"><div class="empty-state"><strong>Nenhum lançamento neste mês</strong><p>Crie um lançamento ou fale com a Mia no Telegram.</p></div></td></tr>
            @endforelse
        </tbody></table></div>{{ $records->links() }}
    </article>
</div>

<div class="modal {{ $errors->getBag('goal')->any() ? 'open' : '' }}" id="goal-modal" aria-hidden="{{ $errors->getBag('goal')->any() ? 'false' : 'true' }}">
    <div class="modal-backdrop" data-modal-close></div>
    <div class="modal-card small">
        <div class="modal-heading"><div><span class="page-kicker">META RECORRENTE</span><h2>Meta por categoria</h2></div><button type="button" data-modal-close aria-label="Fechar">×</button></div>
        <p class="modal-description">Informe o valor mensal. Ele será dividido igualmente entre as semanas do mês, e o acompanhamento recomeçará automaticamente todo mês até você editar ou apagar a meta.</p>
        @if($errors->getBag('goal')->any())<div class="alert error"><ul>@foreach($errors->getBag('goal')->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <form method="POST" action="{{ route('finance.goals.store') }}" class="stack-form" data-goal-form>@csrf
            <label>Categoria de saída<select name="category_id" required><option value="">Selecione</option>@foreach($goalCategories as $category)<option value="{{ $category->id }}" {{ (string) old('category_id') === (string) $category->id ? 'selected' : '' }}>{{ $category->name }}</option>@endforeach</select></label>
            <label>Meta mensal (R$)<input type="number" name="monthly_amount" value="{{ old('monthly_amount') }}" min="0.01" max="999999999999.99" step="0.01" placeholder="Ex.: 1200,00" required></label>
            <div class="goal-form-note"><strong>Como funciona</strong><span>A mesma meta seguirá ativa nos próximos meses. Os gastos e os alertas de 50%, 80%, 90% e 100% serão zerados na virada de cada mês.</span></div>
            <button class="btn btn-primary btn-block" type="submit">Salvar meta</button>
        </form>
    </div>
</div>

<div class="modal {{ $editRecord || $errors->any() ? 'open' : '' }}" id="finance-modal" aria-hidden="{{ $editRecord || $errors->any() ? 'false' : 'true' }}">
    <div class="modal-backdrop" data-modal-close></div>
    <div class="modal-card">
        <div class="modal-heading"><div><span class="page-kicker">{{ $editRecord ? 'EDITANDO' : 'NOVO REGISTRO' }}</span><h2>{{ $editRecord ? 'Editar lançamento' : 'Novo lançamento' }}</h2></div><button type="button" data-modal-close aria-label="Fechar">×</button></div>
        <form method="POST" action="{{ $editRecord ? route('finance.update', $editRecord) : route('finance.store') }}" class="stack-form" data-category-form>@csrf @if($editRecord)@method('PUT')@endif
            <div class="segmented"><label><input type="radio" name="type" value="expense" {{ old('type', $editRecord?->type ?? 'expense') === 'expense' ? 'checked' : '' }}><span>Saída</span></label><label><input type="radio" name="type" value="income" {{ old('type', $editRecord?->type) === 'income' ? 'checked' : '' }}><span>Entrada</span></label></div>
            <label>Descrição<input name="description" value="{{ old('description', $editRecord?->description) }}" placeholder="Ex.: Supermercado" required></label>
            <div class="form-grid"><label>Valor (R$)<input type="number" name="amount" value="{{ old('amount', $editRecord?->amount) }}" min="0.01" step="0.01" placeholder="0,00" required></label><label>Data<input type="date" name="occurred_on" value="{{ old('occurred_on', $editRecord?->occurred_on?->format('Y-m-d') ?? now()->toDateString()) }}" required></label></div>
            <label>Categoria<select name="category_id"><option value="">Sem categoria</option>@foreach($categories as $category)<option value="{{ $category->id }}" data-kind="{{ $category->kind }}" {{ (string) old('category_id', $editRecord?->category_id) === (string) $category->id ? 'selected' : '' }}>{{ $category->name }}</option>@endforeach</select></label>
            <button class="btn btn-primary btn-block" type="submit">{{ $editRecord ? 'Salvar alterações' : 'Adicionar lançamento' }}</button>
            @if($editRecord)<a class="modal-cancel-link" href="{{ route('finance.index', ['month' => $month->format('Y-m')]) }}">Cancelar edição</a>@endif
        </form>
    </div>
</div>
@endsection
