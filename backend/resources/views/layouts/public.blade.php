<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#08130d">
    <title>@yield('title', 'Mia Assistente')</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="64x64">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/mia.css') }}">
    @yield('styles')
</head>
<body class="public-body">
    @yield('content')
    <script src="{{ asset('js/mia.js') }}" defer></script>
</body>
</html>
