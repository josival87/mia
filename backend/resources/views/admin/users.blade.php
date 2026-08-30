@extends('layouts.app')
@section('title', 'Usuários administrativos — Mia')
@section('content')
<div class="page-heading"><div><span class="page-kicker">EQUIPE</span><h1>Usuários administrativos</h1><p>Gerencie quem pode acessar o painel da plataforma.</p></div><button class="btn btn-primary" data-modal-open="user-modal">+ Novo usuário</button></div>
<article class="panel">
    <div class="responsive-table">
        <table>
            <thead><tr><th>Usuário</th><th>CPF</th><th>Nível</th><th>Status</th><th>Criado em</th><th>Ações</th></tr></thead>
            <tbody>
            @forelse($users as $user)
                <tr>
                    <td><div class="table-user"><span class="avatar small">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span><div><strong>{{ $user->name }}</strong><span>{{ $user->email }}</span></div></div></td>
                    <td>{{ $user->cpf ?: '—' }}</td>
                    <td><span class="category-chip">Administrador</span></td>
                    <td><span class="status-label {{ $user->status }}">{{ $user->status === 'active' ? 'Ativo' : 'Bloqueado' }}</span></td>
                    <td>{{ $user->created_at->format('d/m/Y') }}</td>
                    <td><div class="row-actions"><button class="text-button edit-action" type="button" data-modal-open="edit-admin-{{ $user->id }}">Editar</button></div></td>
                </tr>
            @empty
                <tr><td colspan="6"><div class="empty-state"><strong>Nenhum administrador encontrado</strong></div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $users->links() }}
</article>

<div class="modal {{ $errors->any() && old('form_context') === 'create_admin' ? 'open' : '' }}" id="user-modal" aria-hidden="{{ $errors->any() && old('form_context') === 'create_admin' ? 'false' : 'true' }}">
    <div class="modal-backdrop" data-modal-close></div>
    <div class="modal-card">
        <div class="modal-heading"><div><span class="page-kicker">EQUIPE</span><h2>Novo administrador</h2></div><button data-modal-close type="button" aria-label="Fechar">×</button></div>
        <form method="POST" action="{{ route('admin.users.store') }}" class="stack-form">
            @csrf
            <input type="hidden" name="form_context" value="create_admin">
            <div class="form-grid"><label>Nome<input name="name" value="{{ old('form_context') === 'create_admin' ? old('name') : '' }}" required></label><label>CPF<input name="cpf" value="{{ old('form_context') === 'create_admin' ? old('cpf') : '' }}" data-mask="cpf"></label></div>
            <label>E-mail<input type="email" name="email" value="{{ old('form_context') === 'create_admin' ? old('email') : '' }}" required></label>
            <label>Senha inicial<input type="password" name="password" minlength="8" required></label>
            <button class="btn btn-primary btn-block">Criar usuário</button>
        </form>
    </div>
</div>

@foreach($users as $user)
    @php($editingThisAdmin = old('form_context') === 'edit_admin_'.$user->id)
    <div class="modal {{ $errors->any() && $editingThisAdmin ? 'open' : '' }}" id="edit-admin-{{ $user->id }}" aria-hidden="{{ $errors->any() && $editingThisAdmin ? 'false' : 'true' }}">
        <div class="modal-backdrop" data-modal-close></div>
        <div class="modal-card">
            <div class="modal-heading"><div><span class="page-kicker">EQUIPE</span><h2>Editar administrador</h2></div><button data-modal-close type="button" aria-label="Fechar">×</button></div>
            <form method="POST" action="{{ route('admin.users.update', $user) }}" class="stack-form">
                @csrf
                @method('PUT')
                <input type="hidden" name="form_context" value="edit_admin_{{ $user->id }}">
                <div class="form-grid">
                    <label>Nome<input name="name" value="{{ $editingThisAdmin ? old('name') : $user->name }}" required></label>
                    <label>CPF<input name="cpf" value="{{ $editingThisAdmin ? old('cpf') : $user->cpf }}" data-mask="cpf"></label>
                </div>
                <label>E-mail<input type="email" name="email" value="{{ $editingThisAdmin ? old('email') : $user->email }}" required></label>
                <label>Status
                    <select name="status" required>
                        <option value="active" @selected(($editingThisAdmin ? old('status') : $user->status) === 'active')>Ativo</option>
                        <option value="blocked" @selected(($editingThisAdmin ? old('status') : $user->status) === 'blocked')>Bloqueado</option>
                    </select>
                    @if(auth()->id() === $user->id)<small class="field-help">Sua própria conta deve permanecer ativa.</small>@endif
                </label>
                <div class="form-grid">
                    <label>Nova senha <span class="field-help">Opcional</span><input type="password" name="password" minlength="8" autocomplete="new-password"></label>
                    <label>Confirmar nova senha<input type="password" name="password_confirmation" minlength="8" autocomplete="new-password"></label>
                </div>
                <button class="btn btn-primary btn-block">Salvar alterações</button>
            </form>
        </div>
    </div>
@endforeach
@endsection
