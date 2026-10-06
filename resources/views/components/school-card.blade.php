@props(['school'])
<article class="school-card">
    <div class="school-card__icon"><i class="fa-solid fa-school-flag" aria-hidden="true"></i></div>
    <div class="school-card__body">
        <span class="badge">{{ $school->dependencia ?: 'Unidad educativa' }}</span>
        <h3>{{ $school->nombre }}</h3>
        <p><i class="fa-solid fa-location-dot" aria-hidden="true"></i> {{ $school->ubicacion?->municipio ?: 'Municipio no registrado' }} · {{ $school->ubicacion?->departamento ?: 'Bolivia' }}</p>
        <p class="school-card__code">Código RUE: {{ $school->codigo_rue ?: 'No registrado' }}</p>
        <a href="{{ route('schools.show', $school) }}">Ver información <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
    </div>
</article>
