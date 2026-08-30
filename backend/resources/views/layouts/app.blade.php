<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#07100b">
    <title>@yield('title', 'Mia Assistente')</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="64x64">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/mia.css') }}">
</head>
<body class="app-body">
@php
    $isAdmin = auth()->user()->isAdmin() && request()->routeIs('admin.*');
    $clientNav = [
        ['route' => 'dashboard', 'match' => 'dashboard', 'label' => 'Início', 'icon' => 'grid'],
        ['route' => 'finance.index', 'match' => 'finance.*', 'label' => 'Financeiro', 'icon' => 'wallet'],
        ['route' => 'tasks.index', 'match' => 'tasks.*', 'label' => 'Atividades', 'icon' => 'tasks'],
        ['route' => 'categories.index', 'match' => 'categories.*', 'label' => 'Categorias', 'icon' => 'tag'],
        ['route' => 'telegram.connection', 'match' => 'telegram.*', 'label' => 'Telegram', 'icon' => 'bot'],
    ];
    $adminNav = [
        ['route' => 'admin.dashboard', 'match' => 'admin.dashboard', 'label' => 'Visão geral', 'icon' => 'grid'],
        ['route' => 'admin.users', 'match' => 'admin.users*', 'label' => 'Usuários', 'icon' => 'shield'],
        ['route' => 'admin.clients', 'match' => 'admin.clients*', 'label' => 'Clientes', 'icon' => 'users'],
        ['route' => 'admin.categories', 'match' => 'admin.categories*', 'label' => 'Categorias', 'icon' => 'tag'],
        ['route' => 'admin.settings', 'match' => 'admin.settings*', 'label' => 'Configurações', 'icon' => 'settings'],
    ];
    $nav = $isAdmin ? $adminNav : $clientNav;
    $unreadAlertCount = $isAdmin ? 0 : auth()->user()->unreadNotifications()->count();
@endphp
<svg aria-hidden="true" class="svg-sprite">
    <symbol id="i-grid" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/></symbol>
    <symbol id="i-wallet" viewBox="0 0 24 24"><path d="M4 7h15a2 2 0 0 1 2 2v10H4a2 2 0 0 1-2-2V6a3 3 0 0 1 3-3h13v4"/><path d="M16 12h5v4h-5a2 2 0 0 1 0-4Z"/></symbol>
    <symbol id="i-tasks" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="3"/><path d="m7 10 2 2 4-4M14 11h3M7 16h10"/></symbol>
    <symbol id="i-tag" viewBox="0 0 24 24"><path d="M20 13 11 22 2 13V2h11l7 7a3 3 0 0 1 0 4Z"/><circle cx="7" cy="7" r="1.5"/></symbol>
    <symbol id="i-users" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></symbol>
    <symbol id="i-shield" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/></symbol>
    <symbol id="i-settings" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-2.83 2.83-.06-.06A1.7 1.7 0 0 0 15 19.4a1.7 1.7 0 0 0-1 .6 1.7 1.7 0 0 0-.4 1.1V21h-4v-.09A1.7 1.7 0 0 0 8.6 19.4a1.7 1.7 0 0 0-1.88.34l-.06.06-2.83-2.83.06-.06A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-1.5-1H3v-4h.09A1.7 1.7 0 0 0 4.6 9a1.7 1.7 0 0 0-.34-1.88l-.06-.06 2.83-2.83.06.06A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1-1.5V3h4v.09A1.7 1.7 0 0 0 15 4.6a1.7 1.7 0 0 0 1.88-.34l.06-.06 2.83 2.83-.06.06A1.7 1.7 0 0 0 19.4 9c.13.38.35.72.6 1h1v4h-1a1.7 1.7 0 0 0-.6 1Z"/></symbol>
    <symbol id="i-logout" viewBox="0 0 24 24"><path d="M10 17l5-5-5-5M15 12H3M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/></symbol>
    <symbol id="i-bot" viewBox="0 0 24 24"><rect x="3" y="7" width="18" height="13" rx="4"/><path d="M12 3v4M8 12h.01M16 12h.01M8 16h8"/></symbol>
    <symbol id="i-bell" viewBox="0 0 24 24"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/></symbol>
</svg>
<div class="app-shell">
    <aside class="sidebar">
        <a class="brand brand-light" href="{{ $isAdmin ? route('admin.dashboard') : route('dashboard') }}"><span class="brand-mark">M</span><span>Mia<small>{{ $isAdmin ? 'Admin' : 'Assistente' }}</small></span></a>
        <nav class="side-nav">
            <p class="nav-eyebrow">{{ $isAdmin ? 'Administração' : 'Seu espaço' }}</p>
            @foreach($nav as $item)
                <a href="{{ route($item['route']) }}" class="{{ request()->routeIs($item['match']) ? 'active' : '' }}"><svg><use href="#i-{{ $item['icon'] }}"/></svg><span>{{ $item['label'] }}</span></a>
            @endforeach
        </nav>
        <div class="sidebar-bottom">
            @if(!$isAdmin)
            <div class="bot-tip"><svg><use href="#i-bot"/></svg><div><strong>Fale com a Mia</strong><span>Envie texto ou áudio pelo Telegram</span></div></div>
            @endif
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="logout-button"><svg><use href="#i-logout"/></svg>Sair</button></form>
        </div>
    </aside>
    <main class="workspace">
        <header class="topbar">
            <button class="icon-button menu-toggle" type="button" aria-label="Abrir menu" data-menu-toggle><span></span><span></span><span></span></button>
            <div class="mobile-brand"><span class="brand-mark">M</span>Mia</div>
            @if(!$isAdmin)<a class="notification-bell {{ $unreadAlertCount ? 'has-alerts' : '' }}" href="{{ route('alerts.index') }}" aria-label="Ver alertas"><svg><use href="#i-bell"/></svg>@if($unreadAlertCount)<b>{{ min(99, $unreadAlertCount) }}</b>@endif</a>@endif
            <div class="topbar-user"><div><strong>{{ auth()->user()->name }}</strong><span>{{ $isAdmin ? 'Administrador' : 'Conta pessoal' }}</span></div><span class="avatar">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span></div>
        </header>
        <div class="page-content">
            @if(session('success'))<div class="alert success" data-alert>{{ session('success') }}</div>@endif
            @if(session('warning'))<div class="alert warning" data-alert>{{ session('warning') }}</div>@endif
            @if($errors->any())<div class="alert error" data-alert><strong>Revise os dados:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            @yield('content')
        </div>
    </main>
    <nav class="bottom-nav">
        @foreach(array_slice($nav, 0, 5) as $item)
            <a href="{{ route($item['route']) }}" class="{{ request()->routeIs($item['match']) ? 'active' : '' }}"><svg><use href="#i-{{ $item['icon'] }}"/></svg><span>{{ $item['label'] }}</span></a>
        @endforeach
    </nav>
</div>
<script src="{{ asset('js/mia.js') }}" defer></script>
@stack('scripts')
</body>
</html>
