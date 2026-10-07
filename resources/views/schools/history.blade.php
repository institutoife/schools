@push('styles')<link rel="stylesheet" href="{{ asset('css/education-history.css') }}">@endpush
<div class="edu-history school-history">
    <div class="eh-heading"><div><span class="eh-eyebrow">La historia del colegio</span><h2>Así cambian sus cifras, año a año</h2><p>Matrícula, resultados y abandono. Cada tarjeta corresponde a una gestión.</p></div><a class="eh-button" href="{{ route('aplazados.historia') }}">Ver historia por departamento →</a></div>
    <p class="eh-note">Desliza las tarjetas horizontalmente para recorrer todas las gestiones disponibles.</p>
    <div class="eh-timeline" aria-label="Línea de tiempo de estadísticas del colegio">
        @foreach($history as $row)
        <article class="eh-year-card">
            <span class="eh-year">{{ $row['year'] }}</span>
            @foreach(['matricula'=>'Estudiantes matriculados','promovidos'=>'Promovidos','reprobados'=>'Aplazados','abandono'=>'Retirados por abandono'] as $key=>$label)
            <div class="eh-year-value {{ $key === 'reprobados' ? 'eh-highlight' : '' }}"><span>{{ $label }}</span><strong>{{ $row[$key] !== null ? number_format($row[$key],0,',','.') : 'Sin datos' }}</strong></div>
            @endforeach
            <div class="eh-year-rate"><strong>{{ $row['tasa'] !== null ? number_format($row['tasa'],1,',','.').' %' : 'Sin datos' }}</strong><span>Tasa de aplazados / matrícula</span></div>
            <p class="eh-note">Aplazados: {{ $row['hombres'] !== null ? number_format($row['hombres'],0,',','.') : 'Sin datos' }} hombres · {{ $row['mujeres'] !== null ? number_format($row['mujeres'],0,',','.') : 'Sin datos' }} mujeres</p>
        </article>
        @endforeach
    </div>
    <div class="eh-panel"><h3>La evolución en una mirada</h3><div class="eh-school-chart" data-school-history>@foreach($history as $row)<div class="eh-school-column"><div class="eh-school-track"><div class="eh-school-bar" style="--bar-height:{{ $row['matricula'] !== null && $row['matricula'] > 0 ? max(2,100*$row['matricula']/max(1,collect($history)->max('matricula'))) : 0 }}%"><span>{{ $row['matricula'] !== null ? number_format($row['matricula'],0,',','.') : '—' }}</span></div></div><strong>{{ $row['year'] }}</strong><small>Matrícula</small></div>@endforeach</div></div>
    <p class="eh-note">Fuente: estadísticas registradas en el sistema. “Sin datos” indica que no hay un valor disponible; no equivale a cero. La tasa usa aplazados y matrícula del mismo año. La importación desde el Ministerio actualiza esta vista.</p>
</div>
