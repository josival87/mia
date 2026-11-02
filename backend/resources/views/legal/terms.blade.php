@extends('layouts.public')
@section('title', 'Termos de Uso — Mia Assistente')
@section('content')
{{-- RASCUNHO: revisar com advogado. Substitua os campos entre colchetes antes de publicar. --}}
<main class="legal-page" style="max-width:760px;margin:0 auto;padding:32px 16px;line-height:1.6">
    <a href="{{ route('landing') }}">← Voltar</a>
    <h1>Termos de Uso</h1>
    <p><small>Versão {{ \App\Models\Consent::CURRENT_VERSION }}</small></p>

    <h2>1. O que é a Mia</h2>
    <p>A Mia Assistente é uma ferramenta de organização financeira e de atividades, operada por [NOME COMPLETO DO RESPONSÁVEL]. Ela registra lançamentos que você envia pelo site ou pelo Telegram e calcula resumos a partir deles.</p>

    <h2>2. O que a Mia não é</h2>
    <p>A Mia não presta consultoria financeira, contábil, jurídica ou de investimentos. Estimativas de impostos, custos e lucros são aproximações para organização pessoal e não substituem um contador ou outro profissional habilitado.</p>

    <h2>3. Sua conta</h2>
    <ul>
        <li>Você é responsável pela veracidade dos dados que informa e pela guarda da sua senha.</li>
        <li>Mensagens enviadas ao bot a partir da sua conta do Telegram vinculada são tratadas como enviadas por você.</li>
        <li>Lançamentos interpretados por inteligência artificial podem conter erros; confira os valores registrados.</li>
    </ul>

    <h2>4. Uso aceitável</h2>
    <p>É proibido usar a Mia para fins ilícitos, tentar acessar dados de outros usuários, sobrecarregar o serviço ou enviar senhas, códigos de segurança e dados de cartão. O serviço tem limites diários de uso que podem mudar.</p>

    <h2>5. Disponibilidade</h2>
    <p>A Mia é oferecida gratuitamente, sem garantia de disponibilidade contínua. Recomendamos exportar seus dados periodicamente.</p>

    <h2>6. Encerramento</h2>
    <p>Você pode pedir a exclusão da sua conta a qualquer momento pelo e-mail [E-MAIL DE CONTATO]. Contas usadas em violação destes termos podem ser suspensas.</p>

    <h2>7. Alterações e contato</h2>
    <p>Mudanças relevantes serão avisadas no site ou pelo bot. Dúvidas: [E-MAIL DE CONTATO].</p>
</main>
@endsection
