export function sortNumeric(rows, field, direction = 'desc') {
    const sign = direction === 'asc' ? 1 : -1;
    return [...rows].sort((a, b) => {
        const aMissing = !Number.isFinite(a[field]);
        const bMissing = !Number.isFinite(b[field]);
        if (aMissing || bMissing) return aMissing === bMissing ? 0 : aMissing ? 1 : -1;
        return sign * (a[field] - b[field]);
    });
}

export function sexPieSlices(row) {
    if (!Number.isFinite(row.total) || row.total < 0) return {status: 'missing', slices: []};
    const known = [
        {label: 'Mujeres', value: row.mujeres, color: 'rgb(38,186,165)', shade: '#168576'},
        {label: 'Hombres', value: row.hombres, color: 'rgb(55,95,122)', shade: '#254153'},
    ];
    if (known.some(slice => Number.isFinite(slice.value) && slice.value < 0)) return {status: 'invalid', slices: []};
    const sum = known.reduce((total, slice) => total + (Number.isFinite(slice.value) ? slice.value : 0), 0);
    if (sum > row.total) return {status: 'invalid', slices: []};
    if (row.total === 0) return {status: 'zero', slices: []};
    const slices = known.filter(slice => Number.isFinite(slice.value) && slice.value > 0);
    if (sum < row.total) slices.push({label: 'Sin desglose', value: row.total - sum, color: '#a6b4bc', shade: '#74848e'});
    let start = -Math.PI / 2;
    return {status: sum < row.total ? 'partial' : 'complete', slices: slices.map(slice => {
        const angle = slice.value / row.total * Math.PI * 2;
        const result = {...slice, percent: slice.value / row.total * 100, start, end: start + angle};
        start += angle;
        return result;
    })};
}

export function pieSectorPath(cx, cy, rx, ry, start, end) {
    const span = end - start;
    if (!Number.isFinite(span) || span <= .00001) return '';
    const point = angle => [cx + rx * Math.cos(angle), cy + ry * Math.sin(angle)];
    const a = point(start), b = point(end);
    if (span >= Math.PI * 2 - .00001) {
        const opposite = point(start + Math.PI);
        return `M${a} A${rx},${ry} 0 1 1 ${opposite} A${rx},${ry} 0 1 1 ${a} Z`;
    }
    return `M${cx},${cy} L${a} A${rx},${ry} 0 ${span > Math.PI ? 1 : 0} 1 ${b} Z`;
}

export function chartScale(values, {fromZero = false, percentage = false} = {}) {
    const points = values.filter(Number.isFinite);
    if (!points.length) return {min: 0, max: 1, ticks: [0, 1]};
    const low = Math.min(...points), high = Math.max(...points);
    const spread = Math.max(high - low, high * .08, percentage ? .5 : 1);
    const lower = fromZero ? 0 : Math.max(0, low - spread * .3);
    const upper = Math.max(lower + (percentage ? .5 : 1), high + spread * .3);
    const rough = (upper - lower) / 5;
    const power = 10 ** Math.floor(Math.log10(rough));
    const fraction = rough / power;
    let step = (fraction <= 1 ? 1 : fraction <= 2 ? 2 : fraction <= 2.5 ? 2.5 : fraction <= 5 ? 5 : 10) * power;
    if (!percentage) step = Math.max(1, step);
    const min = fromZero ? 0 : Math.max(0, Math.floor(lower / step) * step);
    let max = Math.ceil(upper / step) * step;
    if (percentage) max = Math.min(100, max);
    if (max <= min) return {min: Math.max(0, min - step), max: Math.max(step, max), ticks: [Math.max(0, min - step), Math.max(step, max)]};
    const ticks = [];
    for (let value = min; value <= max + step / 1000; value += step) ticks.push(Number(value.toFixed(6)));
    if (ticks.at(-1) !== max) ticks.push(max);
    return {min, max, ticks};
}
