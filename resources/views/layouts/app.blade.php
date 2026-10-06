<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('brand.platform_name').' | '.config('brand.name'))</title>
    <meta name="description" content="@yield('meta_description', 'Busca y explora información de colegios, municipios, distritos y departamentos de Bolivia con IFE Educabol.')">
    <link rel="canonical" href="@yield('canonical', url()->current())">
    <meta property="og:type" content="website"><meta property="og:locale" content="es_BO">
    <meta property="og:title" content="@yield('title', config('brand.platform_name').' | '.config('brand.name'))">
    <meta property="og:description" content="@yield('meta_description', 'Directorio educativo de colegios de Bolivia.')">
    <meta property="og:url" content="@yield('canonical', url()->current())">
    <meta property="og:image" content="{{ asset(config('brand.logo')) }}">
    <meta name="twitter:card" content="summary_large_image">
    <link rel="icon" href="{{ asset(config('brand.icon')) }}" type="image/png">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
    @stack('head')
</head>
<body>
    <a class="skip-link" href="#main-content">Saltar al contenido</a>
    <x-navbar />
    <main id="main-content">@yield('content')</main>
    <x-footer />
    <x-whatsapp-button class="whatsapp-float" label="" />
    @stack('scripts')
</body>
</html>
