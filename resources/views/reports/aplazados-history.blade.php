@extends('layouts.app')
@section('title', 'Historia de aplazados por departamento | '.config('brand.name'))
@push('styles')<link rel="stylesheet" href="{{ asset('css/education-history.css').'?v='.filemtime(public_path('css/education-history.css')) }}">@endpush
@section('content')
@php
    $reportYears = array_column(reset($departmentHistory), 'year');
    $projectionYear = end($reportYears);
    $lastActualYear = $projectionYear - 1;
@endphp
<div class="edu-history eh-report" id="education-report">
    <header class="eh-report-hero"><div class="eh-wrap"><a href="{{ route('home') }}" class="eh-back">← Volver al inicio</a><span class="eh-eyebrow">Bolivia · Historia educativa</span><h1>Una historia detrás<br>de cada cifra.</h1><p>Aplazados por departamento: 2021–{{ $lastActualYear }} y un escenario posible para {{ $projectionYear }}.</p><div class="eh-hero-tags"><span>9 departamentos</span><span>{{ count($reportYears)-1 }} años de historia</span><span>{{ $projectionYear }} · estimación</span></div></div></header>
    <div class="eh-wrap">
        <section class="eh-controls" aria-label="Controles del informe">
            <label>Departamento<select id="eh-department">@foreach(array_keys($departmentHistory) as $name)<option value="{{ $name }}" @selected($name==='SANTA CRUZ')>{{ $name === 'POTOSI' ? 'POTOSÍ' : $name }}</option>@endforeach</select></label>
            <label>Gráfico general<select id="eh-measure"><option value="total">Cantidad de aplazados</option><option value="tasa">Tasa sobre matrícula (%)</option></select></label>
            <label>Escala vertical<select id="eh-scale"><option value="detail">Detalle de la variación</option><option value="zero">Desde cero</option></select></label>
            <button class="eh-button eh-primary" id="eh-play" type="button">▶ Reproducir años</button><button class="eh-button" id="eh-present" type="button">Modo video</button>
        </section>
        <nav class="eh-year-tabs" aria-label="Seleccionar gestión">@foreach($reportYears as $year)<button type="button" data-year="{{ $year }}" aria-pressed="false">{{ $year }}@if($year===$projectionYear)<small>Proyección</small>@endif</button>@endforeach</nav>
        <section class="eh-focus" id="eh-summary" aria-live="polite">
            <div class="eh-focus-top"><div><span class="eh-eyebrow" id="eh-status">Datos registrados</span><h2><span id="eh-name">Santa Cruz</span> <span class="eh-focus-year" id="eh-year">{{ $lastActualYear }}</span></h2></div><span class="eh-source">Fuente: registros del sistema / Ministerio de Educación</span></div>
            <div class="eh-kpis"><article class="eh-kpi eh-main-kpi"><span>Aplazados en total</span><strong id="eh-total">—</strong><p id="eh-change">Selecciona un año</p></article><article class="eh-kpi"><span>Tasa de aplazados</span><strong id="eh-rate">—</strong><p>Por cada 100 estudiantes matriculados con datos comparables</p></article><article class="eh-kpi"><span>Hombres aplazados</span><strong id="eh-men">—</strong><p id="eh-men-share">—</p></article><article class="eh-kpi"><span>Mujeres aplazadas</span><strong id="eh-women">—</strong><p id="eh-women-share">—</p></article></div>
            <div class="eh-split" aria-label="Distribución de aplazados por sexo"><div id="eh-men-bar"></div><div id="eh-women-bar"></div></div><p id="eh-sex-note" class="eh-note"></p>
            <p id="eh-coverage" class="eh-coverage"></p>
        </section>
        <section class="eh-panel">
            <div class="eh-heading"><div><span class="eh-eyebrow">2021 → {{ $projectionYear }}</span><h2 id="eh-main-title">HISTORIAL APLAZADOS SANTA CRUZ</h2></div><x-history-replay id="eh-replay-main" label="Repetir animación del historial de aplazados" /></div>
            <div class="eh-legend"><span><i></i> Historia registrada</span><span><i class="eh-estimate"></i> Proyección · línea discontinua</span></div>
            <div id="eh-chart" class="eh-chart" role="img" aria-label="Gráfico de evolución de aplazados"></div>
            <p id="eh-main-scale-note" class="eh-note"></p><p class="eh-note">El trazado recorre los años de menor a mayor. Los huecos indican años sin datos. Los valores exactos aparecen sobre cada punto.</p>
        </section>
        <section class="eh-panel">
            <div class="eh-heading"><div><span class="eh-eyebrow">Dos historias en el mismo eje</span><h2 id="eh-sex-title">APLAZADOS HOMBRES VS. MUJERES — SANTA CRUZ</h2></div><div class="eh-chart-actions"><label>Comparar en <select id="eh-sex-measure"><option value="count">Cantidad de aplazados</option><option value="share">Porcentaje de los aplazados</option></select></label><x-history-replay id="eh-replay-sex" label="Repetir animación de hombres y mujeres" /></div></div>
            <div class="eh-legend"><span><i class="eh-female"></i> Mujeres</span><span><i class="eh-male"></i> Hombres</span><span>Línea discontinua: proyección</span></div>
            <div id="eh-sex-chart" class="eh-chart" role="img" aria-label="Evolución de mujeres y hombres aplazados"></div><p id="eh-sex-scale-note" class="eh-note"></p><p class="eh-note">Ambas líneas comparten la misma escala. El porcentaje expresa la participación de cada sexo en el total de aplazados; no es una tasa sobre la matrícula de ese sexo.</p>
        </section>
        <section class="eh-panel eh-pie-panel">
            <div class="eh-heading"><div><span class="eh-eyebrow">Distribución por gestión</span><h2 id="eh-pie-title">APLAZADOS HOMBRES VS. MUJERES — SANTA CRUZ — {{ $lastActualYear }}</h2></div><div class="eh-chart-actions">
                <label>Año<select id="eh-pie-year">@foreach($reportYears as $year)<option value="{{ $year }}" @selected($year===$lastActualYear)>{{ $year }}{{ $year===$projectionYear?' · proyección':'' }}</option>@endforeach</select></label>
                <x-history-replay id="eh-replay-pie" label="Repetir animación de la gráfica circular" />
            </div></div>
            <div class="eh-pie-layout">
                <div id="eh-pie-chart" class="eh-pie-chart" role="img" aria-label="Distribución circular de hombres y mujeres aplazados"></div>
                <div class="eh-pie-summary" aria-live="polite">
                    <p class="eh-pie-total">Total de aplazados<strong id="eh-pie-total">—</strong></p>
                    <div class="eh-pie-stat eh-pie-female">
                        <span class="eh-pie-stat-heading"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="7" r="5"/><path d="M12 12v10M7 18h10"/></svg>Mujeres</span>
                        <strong id="eh-pie-women">—</strong>
                        <span class="eh-pie-share"><b id="eh-pie-women-share">—</b><small>del total</small></span>
                    </div>
                    <div class="eh-pie-stat eh-pie-male">
                        <span class="eh-pie-stat-heading"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="9" cy="15" r="6"/><path d="M13.5 10.5 22 2M15 2h7v7"/></svg>Hombres</span>
                        <strong id="eh-pie-men">—</strong>
                        <span class="eh-pie-share"><b id="eh-pie-men-share">—</b><small>del total</small></span>
                    </div>
                </div>
            </div>
            <div class="eh-legend"><span><i class="eh-female"></i> Mujeres</span><span><i class="eh-male"></i> Hombres</span><span id="eh-pie-unknown-legend" hidden><i class="eh-unknown"></i> Sin desglose</span></div>
            <p id="eh-pie-note" class="eh-note"></p>
        </section>
        <section class="eh-panel">
            <div class="eh-heading"><div><span class="eh-eyebrow">Para comparar con calma</span><h2>Todos los años de <span id="eh-table-name">Santa Cruz</span></h2></div><span class="eh-note">Pulsa cualquier encabezado para ordenar ↑ o ↓.</span></div>
            <div class="eh-table-scroll"><table class="eh-table"><thead><tr>
                @foreach(['year'=>'Año','total'=>'Aplazados','hombres'=>'Hombres','mujeres'=>'Mujeres','porcentaje_hombres'=>'% hombres¹','porcentaje_mujeres'=>'% mujeres¹','tasa'=>'Tasa²','matricula'=>'Matriculados','colegios'=>'Colegios con reporte'] as $field=>$label)
                    <th scope="col" aria-sort="none"><button type="button" data-sort="{{ $field }}">{{ $label }} <span aria-hidden="true">↕</span></button></th>
                @endforeach
            </tr></thead><tbody id="eh-table-body"></tbody></table></div>
            <p class="eh-note">¹ Cantidad de hombres o mujeres aplazados ÷ total de aplazados × 100. Si el desglose es parcial se calculan los porcentajes conocidos y se indica su cobertura. Con cero aplazados no hay proporción que calcular. ² Aplazados ÷ matrícula × 100, solo en colegios con ambos datos del mismo año. Matriculados muestra toda la matrícula registrada, cuya cobertura puede ser mayor que la usada para la tasa.</p>
            <p id="eh-table-coverage" class="eh-note"></p>
        </section>
        <section class="eh-panel eh-panorama">
            <div class="eh-heading"><div><span class="eh-eyebrow">Panorama por gestión</span><h2>Los nueve departamentos · <span id="eh-panorama-title">{{ $lastActualYear }}</span></h2></div><div class="eh-chart-actions">
                <label>Año<select id="eh-panorama-year">@foreach($reportYears as $year)<option value="{{ $year }}" @selected($year===$lastActualYear)>{{ $year }}{{ $year===$projectionYear?' · proyección':'' }}</option>@endforeach</select></label>
                <label>Orden descendente por<select id="eh-panorama-sort"><option value="total">Cantidad de aplazados</option><option value="tasa">Porcentaje sobre matrícula</option></select></label>
            </div></div><p class="eh-note">El indicador elegido aparece en grande. La matrícula se muestra debajo de cada departamento. Selecciona una tarjeta para explorar su historia.</p><div class="eh-departments" id="eh-panorama-cards"></div>
        </section>
        <details class="eh-method" open><summary>Cómo leer la proyección de {{ $projectionYear }}</summary><p>Es un escenario orientativo calculado mediante una tendencia lineal de los registros disponibles de 2021–{{ $lastActualYear }}. Requiere al menos tres años y datos de {{ $lastActualYear }}. No es una cifra oficial ni una predicción garantizada. No se generan estimaciones cuando falta esa base.</p><p>La tasa proyectada usa tendencias separadas de aplazados y matrícula en los colegios con datos comparables. La matrícula del panorama se estima a partir de toda la matrícula registrada. El reparto por sexo se estima solo con años cuyo desglose está completo y se ajusta al total estimado. No se muestran tasas que superen 100 %. Los cambios en la cantidad de colegios que reportan pueden afectar la tendencia: compara también la cobertura de cada año.</p><p>Este informe utiliza los datos actualmente importados. No consulta ni extrae fichas del Ministerio al abrir la página. Los años pendientes de importación aparecen como “Sin datos”.</p></details>
    </div>
</div>
<script type="application/json" id="eh-data">{!! json_encode($departmentHistory, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE) !!}</script>
@endsection
@push('scripts')<script type="module" src="{{ asset('js/education-history.js').'?v='.filemtime(public_path('js/education-history.js')) }}"></script>@endpush
