@extends('layouts.app')
@section('title', 'Configurações — Admin Mia')
@section('content')
<div class="page-heading"><div><span class="page-kicker">CONFIGURAÇÕES</span><h1>Sistema e integrações</h1><p>Dados da empresa, inteligência artificial, Telegram e API financeira.</p></div></div>
<form method="POST" action="{{ route('admin.settings.update') }}" class="settings-layout">@csrf @method('PUT')
    <article class="panel settings-section"><div class="panel-heading"><div><h2>Dados da empresa</h2><p>Identificação exibida na plataforma</p></div></div><div class="form-grid"><label>Nome<input name="company_name" value="{{ old('company_name',$company->name) }}" required></label><label>CNPJ<input name="cnpj" value="{{ old('cnpj',$company->cnpj) }}"></label><label>Telefone<input name="phone" value="{{ old('phone',$company->phone) }}"></label><label>E-mail<input type="email" name="email" value="{{ old('email',$company->email) }}"></label></div><label>Chave Pix<input name="pix_key" value="{{ old('pix_key',$company->pix_key) }}"></label></article>
    <article class="panel settings-section"><div class="panel-heading"><div><h2>Inteligência artificial</h2><p>Chaves são criptografadas antes de serem salvas</p></div><span class="secure-label">Protegido</span></div>
    <label>Provedor principal<select name="ai_primary_provider" required><option value="gemini" @selected(old('ai_primary_provider',$settings['ai_primary_provider']) === 'gemini')>Google Gemini — recomendado para texto e áudio</option><option value="openai" @selected(old('ai_primary_provider',$settings['ai_primary_provider']) === 'openai')>OpenAI / ChatGPT</option></select></label>
    <div class="integration-card"><div><span class="integration-logo gemini">G</span><div><strong>Google Gemini</strong><small>{{ $settings['has_gemini_key'] ? 'Chave configurada · texto e áudio' : 'Não configurado' }}</small></div></div><span class="status-label {{ $settings['has_gemini_key'] ? 'active':'pending' }}">{{ $settings['has_gemini_key'] ? 'Conectado':'Principal' }}</span></div><div class="form-grid"><label>API key Gemini<input type="password" name="gemini_api_key" autocomplete="new-password" placeholder="{{ $settings['has_gemini_key'] ? '•••••••• (preservar atual)' : 'API key' }}"></label><label>Modelo<input name="gemini_model" value="{{ old('gemini_model',$settings['gemini_model']) }}" required></label></div>
    <div class="integration-card"><div><span class="integration-logo openai">AI</span><div><strong>OpenAI / ChatGPT</strong><small>{{ $settings['has_openai_key'] ? 'Chave configurada · contingência' : 'Não configurado' }}</small></div></div><span class="status-label {{ $settings['has_openai_key'] ? 'active':'pending' }}">{{ $settings['has_openai_key'] ? 'Conectado':'Opcional' }}</span></div><div class="form-grid"><label>API key OpenAI<input type="password" name="openai_api_key" autocomplete="new-password" placeholder="{{ $settings['has_openai_key'] ? '•••••••• (preservar atual)' : 'sk-...' }}"></label><label>Modelo<input name="openai_model" value="{{ old('openai_model',$settings['openai_model']) }}" required></label></div>
    <label>URL interna do serviço cognitivo<input type="url" name="cognition_url" value="{{ old('cognition_url',$settings['cognition_url']) }}" required></label></article>
    <article id="telegram-bot" class="panel settings-section">
        <div class="panel-heading">
            <div><h2>Bot do Telegram</h2><p>Token que permite à Mia receber e responder mensagens e áudios</p></div>
            <span class="status-label {{ $telegramStatus['active'] ? 'active':'pending' }}">{{ $telegramStatus['active'] ? 'Bot ativo':'Aguardando ativação' }}</span>
        </div>
        <div class="telegram-token-guide">
            <strong>Onde conseguir o token?</strong>
            <span>Abra o <b>@BotFather</b> no Telegram, crie ou selecione o Bot Mia e copie o token da API. Ele tem o formato <code>123456789:AA...</code>.</span>
            <small>Esse token não é o telegram_user_id do cliente. O ID de cada cliente é capturado automaticamente durante o vínculo.</small>
        </div>
        <div class="integration-card">
            <div><span class="integration-logo">TG</span><div><strong>{{ $telegramStatus['bot_username'] ? '@'.$telegramStatus['bot_username'] : 'Bot Mia' }}</strong><small>
                @if($telegramStatus['active'])
                    {{ $telegramStatus['mode'] === 'polling' ? 'Polling local em execução permanente' : 'Webhook de produção registrado' }}
                @elseif($telegramStatus['last_error'])
                    {{ $telegramStatus['last_error'] }}
                @else
                    Salve as configurações para ativar o recebimento
                @endif
            </small></div></div>
            <span class="status-label {{ $telegramStatus['bot_valid'] ? 'active':'pending' }}">{{ $telegramStatus['bot_valid'] ? 'Token válido':'Não verificado' }}</span>
        </div>
        <div class="form-grid">
            <label>Token de acesso do bot (BotFather)
                <input type="password" name="telegram_bot_token" autocomplete="new-password" placeholder="{{ $settings['has_telegram_token'] ? '•••••••• (token já salvo; deixe vazio para preservar)' : '123456789:AA...' }}">
            </label>
            <label>Usuário do bot (@username)
                <input name="telegram_bot_username" value="{{ old('telegram_bot_username',$settings['telegram_bot_username']) }}" placeholder="bot_mia" required>
            </label>
        </div>
        <div class="form-grid">
            <label>Modo de recebimento
                <select name="telegram_update_mode" required>
                    <option value="polling" @selected(old('telegram_update_mode',$settings['telegram_update_mode']) === 'polling')>Local — polling permanente</option>
                    <option value="webhook" @selected(old('telegram_update_mode',$settings['telegram_update_mode']) === 'webhook')>Produção — webhook HTTPS</option>
                </select>
            </label>
            <label>URL pública do webhook
                <input type="url" name="telegram_webhook_url" value="{{ old('telegram_webhook_url',$settings['telegram_webhook_url']) }}" placeholder="https://seu-dominio.com/telegram/webhook">
            </label>
        </div>
        <div class="telegram-token-guide">
            <strong>Local e produção</strong>
            <span>Use <b>polling</b> no computador local. Na hospedagem, selecione <b>webhook</b> e informe a URL HTTPS pública.</span>
            <small>O mesmo bot não pode usar polling e webhook ao mesmo tempo. Para manter local e produção ativos simultaneamente, use dois bots/tokens diferentes.</small>
        </div>
        <label>Secret de segurança do webhook
            <input type="password" name="telegram_webhook_secret" autocomplete="new-password" placeholder="{{ $settings['has_webhook_secret'] ? '•••••••• (preservar atual)' : 'Crie uma senha secreta para validar o Telegram' }}">
        </label>
        <div class="form-grid">
            <label>Confirmar valores acima de R$<input type="number" step="0.01" min="0" name="telegram_confirmation_amount" value="{{ old('telegram_confirmation_amount',$settings['telegram_confirmation_amount']) }}" required></label>
            <label>Confiança para registro direto<input type="number" step="0.01" min="0" max="1" name="telegram_direct_confidence" value="{{ old('telegram_direct_confidence',$settings['telegram_direct_confidence']) }}" required></label>
            <label>Confiança mínima aceita<input type="number" step="0.01" min="0" max="1" name="telegram_min_confidence" value="{{ old('telegram_min_confidence',$settings['telegram_min_confidence']) }}" required></label>
        </div>
        <div class="webhook-box"><span>Endpoint que recebe as mensagens do Telegram</span><code>{{ url('/telegram/webhook') }}</code><small>Ao salvar em modo produção, a Mia registra automaticamente o webhook e o secret no Telegram.</small></div>
    </article>
    <article class="panel settings-section"><div class="panel-heading"><div><h2>API de recebimentos</h2><p>Autorize sistemas externos a lançar entradas para clientes ativos</p></div><span class="secure-label">Protegido</span></div>
        <div class="integration-card"><div><span class="integration-logo">AP</span><div><strong>AlugaPro</strong><small>{{ $settings['has_alugapro_finance_key'] ? 'Chave configurada' : 'Não configurado' }}</small></div></div><span class="status-label {{ $settings['has_alugapro_finance_key'] ? 'active':'pending' }}">{{ $settings['has_alugapro_finance_key'] ? 'Conectado':'Pendente' }}</span></div>
        <label>Chave Bearer do AlugaPro<input type="password" name="alugapro_finance_api_key" minlength="32" autocomplete="new-password" placeholder="{{ $settings['has_alugapro_finance_key'] ? '•••••••• (preservar atual)' : 'Use uma chave aleatória com pelo menos 32 caracteres' }}"></label>
        <div class="integration-card"><div><span class="integration-logo">DP</span><div><strong>Dashpay</strong><small>{{ $settings['has_dashpay_finance_key'] ? 'Chave configurada' : 'Não configurado' }}</small></div></div><span class="status-label {{ $settings['has_dashpay_finance_key'] ? 'active':'pending' }}">{{ $settings['has_dashpay_finance_key'] ? 'Conectado':'Pendente' }}</span></div>
        <label>Chave Bearer do Dashpay<input type="password" name="dashpay_finance_api_key" minlength="32" autocomplete="new-password" placeholder="{{ $settings['has_dashpay_finance_key'] ? '•••••••• (preservar atual)' : 'Use uma chave aleatória com pelo menos 32 caracteres' }}"></label>
        <div class="webhook-box"><span>Endpoint para criar recebimento</span><code>POST {{ url('/api/v1/clientes/{cliente_id}/recebimentos') }}</code><small>Envie a chave no cabeçalho Authorization: Bearer. Um external_id evita lançamentos duplicados.</small></div>
    </article>
    <div class="settings-actions"><p>Campos secretos vazios mantêm o valor já salvo.</p><button class="btn btn-primary btn-lg">Salvar configurações</button></div>
</form>
@endsection
