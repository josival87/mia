@extends('layouts.public')
@section('title', 'Política de Privacidade — Mia Assistente')
@section('content')
{{-- RASCUNHO: revisar com advogado. Substitua os campos entre colchetes antes de publicar. --}}
<main class="legal-page" style="max-width:760px;margin:0 auto;padding:32px 16px;line-height:1.6">
    <a href="{{ route('landing') }}">← Voltar</a>
    <h1>Política de Privacidade</h1>
    <p><small>Versão {{ \App\Models\Consent::CURRENT_VERSION }}</small></p>

    <h2>1. Controlador</h2>
    <p>[NOME COMPLETO DO RESPONSÁVEL], pessoa física, é o controlador dos dados tratados pela Mia. Canal do titular: [E-MAIL DE PRIVACIDADE].</p>

    <h2>2. Dados que tratamos</h2>
    <ul>
        <li>Cadastro: nome, e-mail e identificador da sua conta do Telegram. Não coletamos CPF.</li>
        <li>Uso: lançamentos financeiros, atividades, categorias, metas e mensagens enviadas ao bot.</li>
        <li>Áudios enviados ao bot são processados para transcrição e não são armazenados.</li>
        <li>Alertas bancários enviados pela automação do iPhone: guardamos só um código de verificação (hash), não o texto.</li>
        <li>Registros técnicos: data do aceite destes termos e um hash do endereço IP.</li>
    </ul>

    <h2>3. Para que usamos</h2>
    <p>Para prestar o serviço que você contratou (base legal: execução de contrato, art. 7º, V, da LGPD): registrar lançamentos, calcular resumos e enviar lembretes que você configurou. Não vendemos dados e não os usamos para publicidade.</p>

    <h2>4. Inteligência artificial e compartilhamento</h2>
    <p>Para interpretar mensagens livres, o texto pode ser processado por um modelo executado no próprio servidor da Mia ou enviado a provedores de IA que atuam como operadores e não usam o conteúdo para treinar modelos: Groq Inc. e Cerebras Systems Inc. (Estados Unidos) e, quando habilitados, Google (Gemini, nível pago) e OpenAI. Isso configura transferência internacional de dados (art. 33 da LGPD). Mensagens simples são interpretadas no próprio servidor, sem envio a terceiros.</p>

    <h2>5. Retenção</h2>
    <p>Mantemos seus dados enquanto a conta existir. Cadastros não concluídos são apagados após a expiração. Ao excluir a conta, apagamos seus dados em até 30 dias, salvo obrigação legal.</p>

    <h2>6. Segurança</h2>
    <p>Usamos conexão HTTPS, senhas com hash, chaves de integração cifradas, isolamento de dados por usuário e backups cifrados.</p>

    <h2>7. Seus direitos</h2>
    <p>Você pode pedir confirmação, acesso, correção, portabilidade, anonimização ou exclusão dos seus dados, e revogar o consentimento, pelo e-mail [E-MAIL DE PRIVACIDADE] (art. 18 da LGPD).</p>
</main>
@endsection
