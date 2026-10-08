export function createCounterAnimation(entries, {
    duration = 1400,
    requestFrame = callback => requestAnimationFrame(callback),
    cancelFrame = id => cancelAnimationFrame(id),
    now = () => performance.now(),
    reducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches,
} = {}) {
    let frame = null;
    const counters = entries.map(({element, value, format}) => {
        const visual = element.ownerDocument.createElement('span');
        visual.setAttribute('aria-hidden', 'true');
        const accessible = element.ownerDocument.createElement('span');
        accessible.className = 'eh-counter-accessible';
        accessible.textContent = format(value);
        visual.textContent = format(Number.isFinite(value) && !reducedMotion() ? 0 : value);
        element.replaceChildren(visual, accessible);
        return {visual, value, format};
    });
    const draw = progress => counters.forEach(({visual, value, format}) => {
        if (Number.isFinite(value)) visual.textContent = format(progress === 1 ? value : value * progress);
    });
    const cancel = () => { if (frame !== null) cancelFrame(frame); frame = null; };
    const play = () => {
        cancel();
        if (reducedMotion() || duration <= 0) { draw(1); return; }
        const start = now();
        draw(0);
        const tick = timestamp => {
            const progress = Math.max(0, Math.min(1, (timestamp - start) / duration));
            draw(1 - (1 - progress) ** 3);
            frame = progress < 1 ? requestFrame(tick) : null;
        };
        frame = requestFrame(tick);
    };
    return {play, cancel};
}
