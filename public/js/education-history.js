import {chartScale, sortNumeric, sexPieSlices, pieSectorPath} from './education-history-utils.js';
import {createCounterAnimation} from './education-counters.js';

const root = document.getElementById('education-report');
if (root) {
    const data = JSON.parse(document.getElementById('eh-data').textContent);
    const get = id => document.getElementById(id);
    const setText = (id, value) => { get(id).textContent = value; };
    const department = get('eh-department'), measure = get('eh-measure'), scaleControl = get('eh-scale');
    const sexMeasure = get('eh-sex-measure'), panoramaYear = get('eh-panorama-year'), panoramaSort = get('eh-panorama-sort');
    const pieYear = get('eh-pie-year');
    const years = Object.values(data)[0].map(row => row.year);
    const projectionYear = years.at(-1), lastActualYear = projectionYear - 1;
    const fmt = new Intl.NumberFormat('es-BO');
    const pctFmt = new Intl.NumberFormat('es-BO', {minimumFractionDigits: 1, maximumFractionDigits: 1});
    const number = n => Number.isFinite(n) ? fmt.format(n) : 'Sin datos';
    const percent = n => Number.isFinite(n) ? pctFmt.format(n) + ' %' : 'Sin datos';
    const departmentName = name => name === 'POTOSI' ? 'POTOSÍ' : name;
    const initial = new URLSearchParams(location.search);
    if (Object.hasOwn(data, initial.get('departamento'))) department.value = initial.get('departamento');
    let selectedYear = years.includes(Number(initial.get('anio'))) ? Number(initial.get('anio')) : lastActualYear;
    panoramaYear.value = String(selectedYear);
    pieYear.value = String(selectedYear);
    let timer = null, sortField = 'year', sortDirection = 'asc', chartKey = '';
    let pieKey = '', pieAnimationFrame = 0, pieAnimate = null;
    let counterKey = '', counterAnimation = null;
    const turquoise = 'rgb(38,186,165)', blue = 'rgb(55,95,122)';
    const animationObserver = 'IntersectionObserver' in window ? new IntersectionObserver(entries => {
        entries.forEach(entry => { if (entry.isIntersecting) { entry.target.classList.add('eh-animating'); if (entry.target.id === 'eh-pie-chart') pieAnimate?.(); if (entry.target.id === 'eh-summary') counterAnimation?.play(); animationObserver.unobserve(entry.target); } });
    }, {threshold: .15}) : null;

    function stop() { clearInterval(timer); timer = null; setText('eh-play', '▶ Reproducir años'); }
    function selectYear(year) { selectedYear = year; panoramaYear.value = String(year); pieYear.value = String(year); render(); }

    function render() {
        const url = new URL(location.href);
        url.searchParams.set('departamento', department.value); url.searchParams.set('anio', selectedYear);
        window.history.replaceState(null, '', url);
        const rows = data[department.value], row = rows.find(item => item.year === selectedYear);
        setText('eh-name', departmentName(department.value)); setText('eh-table-name', departmentName(department.value)); setText('eh-year', selectedYear);
        setText('eh-main-title', 'HISTORIAL APLAZADOS ' + departmentName(department.value));
        setText('eh-sex-title', 'APLAZADOS HOMBRES VS. MUJERES — ' + departmentName(department.value));
        setText('eh-status', row.projection ? 'Escenario estimado · no oficial' : 'Historia registrada');
        const nextCounterKey = department.value + '|' + selectedYear;
        if (nextCounterKey !== counterKey) {
            counterKey = nextCounterKey;
            counterAnimation?.cancel();
            const summary = get('eh-summary');
            animationObserver?.unobserve(summary);
            const share = value => Number.isFinite(value) ? percent(value) + ' del total de aplazados' : 'Proporción no disponible';
            counterAnimation = createCounterAnimation([
                {element: get('eh-total'), value: row.total, format: number},
                {element: get('eh-rate'), value: row.tasa, format: percent},
                {element: get('eh-men'), value: row.hombres, format: number},
                {element: get('eh-women'), value: row.mujeres, format: number},
                {element: get('eh-men-share'), value: row.porcentaje_hombres, format: share},
                {element: get('eh-women-share'), value: row.porcentaje_mujeres, format: share},
            ]);
            if (animationObserver) animationObserver.observe(summary); else counterAnimation.play();
        }
        const prior = rows.find(item => item.year === selectedYear - 1);
        let change = 'No hay un año anterior comparable';
        if (prior && prior.total !== null && row.total !== null) {
            const difference = row.total - prior.total;
            change = (difference > 0 ? '+' : '') + number(difference) + ' frente a ' + prior.year;
            if (prior.total > 0) change += ' (' + (difference > 0 ? '+' : '') + pctFmt.format(difference / prior.total * 100) + ' %)';
            if (!row.projection && row.colegios !== prior.colegios) change += ' · cambió la cobertura';
        }
        if (row.total === null) change = row.projection ? 'Faltan al menos tres años, incluido ' + lastActualYear : 'Pendiente de datos importados';
        setText('eh-change', change);
        const sharesFit = (row.porcentaje_hombres ?? 0) + (row.porcentaje_mujeres ?? 0) <= 100.01;
        get('eh-men-bar').style.width = (sharesFit ? row.porcentaje_hombres ?? 0 : 0) + '%';
        get('eh-women-bar').style.width = (sharesFit ? row.porcentaje_mujeres ?? 0 : 0) + '%';
        setText('eh-sex-note', row.sexo_completo ? 'Desglose completo del total de aplazados.' : 'Desglose parcial o con diferencias respecto al total. Los porcentajes usan las cantidades conocidas; no se completan datos faltantes.');
        setText('eh-coverage', row.projection ? 'Proyección de tendencia, no dato oficial. La cobertura variable de los registros puede alterar la estimación.' : number(row.colegios) + ' colegios con datos de aplazados. Tasa calculada con ' + number(row.colegios_tasa) + ' colegios y ' + number(row.base_tasa) + ' estudiantes matriculados comparables.');
        root.querySelectorAll('[data-year]').forEach(button => button.setAttribute('aria-pressed', String(Number(button.dataset.year) === selectedYear)));
        renderTable(); renderPanorama();
        const nextKey = [department.value, measure.value, sexMeasure.value, scaleControl.value].join('|');
        if (nextKey !== chartKey) { chartKey = nextKey; renderCharts(); }
        const nextPieKey = department.value + '|' + pieYear.value;
        if (nextPieKey !== pieKey) { pieKey = nextPieKey; drawPie(); }
        root.querySelectorAll('.eh-chart [data-point-year]').forEach(point => { point.setAttribute('r', Number(point.dataset.pointYear) === selectedYear ? '9' : '6'); });
        const focus = root.querySelector('.eh-focus'); focus.classList.remove('eh-refresh'); void focus.offsetWidth; focus.classList.add('eh-refresh');
    }

    function renderTable() {
        const body = get('eh-table-body'); body.replaceChildren();
        sortNumeric(data[department.value], sortField, sortDirection).forEach(row => {
            const tr = document.createElement('tr');
            tr.className = (row.year === selectedYear ? 'is-selected ' : '') + (row.projection ? 'is-projection' : '');
            const values = [String(row.year) + (row.projection ? ' · estimación' : ''), number(row.total), number(row.hombres), number(row.mujeres), percent(row.porcentaje_hombres), percent(row.porcentaje_mujeres), percent(row.tasa), number(row.matricula), row.projection ? 'No aplica' : number(row.colegios)];
            values.forEach(value => { const td = document.createElement('td'); td.textContent = value; tr.append(td); });
            if (!row.sexo_completo && row.total !== null) tr.title = 'Desglose por sexo parcial o con diferencias respecto al total registrado.';
            body.append(tr);
        });
        root.querySelectorAll('[data-sort]').forEach(button => {
            const active = button.dataset.sort === sortField;
            button.parentElement.setAttribute('aria-sort', active ? sortDirection === 'asc' ? 'ascending' : 'descending' : 'none');
            button.querySelector('span').textContent = active ? sortDirection === 'asc' ? '↑' : '↓' : '↕';
        });
        const incomplete = data[department.value].filter(row => row.total !== null && !row.sexo_completo).map(row => row.year);
        setText('eh-table-coverage', incomplete.length ? 'Desglose por sexo parcial o con diferencias en: ' + incomplete.join(', ') + '. Un porcentaje conocido puede calcularse aunque falte parte del desglose.' : 'El desglose de hombres y mujeres coincide con el total en todos los años con datos.');
    }

    function renderPanorama() {
        const year = Number(panoramaYear.value), field = panoramaSort.value;
        setText('eh-panorama-title', year + (year === projectionYear ? ' · proyección' : ''));
        const entries = Object.entries(data).map(([name, rows]) => ({name, ...rows.find(row => row.year === year)})).sort((a, b) => a.name.localeCompare(b.name));
        const cards = get('eh-panorama-cards'); cards.replaceChildren();
        sortNumeric(entries, field).forEach((row, index) => {
            const card = document.createElement('button'); card.type = 'button'; card.className = row.projection ? 'eh-card-projection' : '';
            const add = (tag, label, className) => { const element = document.createElement(tag); element.textContent = label; if (className) element.className = className; card.append(element); };
            add('span', String(index + 1).padStart(2, '0') + ' · ' + departmentName(row.name), 'eh-card-name');
            add('strong', field === 'tasa' ? percent(row.tasa) : number(row.total));
            add('span', field === 'tasa' ? 'Tasa de aplazados sobre matrícula' : 'Estudiantes aplazados', 'eh-card-label');
            add('small', field === 'tasa' ? number(row.total) + ' aplazados' : percent(row.tasa) + ' de tasa');
            add('small', 'Matriculados: ' + number(row.matricula), 'eh-card-enrolment');
            if (row.projection) add('small', 'Escenario estimado · no oficial');
            card.addEventListener('click', () => { stop(); department.value = row.name; selectYear(year); root.querySelector('.eh-controls').scrollIntoView({behavior: 'smooth', block: 'start'}); });
            cards.append(card);
        });
    }

    function drawPie() {
        cancelAnimationFrame(pieAnimationFrame);
        pieAnimate = null;
        const container = get('eh-pie-chart');
        animationObserver?.unobserve(container);
        container.replaceChildren();
        const year = Number(pieYear.value);
        const row = data[department.value].find(item => item.year === year);
        const title = 'APLAZADOS HOMBRES VS. MUJERES — ' + departmentName(department.value) + ' — ' + year;
        setText('eh-pie-title', title);
        setText('eh-pie-total', number(row.total));
        setText('eh-pie-women', number(row.mujeres)); setText('eh-pie-men', number(row.hombres));
        setText('eh-pie-women-share', percent(row.porcentaje_mujeres)); setText('eh-pie-men-share', percent(row.porcentaje_hombres));
        const result = sexPieSlices(row);
        get('eh-pie-unknown-legend').hidden = result.status !== 'partial';
        const notes = {
            complete: 'Porcentajes respecto al total de aplazados de la gestión seleccionada.',
            partial: 'La sección “Sin desglose” corresponde a aplazados sin clasificación por sexo disponible. No se asignan esos casos a hombres o mujeres.',
            zero: 'No se registran aplazados en esta gestión. No hay proporciones que representar.',
            missing: 'No hay un total disponible para representar esta gestión.',
            invalid: 'El desglose por sexo no concuerda con el total registrado. No se dibujan proporciones inconsistentes.',
        };
        const note = notes[result.status] + (row.projection ? ' PROYECCIÓN: escenario estimado, no dato oficial.' : '');
        setText('eh-pie-note', note);
        container.setAttribute('aria-label', title + '. ' + result.slices.map(slice => slice.label + ': ' + number(slice.value) + ', ' + percent(slice.percent)).join('. ') + '. ' + note);
        if (!result.slices.length) {
            const message = document.createElement('p'); message.className = 'eh-pie-empty'; message.textContent = notes[result.status]; container.append(message);
            return;
        }
        const ns = 'http://www.w3.org/2000/svg';
        const svg = document.createElementNS(ns, 'svg'); svg.setAttribute('viewBox', '40 25 480 350');
        const make = (tag, attrs, text, parent = svg) => { const node = document.createElementNS(ns, tag); Object.entries(attrs).forEach(([key, value]) => node.setAttribute(key, String(value))); if (text !== undefined) node.textContent = text; parent.append(node); return node; };
        make('title', {}, title);
        make('ellipse', {cx: 280, cy: 220, rx: 218, ry: 142, fill: blue, opacity: .12, class: 'eh-pie-shadow'});
        const paths = [];
        // Extruded layers share the same slice angles; depth never changes the represented percentages.
        for (let depth = 22; depth >= 0; depth -= 2) {
            result.slices.forEach(slice => {
                const path = make('path', {fill: depth ? slice.shade : slice.color, stroke: depth ? slice.shade : 'white', 'stroke-width': depth ? 1 : 1.5});
                if (depth === 0) make('title', {}, slice.label + ': ' + number(slice.value) + ' (' + percent(slice.percent) + ')', path);
                paths.push({path, slice, cy: 190 + depth});
            });
        }
        const labels = result.slices.filter(slice => slice.percent >= 8).map(slice => {
            const angle = (slice.start + slice.end) / 2;
            const compact = slice.percent < 18;
            const label = make('g', {
                transform: 'translate(' + (280 + 215 * .6 * Math.cos(angle)) + ' ' + (190 + 140 * .6 * Math.sin(angle)) + ')',
                class: 'eh-pie-annotation' + (compact ? ' eh-pie-annotation-compact' : ''), opacity: 0, 'aria-hidden': 'true',
            });
            const icon = make('g', {transform: compact ? 'translate(-12 -32)' : 'translate(-18 -44) scale(1.5)', class: 'eh-pie-sex-icon'}, undefined, label);
            if (slice.label === 'Mujeres') {
                make('circle', {cx: 12, cy: 7, r: 5}, undefined, icon);
                make('path', {d: 'M12 12v10M7 18h10'}, undefined, icon);
            } else if (slice.label === 'Hombres') {
                make('circle', {cx: 9, cy: 15, r: 6}, undefined, icon);
                make('path', {d: 'M13.5 10.5 22 2M15 2h7v7'}, undefined, icon);
            } else {
                make('text', {x: 12, y: 18, 'text-anchor': 'middle', class: 'eh-pie-unknown-icon'}, '?', icon);
            }
            make('text', {x: 0, y: compact ? 15 : 22, 'text-anchor': 'middle', 'dominant-baseline': 'middle', class: 'eh-pie-label'}, percent(slice.percent), label);
            return {label, slice};
        });
        const draw = progress => {
            const end = -Math.PI / 2 + progress * Math.PI * 2;
            paths.forEach(({path, slice, cy}) => path.setAttribute('d', pieSectorPath(280, cy, 215, 140, slice.start, Math.min(slice.end, end))));
            labels.forEach(({label, slice}) => label.setAttribute('opacity', end >= slice.end - .0001 ? 1 : 0));
        };
        draw(0); container.append(svg);
        pieAnimate = () => {
            cancelAnimationFrame(pieAnimationFrame);
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) { draw(1); return; }
            const start = performance.now();
            const animate = now => {
                const progress = Math.min(1, (now - start) / 1400);
                draw(1 - (1 - progress) ** 3);
                if (progress < 1) pieAnimationFrame = requestAnimationFrame(animate);
            };
            pieAnimationFrame = requestAnimationFrame(animate);
        };
        if (animationObserver) animationObserver.observe(container); else pieAnimate();
    }

    function renderCharts() {
        const rows = data[department.value];
        drawChart('eh-chart', 'eh-main-scale-note', rows, [{field: measure.value, label: 'Total', color: turquoise}], measure.value === 'tasa');
        const share = sexMeasure.value === 'share';
        drawChart('eh-sex-chart', 'eh-sex-scale-note', rows, [{field: share ? 'porcentaje_mujeres' : 'mujeres', label: 'Mujeres', color: turquoise}, {field: share ? 'porcentaje_hombres' : 'hombres', label: 'Hombres', color: blue}], share);
    }

    function drawChart(id, noteId, rows, series, percentage) {
        const container = get(id); animationObserver?.unobserve(container); container.classList.remove('eh-animating'); container.replaceChildren();
        const values = series.flatMap(item => rows.map(row => row[item.field])).filter(Number.isFinite);
        if (!values.length) { const p = document.createElement('p'); p.className = 'eh-note'; p.textContent = 'Aún no hay datos disponibles para dibujar esta evolución.'; container.append(p); setText(noteId, ''); return; }
        const scale = chartScale(values, {fromZero: scaleControl.value === 'zero', percentage});
        const format = percentage ? percent : number;
        setText(noteId, 'Escala vertical: ' + format(scale.min) + ' a ' + format(scale.max) + '. ' + (scale.min > 0 ? 'El eje no empieza en cero para mostrar la variación; puedes elegir “Desde cero”.' : 'El eje empieza en cero.') + ' Los límites se mantienen durante la animación.');
        const ns = 'http://www.w3.org/2000/svg';
        const svg = document.createElementNS(ns, 'svg'); svg.setAttribute('viewBox', '0 0 1100 470');
        const create = (tag, attrs, label, parent = svg) => { const node = document.createElementNS(ns, tag); Object.entries(attrs).forEach(([key, value]) => node.setAttribute(key, String(value))); if (label !== undefined) node.textContent = label; parent.append(node); return node; };
        create('title', {}, departmentName(department.value) + ' · ' + series.map(item => item.label).join(' vs. '));
        const left = 130, right = 1015, top = 70, bottom = 370;
        const x = index => left + index * (right - left) / Math.max(1, rows.length - 1);
        const y = value => bottom - (value - scale.min) / (scale.max - scale.min) * (bottom - top);
        create('text', {x: left, y: 28, class: 'eh-axis-title'}, percentage ? 'Porcentaje (%)' : 'Aplazados (estudiantes)');
        scale.ticks.forEach(tick => {
            create('line', {x1: left, y1: y(tick), x2: right, y2: y(tick), stroke: '#dbe5ea', 'stroke-width': 1});
            create('text', {x: left - 18, y: y(tick) + 6, 'text-anchor': 'end', class: 'eh-axis-tick'}, percentage ? pctFmt.format(tick) + ' %' : number(tick));
        });
        create('line', {x1: left, y1: top, x2: left, y2: bottom, stroke: blue, 'stroke-width': 2});
        create('line', {x1: left, y1: bottom, x2: right, y2: bottom, stroke: blue, 'stroke-width': 2});
        rows.forEach((row, index) => {
            create('line', {x1: x(index), y1: bottom, x2: x(index), y2: bottom + 8, stroke: blue, 'stroke-width': 2});
            create('text', {x: x(index), y: bottom + 36, 'text-anchor': 'middle', class: 'eh-year-tick'}, row.year);
            if (row.projection) create('text', {x: x(index), y: bottom + 60, 'text-anchor': 'middle', class: 'eh-projection-tick'}, 'Proyección');
        });
        create('text', {x: (left + right) / 2, y: 460, 'text-anchor': 'middle', class: 'eh-axis-title'}, 'Gestión / año');
        const defs = create('defs', {}), clip = create('clipPath', {id: id + '-reveal'}, undefined, defs);
        create('rect', {x: left - 12, y: 0, width: right - left + 90, height: 430, class: 'eh-reveal-window'}, undefined, clip);
        const lines = create('g', {'clip-path': 'url(#' + id + '-reveal)'});
        series.forEach((item, seriesIndex) => {
            rows.forEach((row, index) => {
                const value = row[item.field]; if (!Number.isFinite(value)) return;
                const previous = rows[index - 1];
                const color = series.length === 1 && row.projection ? blue : item.color;
                if (previous && Number.isFinite(previous[item.field])) create('path', {d: `M${x(index - 1)},${y(previous[item.field])} L${x(index)},${y(value)}`, fill: 'none', stroke: color, 'stroke-width': 4.5, ...(row.projection ? {'stroke-dasharray': '10 8'} : {})}, undefined, lines);
                const group = create('g', {class: 'eh-point-reveal', style: '--point-delay:' + index * (3.2 / Math.max(1, rows.length - 1)) + 's'});
                const point = create('circle', {cx: x(index), cy: y(value), r: row.year === selectedYear ? 9 : 6, fill: color, stroke: 'white', 'stroke-width': 2.5, tabindex: 0, role: 'button', 'data-point-year': row.year, 'aria-label': item.label + ', ' + row.year + ': ' + format(value)}, undefined, group);
                create('title', {}, item.label + ': ' + format(value) + ' · ' + row.year + (row.projection ? ' (estimación)' : ''), point);
                const choose = () => { stop(); selectYear(row.year); };
                point.addEventListener('click', choose); point.addEventListener('keydown', event => { if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); choose(); } });
                const other = series.length === 2 ? rows[index][series[1 - seriesIndex].field] : null;
                const close = Number.isFinite(other) && Math.abs(y(value) - y(other)) < 40;
                const offset = close ? seriesIndex === 0 ? -40 : -16 : -20;
                create('text', {x: x(index), y: y(value) + offset, 'text-anchor': 'middle', class: 'eh-point-label', style: 'fill:' + color}, format(value), group);
            });
        });
        container.setAttribute('aria-label', departmentName(department.value) + ': ' + series.map(item => item.label).join(' y ') + ', de ' + rows[0].year + ' a ' + projectionYear + '. Escala ' + format(scale.min) + ' a ' + format(scale.max) + '. Último año estimado.');
        container.append(svg);
        if (animationObserver) animationObserver.observe(container); else container.classList.add('eh-animating');
    }

    department.addEventListener('change', () => { stop(); render(); });
    measure.addEventListener('change', render); sexMeasure.addEventListener('change', render); scaleControl.addEventListener('change', render);
    panoramaYear.addEventListener('change', renderPanorama); panoramaSort.addEventListener('change', renderPanorama);
    pieYear.addEventListener('change', () => { pieKey = department.value + '|' + pieYear.value; drawPie(); });
    get('eh-replay-pie').addEventListener('click', drawPie);
    root.querySelectorAll('[data-year]').forEach(button => button.addEventListener('click', () => { stop(); selectYear(Number(button.dataset.year)); }));
    root.querySelectorAll('[data-sort]').forEach(button => button.addEventListener('click', () => { sortDirection = sortField === button.dataset.sort && sortDirection === 'desc' ? 'asc' : 'desc'; sortField = button.dataset.sort; renderTable(); }));
    get('eh-replay-main').addEventListener('click', () => drawChart('eh-chart', 'eh-main-scale-note', data[department.value], [{field: measure.value, label: 'Total', color: turquoise}], measure.value === 'tasa'));
    get('eh-replay-sex').addEventListener('click', () => { const share = sexMeasure.value === 'share'; drawChart('eh-sex-chart', 'eh-sex-scale-note', data[department.value], [{field: share ? 'porcentaje_mujeres' : 'mujeres', label: 'Mujeres', color: turquoise}, {field: share ? 'porcentaje_hombres' : 'hombres', label: 'Hombres', color: blue}], share); });
    get('eh-play').addEventListener('click', () => {
        if (timer) { stop(); return; }
        selectYear(years[0]); renderCharts(); setText('eh-play', 'Ⅱ Pausar reproducción');
        timer = setInterval(() => { const index = years.indexOf(selectedYear); if (index >= years.length - 1) { stop(); return; } selectYear(years[index + 1]); }, 3000);
    });
    get('eh-present').addEventListener('click', () => { const enabled = document.documentElement.classList.toggle('eh-video'); setText('eh-present', enabled ? 'Salir de modo video' : 'Modo video'); root.scrollIntoView({block: 'start'}); });
    document.addEventListener('keydown', event => { if (event.key === 'Escape') { document.documentElement.classList.remove('eh-video'); setText('eh-present', 'Modo video'); stop(); } });
    render();
}
