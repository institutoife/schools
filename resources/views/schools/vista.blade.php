@extends('layouts.app')

@section('title', $school->nombre.' | Colegio en Bolivia - '.config('brand.name'))
@section('meta_description', 'Información de '.$school->nombre.', código RUE '.$school->codigo_rue.', ubicación, niveles, servicios e indicadores educativos disponibles.')

@if($ubicaciones?->latitud && $ubicaciones?->longitud)
@push('styles')<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">@endpush
@endif

@push('head')
<script type="application/ld+json">{!! json_encode([
    '@context' => 'https://schema.org', '@type' => 'School', 'name' => $school->nombre,
    'identifier' => $school->codigo_rue,
    'address' => ['@type' => 'PostalAddress', 'streetAddress' => $school->direccion, 'addressLocality' => $ubicaciones?->municipio, 'addressRegion' => $ubicaciones?->departamento, 'addressCountry' => 'BO'],
    'url' => url()->current(),
], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) !!}</script>
@endpush

@section('content')
<header class="detail-hero"><div class="shell"><a href="{{ route('home') }}"><i class="fa-solid fa-arrow-left"></i> Volver al buscador</a><div><span class="badge">{{ $school->dependencia ?: 'Unidad educativa' }}</span><h1>{{ $school->nombre }}</h1><p><i class="fa-solid fa-location-dot"></i> {{ collect([$ubicaciones?->municipio, $ubicaciones?->provincia, $ubicaciones?->departamento])->filter()->join(' · ') ?: 'Ubicación no registrada' }}</p></div></div></header>

<section class="section"><div class="shell detail-grid">
    <article class="info-card"><x-section-heading align="left" eyebrow="Identificación" title="Información general" /><div class="info-list">
        @foreach([
            ['fa-barcode','Código RUE',$school->codigo_rue], ['fa-location-dot','Dirección',$school->direccion],
            ['fa-user-tie','Dirección institucional',$school->director], ['fa-phone','Teléfonos',$school->telefonos],
            ['fa-graduation-cap','Niveles',$school->niveles], ['fa-clock','Turnos',$school->turnos]
        ] as [$icon,$label,$value])
        <div class="info-row"><i class="fa-solid {{ $icon }}"></i><div><strong>{{ $label }}</strong><span>{{ $value ?: 'No registrado' }}</span></div></div>
        @endforeach
    </div></article>
    <article class="info-card"><x-section-heading align="left" eyebrow="Territorio" title="Ubicación educativa" /><div class="info-list">
        @foreach([
            ['Departamento',$ubicaciones?->departamento], ['Provincia',$ubicaciones?->provincia], ['Municipio',$ubicaciones?->municipio],
            ['Distrito educativo',$ubicaciones?->distrito], ['Distrito municipal',$ubicaciones?->distrito_municipal], ['Área',$ubicaciones?->area]
        ] as [$label,$value])
        <div class="info-row"><i class="fa-solid fa-map-pin"></i><div><strong>{{ $label }}</strong><span>{{ $value ?: 'No registrado' }}</span></div></div>
        @endforeach
    </div></article>

    @if($ubicaciones?->latitud && $ubicaciones?->longitud)
    <article class="info-card span-2"><x-section-heading align="left" eyebrow="Geolocalización" title="Ubicación en el mapa" description="La posición corresponde a las coordenadas registradas para la unidad educativa." /><div id="school-map" role="region" aria-label="Mapa de ubicación de {{ $school->nombre }}"></div></article>
    @endif

    <article class="info-card"><x-section-heading align="left" eyebrow="Infraestructura" title="Ambientes disponibles" /><div class="metric-grid">
        @forelse(['aulas'=>'Aulas','laboratorios'=>'Laboratorios','bibliotecas'=>'Bibliotecas','computacion'=>'Computación','canchas'=>'Canchas','gimnasios'=>'Gimnasios','coliseos'=>'Coliseos','talleres'=>'Talleres'] as $field=>$label)
            @if($ambientes?->{$field} !== null)<div class="metric"><strong>{{ $ambientes->{$field} }}</strong><span>{{ $label }}</span></div>@endif
        @empty<div class="empty-state">Sin información de ambientes.</div>@endforelse
    </div></article>
    <article class="info-card"><x-section-heading align="left" eyebrow="Servicios" title="Servicios básicos" /><div class="info-list">
        @foreach(['agua'=>'Agua','electricidad'=>'Electricidad','banos'=>'Baños','internet'=>'Internet'] as $field=>$label)
        <div class="info-row"><i class="fa-solid {{ $servicios?->{$field} ? 'fa-circle-check' : 'fa-circle-minus' }}"></i><div><strong>{{ $label }}</strong><span>{{ $servicios?->{$field} === null ? 'No registrado' : ($servicios->{$field} ? 'Disponible' : 'No disponible') }}</span></div></div>
        @endforeach
    </div></article>

    <article class="info-card span-2"><x-section-heading align="left" eyebrow="Datos registrados" title="Indicadores educativos" /><div class="metric-grid">
        @forelse($estadisticas->sortByDesc('anio')->take(12) as $stat)<div class="metric"><strong>{{ number_format((int)$stat->total) }}</strong><span>{{ ucfirst($stat->categoria ?: 'Indicador') }} · {{ $stat->anio ?: 'Sin año' }}</span></div>@empty<div class="empty-state">No existen indicadores estadísticos disponibles para este colegio.</div>@endforelse
    </div></article>
</div></section>
@endsection

@if($ubicaciones?->latitud && $ubicaciones?->longitud)
@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const schoolCoordinates = [@json((float)$ubicaciones->latitud), @json((float)$ubicaciones->longitud)];
const schoolMap = L.map('school-map', {scrollWheelZoom: false}).setView(schoolCoordinates, 15);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom: 19, attribution: '&copy; OpenStreetMap contributors'}).addTo(schoolMap);
L.marker(schoolCoordinates).addTo(schoolMap).bindPopup(@json($school->nombre)).openPopup();
</script>
@endpush
@endif
