@extends('layouts.app')
@section('title', 'Clientes — Admin Mia')
@section('content')
<div class="page-heading"><div><span class="page-kicker">BASE DE CLIENTES</span><h1>Clientes</h1><p>Consulte cadastros, identificadores da API, status e vínculo com o Telegram.</p></div><form class="search-form"><input name="q" value="{{ request('q') }}" placeholder="Buscar nome ou e-mail"><button>Buscar</button></form></div>
@if(session('telegram_connection_code'))<div class="webhook-box" style="margin-bottom:18px"><span>Novo vínculo para {{ session('telegram_connection_client') }}</span><code>{{ session('telegram_connection_code') }}</code><a class="btn btn-light" target="_blank" href="https://t.me/{{ ltrim(\App\Models\SystemSetting::read('telegram_bot_username', 'bot_Mia_Assistente'),'@') }}?start={{ session('telegram_connection_code') }}">Abrir bot com este código ↗</a><small>O cliente deve abrir o link usando a nova conta do Telegram. O código expira em 15 minutos.</small></div>@endif
<article class="panel"><div class="responsive-table"><table><thead><tr><th>Cliente</th><th>CPF</th><th>Telegram</th><th>Status</th><th>Cadastro</th><th></th></tr></thead><tbody>
@forelse($clients as $client)<tr><td><div class="table-user"><span class="avatar small">{{ mb_strtoupper(mb_substr($client->name,0,1)) }}</span><div><strong>{{ $client->name }}</strong><span>{{ $client->email }} · ID da API: {{ $client->id }}</span></div></div></td><td>{{ $client->cpf ?: '—' }}</td><td><span class="telegram-status {{ $client->telegram_user_id ? 'linked':'' }}">{{ $client->telegram_user_id ? '● ID autenticado' : 'Desconectado' }}</span></td><td><span class="status-label {{ $client->status }}">{{ ['active'=>'Ativo','blocked'=>'Bloqueado','pending'=>'Pendente'][$client->status] ?? ucfirst($client->status) }}</span></td><td>{{ $client->created_at->format('d/m/Y') }}</td><td><div class="row-actions"><button class="text-button edit-action" type="button" data-modal-open="edit-client-{{ $client->id }}">Editar</button><form method="POST" action="{{ route('admin.clients.status',$client) }}">@csrf @method('PATCH')<button class="text-button">{{ $client->status === 'active' ? 'Bloquear' : 'Ativar' }}</button></form><form method="POST" action="{{ route('admin.clients.telegram.reset',$client) }}">@csrf<button class="text-button">{{ $client->telegram_user_id ? 'Trocar Telegram' : 'Conectar Telegram' }}</button></form><form method="POST" action="{{ route('admin.clients.destroy', $client) }}" data-confirm="Apagar este cliente permanentemente? Todos os dados financeiros, atividades, metas, integrações e acessos serão removidos. Esta ação não pode ser desfeita.">@csrf @method('DELETE')<button class="text-button" type="submit">Apagar</button></form></div></td></tr>
@empty<tr><td colspan="6"><div class="empty-state"><strong>Nenhum cliente encontrado</strong></div></td></tr>@endforelse
</tbody></table></div>{{ $clients->links() }}</article>

@foreach($clients as $client)
    @php($editingThisClient = old('form_context') === 'edit_client_'.$client->id)
    <div class="modal {{ $errors->any() && $editingThisClient ? 'open' : '' }}" id="edit-client-{{ $client->id }}" aria-hidden="{{ $errors->any() && $editingThisClient ? 'false' : 'true' }}">
        <div class="modal-backdrop" data-modal-close></div>
        <div class="modal-card">
            <div class="modal-heading"><div><span class="page-kicker">BASE DE CLIENTES</span><h2>Editar cliente</h2></div><button data-modal-close type="button" aria-label="Fechar">×</button></div>
            <form method="POST" action="{{ route('admin.clients.update', $client) }}" class="stack-form">
                @csrf
                @method('PUT')
                <input type="hidden" name="form_context" value="edit_client_{{ $client->id }}">
                <div class="form-grid">
                    <label>Nome<input name="name" value="{{ $editingThisClient ? old('name') : $client->name }}" required></label>
                    <label>CPF<input name="cpf" value="{{ $editingThisClient ? old('cpf') : $client->cpf }}" data-mask="cpf"></label>
                </div>
                <label>E-mail<input type="email" name="email" value="{{ $editingThisClient ? old('email') : $client->email }}" required></label>
                <label>Status
                    <select name="status" required>
                        <option value="active" @selected(($editingThisClient ? old('status') : $client->status) === 'active')>Ativo</option>
                        <option value="pending" @selected(($editingThisClient ? old('status') : $client->status) === 'pending')>Pendente</option>
                        <option value="blocked" @selected(($editingThisClient ? old('status') : $client->status) === 'blocked')>Bloqueado</option>
                    </select>
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
