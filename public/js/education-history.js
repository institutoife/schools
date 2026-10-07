(() => {
    'use strict';
    const root = document.getElementById('education-report');
    if (!root) return;
    const history = JSON.parse(document.getElementById('eh-data').textContent);
    const departmentSelect = document.getElementById('eh-department');
    const measureSelect = document.getElementById('eh-measure');
    const fmt = new Intl.NumberFormat('es-BO');
    const pctFmt = new Intl.NumberFormat('es-BO', {minimumFractionDigits:1, maximumFractionDigits:1});
    const number = n => n === null || n === undefined ? 'Sin datos' : fmt.format(n);
    const percent = n => n === null || n === undefined ? 'Sin datos' : pctFmt.format(n) + ' %';
    const text = (id, value) => { document.getElementById(id).textContent = value; };
    const initial = new URLSearchParams(window.location.search);
    if (Object.hasOwn(history, initial.get('departamento'))) departmentSelect.value = initial.get('departamento');
    let selectedYear = [2021,2022,2023,2024,2025,2026].includes(Number(initial.get('anio'))) ? Number(initial.get('anio')) : 2025;
    let timer = null;
    function stop() { clearInterval(timer); timer = null; text('eh-play', '▶ Reproducir años'); }
    function render() {
        const url = new URL(window.location.href);
        url.searchParams.set('departamento', departmentSelect.value); url.searchParams.set('anio', selectedYear);
        window.history.replaceState(null, '', url);
        const rows = history[departmentSelect.value];
        const row = rows.find(r => r.year === selectedYear);
        const name = departmentSelect.value === 'POTOSI' ? 'POTOSÍ' : departmentSelect.value;
        text('eh-name', name); text('eh-table-name', name); text('eh-year', selectedYear);
        text('eh-status', row.projection ? 'Escenario estimado · no oficial' : 'Historia registrada');
        text('eh-total', number(row.total)); text('eh-rate', percent(row.tasa));
        text('eh-men', number(row.hombres)); text('eh-women', number(row.mujeres));
        text('eh-men-share', row.porcentaje_hombres !== null ? percent(row.porcentaje_hombres) + ' de los aplazados' : 'Proporción no disponible');
        text('eh-women-share', row.porcentaje_mujeres !== null ? percent(row.porcentaje_mujeres) + ' de los aplazados' : 'Proporción no disponible');
        const prior = rows.find(r => r.year === selectedYear - 1);
        let change = 'No hay un año anterior comparable';
        if (prior && prior.total !== null && row.total !== null) {
            const difference = row.total - prior.total;
            change = (difference > 0 ? '+' : '') + number(difference) + ' frente a ' + prior.year;
            if (prior.total > 0) change += ' (' + (difference > 0 ? '+' : '') + pctFmt.format(difference / prior.total * 100) + ' %)';
            if (!row.projection && row.colegios !== prior.colegios) change += ' · cambió la cobertura';
        }
        if (row.total === null) change = row.projection ? 'Faltan al menos tres años, incluido 2025' : 'Pendiente de datos importados';
        text('eh-change', change);
        document.getElementById('eh-men-bar').style.width = (row.porcentaje_hombres ?? 0) + '%';
        document.getElementById('eh-women-bar').style.width = (row.porcentaje_mujeres ?? 0) + '%';
        text('eh-sex-note', row.sexo_completo ? 'Verde: hombres · Naranja: mujeres. Porcentajes entre los aplazados del año seleccionado.' : 'El desglose por sexo está incompleto o no concuerda con el total. No se calculan proporciones.');
        text('eh-coverage', row.projection ? 'Proyección de tendencia, no dato oficial. La cobertura variable de los registros puede alterar la estimación.' : number(row.colegios) + ' colegios con datos de aplazados. Tasa calculada con ' + number(row.colegios_tasa) + ' colegios y ' + number(row.base_tasa) + ' estudiantes matriculados comparables.');
        root.querySelectorAll('[data-year]').forEach(button => button.setAttribute('aria-pressed', String(Number(button.dataset.year) === selectedYear)));
        const body = document.getElementById('eh-table-body'); body.replaceChildren();
        rows.forEach(item => {
            const tr = document.createElement('tr');
            tr.className = (item.year === selectedYear ? 'is-selected ' : '') + (item.projection ? 'is-projection' : '');
            [String(item.year) + (item.projection ? ' · estimación' : ''),number(item.total),number(item.hombres),number(item.mujeres),percent(item.porcentaje_hombres),percent(item.porcentaje_mujeres),percent(item.tasa),item.projection ? 'No aplica' : number(item.colegios)].forEach(value => { const td = document.createElement('td'); td.textContent = value; tr.append(td); });
            body.append(tr);
        });
        drawChart(rows);
        const focus = root.querySelector('.eh-focus'); focus.classList.remove('eh-refresh'); void focus.offsetWidth; focus.classList.add('eh-refresh');
    }
    function drawChart(rows) {
        const metric = measureSelect.value;
        const available = rows.map(r => r[metric]).filter(v => v !== null && Number.isFinite(v));
        const container = document.getElementById('eh-chart');
        container.replaceChildren();
        if (!available.length) { const p = document.createElement('p'); p.className = 'eh-note'; p.textContent = 'Aún no hay datos disponibles para dibujar esta evolución.'; container.append(p); return; }
        const max = Math.max(1,...available) * 1.18;
        const ns = 'http://www.w3.org/2000/svg';
        const svg = document.createElementNS(ns,'svg'); svg.setAttribute('viewBox','0 0 1000 310');
        const el = (name, attrs, label) => { const node = document.createElementNS(ns,name); Object.entries(attrs).forEach(([k,v]) => node.setAttribute(k,String(v))); if(label !== undefined) node.textContent = label; svg.append(node); return node; };
        const x = i => 85 + i * 167;
        const y = value => 250 - value / max * 205;
        for(let i=0;i<=4;i++) { const value=max*i/4; const lineY=y(value); el('line',{x1:75,y1:lineY,x2:940,y2:lineY,stroke:'#e5eeea'}); el('text',{x:65,y:lineY+4,'text-anchor':'end'},metric==='tasa'?pctFmt.format(value)+' %':fmt.format(Math.round(value))); }
        rows.forEach((r,i) => {
            el('text',{x:x(i),y:285,'text-anchor':'middle'},String(r.year)+(r.projection?'*':''));
            if(r[metric] === null) return;
            const color = r.projection ? '#ea8c45' : '#087f78';
            if(i>0 && rows[i-1][metric] !== null) el('path',{d:`M${x(i-1)},${y(rows[i-1][metric])} L${x(i)},${y(r[metric])}`,fill:'none',stroke:color,'stroke-width':4,...(r.projection?{'stroke-dasharray':'9 7'}:{class:'eh-plot-path'})});
            const point = el('circle',{cx:x(i),cy:y(r[metric]),r:r.year===selectedYear?9:6,fill:color,stroke:'white','stroke-width':3,tabindex:0,role:'button','aria-label':r.year+': '+(metric==='tasa'?percent(r[metric]):number(r[metric]))});
            const select = () => {stop(); selectedYear=r.year; render();}; point.addEventListener('click',select); point.addEventListener('keydown',event=>{if(event.key==='Enter'||event.key===' '){event.preventDefault();select();}});
            el('text',{x:x(i),y:y(r[metric])-17,'text-anchor':'middle','font-weight':700},metric==='tasa'?percent(r[metric]):number(r[metric]));
        });
        container.setAttribute('aria-label', 'Evolución de '+departmentSelect.value+' en '+(metric==='tasa'?'porcentaje':'cantidad')+' de 2021 a 2026. Año 2026 estimado.');
        container.append(svg);
    }
    departmentSelect.addEventListener('change',()=>{stop();render();});
    measureSelect.addEventListener('change',render);
    root.querySelectorAll('[data-year]').forEach(button=>button.addEventListener('click',()=>{stop();selectedYear=Number(button.dataset.year);render();}));
    root.querySelectorAll('[data-department]').forEach(button=>button.addEventListener('click',()=>{stop();departmentSelect.value=button.dataset.department;selectedYear=2025;render();root.querySelector('.eh-controls').scrollIntoView({behavior:'smooth',block:'start'});}));
    document.getElementById('eh-play').addEventListener('click',()=>{
        if(timer){stop();return;}
        selectedYear=2021;render();text('eh-play','Ⅱ Pausar reproducción');
        timer=setInterval(()=>{if(selectedYear>=2026){stop();return;} selectedYear++;render();},3000);
    });
    document.getElementById('eh-present').addEventListener('click',()=>{
        const enabled=document.documentElement.classList.toggle('eh-video');text('eh-present',enabled?'Salir de modo video':'Modo video');root.scrollIntoView({block:'start'});
    });
    document.addEventListener('keydown',event=>{if(event.key==='Escape'){document.documentElement.classList.remove('eh-video');text('eh-present','Modo video');stop();}});
    render();
})();
