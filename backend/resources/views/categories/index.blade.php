@extends('layouts.app')
@section('title', 'Categorias — Mia Assistente')
@section('content')
<div class="page-heading"><div><span class="page-kicker">PERSONALIZE A MIA</span><h1>Categorias</h1><p>Use as sugestões do sistema ou crie categorias só suas.</p></div><button class="btn btn-primary" data-modal-open="category-modal">+ Nova categoria</button></div>
<section class="category-groups">
@foreach(['income'=>'Entradas','expense'=>'Saídas','task'=>'Atividades'] as $kind=>$label)<article class="panel"><div class="panel-heading"><div><h2>{{ $label }}</h2><p>{{ ($categories[$kind] ?? collect())->count() }} categorias</p></div></div><div class="category-list">
@forelse($categories[$kind] ?? [] as $category)<div><span class="category-badge" style="--category:{{ $category->color }}"><i></i>{{ $category->name }}</span><small>{{ $category->user_id ? 'Personalizada' : 'Sugestão da Mia' }}</small>@if($category->user_id)<form method="POST" action="{{ route('categories.destroy', $category) }}" data-confirm="Remover esta categoria?">@csrf @method('DELETE')<button>×</button></form>@else<span class="lock">●</span>@endif</div>@empty<div class="empty-state compact"><p>Nenhuma categoria.</p></div>@endforelse
</div></article>@endforeach
</section>
<div class="modal" id="category-modal" aria-hidden="true"><div class="modal-backdrop" data-modal-close></div><div class="modal-card small"><div class="modal-heading"><div><span class="page-kicker">PERSONALIZAR</span><h2>Nova categoria</h2></div><button type="button" data-modal-close>×</button></div><form method="POST" action="{{ route('categories.store') }}" class="stack-form">@csrf<label>Nome<input name="name" placeholder="Ex.: Assinaturas" required></label><label>Usar em<select name="kind"><option value="expense">Saídas</option><option value="income">Entradas</option><option value="task">Atividades</option></select></label><label>Cor<input type="color" name="color" value="#16a34a"></label><button class="btn btn-primary btn-block">Salvar categoria</button></form></div></div>
@endsection
