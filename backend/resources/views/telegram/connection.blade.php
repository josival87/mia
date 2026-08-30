@extends('layouts.app')
@section('title', 'Telegram — Mia Assistente')
@section('content')
<div class="page-heading"><div><span class="page-kicker">INTEGRAÇÕES SEGURAS</span><h1>Telegram e iPhone</h1><p>Controle os canais que podem enviar lançamentos para a Mia.</p></div></div>
<div class="settings-layout">
    <article class="panel settings-section">
        <div class="panel-heading"><div><h2>Conta vinculada</h2><p>A autenticação usa o ID permanente da sua conta no Telegram.</p></div><span class="status-label {{ auth()->user()->telegram_user_id ? 'active' : 'pending' }}">{{ auth()->user()->telegram_user_id ? 'Conectado' : 'Desconectado' }}</span></div>
        @if(auth()->user()->telegram_user_id)
            <div class="integration-card"><div><span class="integration-logo gemini">TG</span><div><strong>{{ auth()->user()->telegram ? '@'.auth()->user()->telegram : 'Conta Telegram' }}</strong><small>ID autenticado e protegido no banco</small></div></div></div>
            <div class="row-actions"><form method="POST" action="{{ route('telegram.reconnect') }}">@csrf<button class="btn btn-primary">Conectar outra conta</button></form><form method="POST" action="{{ route('telegram.disconnect') }}">@csrf @method('DELETE')<button class="btn btn-ghost">Desconectar</button></form></div>
        @else
            <p class="muted">Nenhuma conta está autorizada. Gere um novo vínculo e abra o bot usando o código temporário.</p>
            <form method="POST" action="{{ route('telegram.reconnect') }}">@csrf<button class="btn btn-primary">Gerar vínculo</button></form>
        @endif

        @php($activeCode = session('telegram_connection_code') ?: $code?->code)
        @if($activeCode)
            <div class="webhook-box"><span>Código temporário — expira em 15 minutos</span><code>{{ $activeCode }}</code><a class="btn btn-light" href="https://t.me/{{ $botUsername }}?start={{ $activeCode }}" target="_blank">Abrir bot e conectar ↗</a><small>Ao abrir, o bot registra o telegram_user_id da nova conta e invalida este código.</small></div>
        @endif
    </article>
    <article class="panel"><div class="panel-heading"><div><h2>Como a Mia protege seus registros</h2><p>Somente mensagens do ID vinculado são aceitas; atualizações repetidas são ignoradas e lançamentos suspeitos pedem confirmação.</p></div></div></article>

    <article class="panel settings-section" id="iphone">
        <div class="panel-heading">
            <div><h2>Automação bancária no iPhone</h2><p>Receba um SMS ou e-mail do banco, envie o texto para a Mia e continue a confirmação pelo Telegram.</p></div>
            <span class="status-label {{ auth()->user()->iphone_ingest_token_hash ? 'active' : 'pending' }}">{{ auth()->user()->iphone_ingest_token_hash ? 'Ativa' : 'Desativada' }}</span>
        </div>

        @if(!auth()->user()->telegram_chat_id)
            <div class="alert warning">Conecte primeiro o Telegram. É nele que a Mia avisará se registrou, ignorou ou precisa confirmar o lançamento.</div>
        @endif

        <div class="telegram-token-guide">
            <strong>O que funciona no iPhone</strong>
            <span><b>SMS e e-mail:</b> podem iniciar uma Automação Pessoal no app Atalhos e rodar sem confirmação.</span>
            <span><b>Push do app do banco:</b> o iOS não entrega o texto das notificações de outros apps ao Atalhos. Nesse caso, prefira SMS, e-mail, uma automação de transação da Carteira quando disponível ou futura integração via Open Finance.</span>
            <small>Mensagens parecidas com código de acesso, OTP, token, senha, CVV ou código de segurança são descartadas antes de chegar à IA.</small>
        </div>

        <div class="webhook-box">
            <span>Endpoint do Atalho — precisa estar acessível pelo iPhone</span>
            <code>{{ url('/api/v1/iphone/eventos-bancarios') }}</code>
            <small>Em produção use HTTPS. Em ambiente local, “localhost” aponta para o próprio iPhone; use o IP do computador na mesma rede ou um túnel HTTPS protegido.</small>
        </div>

        @if(session('iphone_ingest_token'))
            <div class="webhook-box iphone-token-box">
                <span>Chave exibida uma única vez — copie agora</span>
                <code>{{ session('iphone_ingest_token') }}</code>
                <small>Use no cabeçalho Authorization como: Bearer SUA_CHAVE. Não envie essa chave em mensagens nem capturas de tela.</small>
            </div>
        @endif

        <div class="iphone-steps">
            <strong>Configure no app Atalhos</strong>
            <ol>
                <li>Crie uma Automação Pessoal com o gatilho <b>Mensagem</b>, selecione somente o remetente oficial do banco e escolha <b>Executar imediatamente</b>.</li>
                <li>Adicione <b>Gerar UUID</b> e depois <b>Obter Conteúdo de URL</b>, usando o endpoint acima, método <b>POST</b> e corpo <b>JSON</b>.</li>
                <li>Envie <code>event_id</code> com o UUID, <code>text</code> com o conteúdo da mensagem, <code>source</code> como <code>sms</code> e, opcionalmente, <code>sender</code> e <code>received_at</code>.</li>
                <li>No cabeçalho, informe <code>Authorization</code> = <code>Bearer SUA_CHAVE</code>. Teste com um alerta real de compra de baixo valor.</li>
            </ol>
        </div>

        <div class="webhook-box">
            <span>Exemplo do corpo JSON</span>
            <code>{"event_id":"UUID-GERADO","text":"CONTEÚDO-DA-MENSAGEM","source":"sms","sender":"NOME-DO-BANCO"}</code>
        </div>

        <div class="row-actions">
            <form method="POST" action="{{ route('iphone.token.store') }}">@csrf<button class="btn btn-primary" {{ !auth()->user()->telegram_chat_id ? 'disabled' : '' }}>{{ auth()->user()->iphone_ingest_token_hash ? 'Gerar nova chave' : 'Gerar chave do iPhone' }}</button></form>
            @if(auth()->user()->iphone_ingest_token_hash)
                <form method="POST" action="{{ route('iphone.token.destroy') }}">@csrf @method('DELETE')<button class="btn btn-ghost">Revogar automação</button></form>
            @endif
        </div>

        @if(auth()->user()->iphone_ingest_last_used_at)
            <small class="muted">Último evento autenticado em {{ auth()->user()->iphone_ingest_last_used_at->format('d/m/Y H:i') }}.</small>
        @endif
    </article>
</div>
@endsection
