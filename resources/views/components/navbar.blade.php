<header class="site-header" data-site-header>
    <div class="shell nav-shell">
        <a href="{{ route('home') }}" aria-label="Ir al inicio"><x-brand-logo /></a>
        <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="main-navigation" aria-label="Abrir menú"><i class="fa-solid fa-bars"></i></button>
        <nav id="main-navigation" class="main-nav" aria-label="Navegación principal">
            <a href="{{ route('home') }}">Inicio</a>
            <a href="{{ route('home') }}#explorar">Departamentos</a>
            <a href="{{ route('home') }}#herramientas">Análisis</a>
            <a href="{{ route('home') }}#servicios">Servicios</a>
            @auth
                <a href="{{ url('/rankings') }}">Reportes</a>
            @endauth
            <a class="nav-login" href="{{ url('/admin') }}"><i class="fa-regular fa-user"></i> Panel</a>
        </nav>
    </div>
</header>
