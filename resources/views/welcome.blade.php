@extends('layouts.app')

@section('title', 'Colegios de Bolivia | Directorio educativo de IFE Educabol')
@section('meta_description', 'Busca colegios de Bolivia por nombre, código RUE, departamento, municipio o distrito. Explora datos educativos reales en IFE Educabol.')

@push('head')
<script type="application/ld+json">{!! json_encode([
    '@context' => 'https://schema.org', '@type' => 'WebSite',
    'name' => config('brand.platform_name'), 'url' => route('home'),
    'publisher' => ['@type' => 'EducationalOrganization', 'name' => config('brand.legal_name'), 'url' => 'https://'.config('brand.domain')],
    'potentialAction' => ['@type' => 'SearchAction', 'target' => route('home').'?search={search_term_string}', 'query-input' => 'required name=search_term_string'],
], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) !!}</script>
@endpush

@section('content')
<section class="hero" id="buscar">
    <div class="shell hero-grid">
        <div class="hero-copy">
            <span class="eyebrow"><i class="fa-solid fa-graduation-cap"></i> {{ config('brand.name') }}</span>
            <h1>Encuentra y conoce los <span>colegios de Bolivia</span></h1>
            <p>Consulta información educativa por colegio, código RUE, departamento, municipio o distrito desde un directorio nacional claro y accesible.</p>
            <form class="search-panel" method="GET" action="{{ route('home') }}" role="search" aria-label="Buscar colegios de Bolivia">
                <div class="search-fields">
                    <div class="field"><label for="filter">Buscar por</label><select id="filter" name="filter"><option value="nombre" @selected($filter === 'nombre')>Todo</option><option value="codigo" @selected($filter === 'codigo')>Código RUE</option><option value="departamento" @selected($filter === 'departamento')>Departamento</option><option value="municipio" @selected($filter === 'municipio')>Municipio</option><option value="distrito" @selected($filter === 'distrito')>Distrito</option><option value="zona" @selected($filter === 'zona')>Zona</option></select></div>
                    <div class="field"><label for="search">Nombre o ubicación</label><input id="search" name="search" value="{{ $search }}" placeholder="Ej.: Unidad Educativa, La Paz o 8073…" autocomplete="off"></div>
                    <button class="button" type="submit"><i class="fa-solid fa-magnifying-glass"></i> Buscar</button>
                </div>
            </form>
        </div>
        <div class="hero-brand"><img src="{{ asset(config('brand.logo')) }}" alt="{{ config('brand.name') }}, Instituto de Formación Educabol" width="520" height="420"></div>
    </div>
</section>

@if($search !== '')
<section class="section section--white" aria-labelledby="results-title">
    <div class="shell">
        <div class="results-bar"><div><span class="eyebrow">Resultados</span><h2 id="results-title">Colegios encontrados para “{{ $search }}”</h2></div><span>{{ $schools->total() }} coincidencias</span></div>
        @if($schools->count())<div class="school-grid">@foreach($schools as $school)<x-school-card :school="$school" />@endforeach</div><div class="pagination-wrap">{{ $schools->links() }}</div>@else<div class="empty-state"><i class="fa-regular fa-folder-open"></i><p>No encontramos colegios con esos criterios. Prueba con otra palabra o ubicación.</p></div>@endif
    </div>
</section>
@endif

<section class="section" aria-labelledby="stats-title"><div class="shell"><x-section-heading eyebrow="Datos disponibles" title="Bolivia educativa en cifras" description="Indicadores calculados únicamente con la información registrada en la plataforma." /><div class="stats-grid">
    <article class="stat-card"><i class="fa-solid fa-school"></i><strong>{{ number_format($kpis['total'] ?? 0) }}</strong><span>Colegios registrados</span></article>
    <article class="stat-card"><i class="fa-solid fa-map"></i><strong>{{ $locationStats['departments'] }}</strong><span>Departamentos</span></article>
    <article class="stat-card"><i class="fa-solid fa-city"></i><strong>{{ number_format($locationStats['municipalities']) }}</strong><span>Municipios</span></article>
    <article class="stat-card"><i class="fa-solid fa-map-location-dot"></i><strong>{{ number_format($locationStats['districts']) }}</strong><span>Distritos</span></article>
</div></div></section>

<section class="section section--white" id="explorar"><div class="shell"><x-section-heading eyebrow="Explorar Bolivia" title="Nueve departamentos, una sola plataforma" description="Selecciona un departamento para consultar sus unidades educativas registradas." /><div class="department-grid">
@foreach($departments as $department)<article class="department-card"><h3>{{ $department->departamento }}</h3><p>{{ number_format($department->schools_count) }} colegios</p><a href="{{ route('home', ['filter'=>'departamento','search'=>$department->departamento]) }}">Explorar <i class="fa-solid fa-arrow-right"></i></a></article>@endforeach
</div></div></section>

<section class="section"><div class="shell"><x-section-heading eyebrow="Directorio" title="Colegios para comenzar a explorar" description="Una selección de instituciones disponibles en el directorio." /><div class="school-grid">@foreach($featuredSchools as $school)<x-school-card :school="$school" />@endforeach</div></div></section>

<section class="section section--white"><div class="shell"><x-section-heading eyebrow="Una herramienta para todos" title="Información educativa fácil de consultar" /><div class="benefit-grid">
    <article class="benefit-card"><i class="fa-solid fa-magnifying-glass-location"></i><h3>Búsqueda centralizada</h3><p>Ubica instituciones por nombre, RUE o división territorial.</p></article>
    <article class="benefit-card"><i class="fa-solid fa-chart-column"></i><h3>Datos comprensibles</h3><p>Consulta indicadores disponibles sin perderte en tablas extensas.</p></article>
    <article class="benefit-card"><i class="fa-solid fa-mobile-screen-button"></i><h3>Acceso desde cualquier lugar</h3><p>Diseñada para celular, tableta y computadora, con navegación accesible.</p></article>
</div></div></section>

@php
    $analysisTools = [
        ['buscar', 'Directorio', 'Buscar un colegio', 'Encuentra cualquier institución por nombre, código RUE, distrito o ubicación.', 'fa-magnifying-glass', 'Ir al buscador'],
        ['colegios-reprobados', 'Reprobación', 'Colegios con más reprobados', 'Lista de colegios con mayor cantidad de estudiantes reprobados, por año.', 'fa-chart-line', 'Ver colegios'],
        ['ranking-colegio', 'Rankings', 'Ranking tu colegio', 'Consulta el ranking de tu colegio a nivel Nacional, Departamental, Provincial, Municipal y Distrital.', 'fa-school', 'Ver ranking'],
        ['ranking-matricula', 'Matrícula', 'Rankings de matrícula', 'Colegios más y menos poblados, crecimiento anual y comparativas departamentales.', 'fa-users', 'Explorar'],
        ['abandono-escolar', 'Abandono', 'Abandono escolar', 'Departamentos críticos, comparación urbana/rural y tendencias.', 'fa-user-times', 'Ver detalle'],
        ['infraestructura', 'Infraestructura', 'Infraestructura', 'Mejores y peores condiciones de ambientes, servicios y equipamiento.', 'fa-building', 'Ver ranking'],
        ['mapa-interactivo', 'Mapas', 'Mapa interactivo', 'Visualiza los colegios geolocalizados con filtros por indicadores.', 'fa-map-marked-alt', 'Abrir mapa'],
        ['aplazados-municipio-resumen', 'Reprobación', 'Colegios más aplazados por municipio', 'Consulta el colegio con mayor reprobación en cada municipio y explora el detalle de cualquier municipio.', 'fa-city', 'Ver municipios'],
        ['mapa-calor-aplazados', 'Mapas', 'Mapa de calor de aplazados', 'Visualiza los colegios de Bolivia por intensidad de aplazados, con filtros por año y ubicación.', 'fa-fire', 'Ver mapa de calor'],
        ['dependencia', 'Distribución', 'Distribución por dependencia', 'Fiscal / Privada / Convenio', 'fa-scale-balanced', 'Ver distribución'],
        ['area', 'Distribución', 'Distribución por área', 'Rural vs Urbana', 'fa-map', 'Ver áreas'],
        ['genero', 'Resultados', 'Resultados por género', 'Promoción / Reprobación / Abandono', 'fa-venus-mars', 'Ver resultados'],
        ['colegios-departamento', 'Cobertura', 'Número de colegios por departamento', 'Cantidad total de colegios por región.', 'fa-layer-group', 'Ver números'],
        ['evolucion-matricula', 'Matrícula', 'Evolución de matrícula por departamento', 'Crecimiento y variación anual.', 'fa-chart-area', 'Ver evolución'],
        ['tasa-promocion', 'Promoción', 'Tasa de promoción por departamento', 'Porcentaje de estudiantes promovidos.', 'fa-arrow-trend-up', 'Ver tasa'],
        ['tasa-reprobacion', 'Reprobación', 'Tasa de reprobación por departamento', 'Porcentaje de estudiantes reprobados.', 'fa-arrow-trend-down', 'Ver tasa'],
        ['tasa-abandono', 'Abandono', 'Tasa de abandono por departamento', 'Porcentaje de abandono escolar.', 'fa-user-slash', 'Ver tasa'],
        ['aplazados-nacional', 'Aplazados', 'Colegio con más aplazados (nacional)', 'El colegio con mayor cantidad de reprobados en Bolivia.', 'fa-trophy', 'Ver colegio'],
        ['aplazados-departamento', 'Aplazados', 'Colegio con más aplazados por departamento', 'El colegio con mayor reprobación en cada departamento.', 'fa-map', 'Ver colegios'],
        ['aplazados-provincia', 'Aplazados', 'Colegio con más aplazados por provincia', 'El colegio con mayor reprobación en cada provincia.', 'fa-mountain-sun', 'Ver colegios'],
        ['aplazados-municipio', 'Aplazados', 'Colegio con más aplazados por municipio', 'El colegio con mayor reprobación en cada municipio.', 'fa-city', 'Ver colegios'],
        ['aplazados-distrito', 'Aplazados', 'Colegio con más aplazados por distrito', 'El colegio con mayor reprobación en cada distrito.', 'fa-map-location-dot', 'Ver colegios'],
        ['aplazados-distrito-municipal', 'Aplazados', 'Colegios más aplazados por distrito municipal (SCZ)', 'Ranking por distrito municipal usando la delimitación geográfica disponible.', 'fa-draw-polygon', 'Ver distritos'],
    ];
@endphp
<section class="section tools-section" id="herramientas" aria-labelledby="tools-title">
    <div class="shell">
        <x-section-heading
            eyebrow="Herramientas educativas"
            title="Explora todos los análisis disponibles"
            description="Conservamos cada buscador, ranking, mapa e informe de la plataforma y los organizamos en un catálogo más claro."
        />
        <div class="tools-grid">
            @foreach($analysisTools as [$key, $category, $title, $description, $icon, $action])
                @php $locked = !Auth::check(); @endphp
                <a class="tool-card {{ $locked ? 'tool-card--locked' : '' }}" href="{{ route('reports.access', $key) }}" @if($locked) aria-label="{{ $title }}. Solo lectura; requiere iniciar sesión" @endif>
                    <span class="tool-card__category">{{ $category }}</span>
                    @if($locked)
                        <span class="tool-card__watermark" aria-hidden="true">Solo lectura</span>
                        <span class="tool-card__lock"><i class="fa-solid fa-lock" aria-hidden="true"></i></span>
                    @endif
                    <span class="tool-card__icon"><i class="fa-solid {{ $icon }}" aria-hidden="true"></i></span>
                    <h3>{{ $title }}</h3>
                    <p>{{ $description }}</p>
                    <span class="tool-card__action">{{ $locked ? 'Iniciar sesión para consultar' : $action }} <i class="fa-solid {{ $locked ? 'fa-lock' : 'fa-arrow-right' }}" aria-hidden="true"></i></span>
                </a>
            @endforeach
        </div>
        <div class="territorial-note">
            <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
            <p><strong>Datos responsables:</strong> los análisis utilizan exclusivamente las gestiones e indicadores registrados; la plataforma no inventa valores faltantes.</p>
        </div>
    </div>
</section>

<section class="section" id="servicios"><div class="shell"><x-section-heading eyebrow="{{ config('brand.name') }}" title="Formación y tecnología para aprender mejor" /><div class="service-grid">
    <article class="service-card"><i class="fa-solid fa-laptop-code"></i><h3>Computación</h3><p>Competencias digitales para estudiantes y docentes.</p></article>
    <article class="service-card"><i class="fa-solid fa-robot"></i><h3>Robótica</h3><p>Aprendizaje práctico, creatividad y pensamiento lógico.</p></article>
    <article class="service-card"><i class="fa-solid fa-book-open-reader"></i><h3>Apoyo educativo</h3><p>Acompañamiento para fortalecer el aprendizaje escolar.</p></article>
    <article class="service-card"><i class="fa-solid fa-code"></i><h3>Desarrollo web</h3><p>Herramientas digitales con propósito educativo.</p></article>
</div></div></section>

<section class="section section--white"><div class="shell"><article class="author-card"><img src="{{ asset(config('brand.author_photo')) }}" alt="David Flores, creador de herramientas educativas en {{ config('brand.name') }}" width="300" height="300"><div><span class="eyebrow">Acerca del autor</span><h2>David Flores</h2><p>Creador de herramientas educativas y representante de {{ config('brand.name') }}. Trabaja en soluciones que acercan información, formación y tecnología a la comunidad educativa boliviana.</p><x-social-links :labels="true" /></div></article></div></section>
@endsection
