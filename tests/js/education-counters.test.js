import test from 'node:test';
import assert from 'node:assert/strict';
import {createCounterAnimation} from '../../public/js/education-counters.js';

function fixture(values, reduced = false) {
    const frames = new Map();
    let nextId = 0;
    const document = {createElement: () => ({textContent: '', setAttribute(key, value) { this[key] = value; }})};
    const elements = values.map(() => ({ownerDocument: document, replaceChildren(...children) { this.children = children; }}));
    const format = value => Number.isFinite(value) ? value.toFixed(1) : 'Sin datos';
    const animation = createCounterAnimation(values.map((value, index) => ({element: elements[index], value, format})), {
        duration: 1000, now: () => 0, reducedMotion: () => reduced,
        requestFrame(callback) { const id = ++nextId; frames.set(id, callback); return id; },
        cancelFrame(id) { frames.delete(id); },
    });
    const advance = timestamp => { const pending = [...frames.values()]; frames.clear(); pending.forEach(callback => callback(timestamp)); };
    return {animation, elements, frames, advance, visible: () => elements.map(element => element.children[0].textContent)};
}

test('counts and percentages animate together and settle on the exact selected values', () => {
    const f = fixture([8333, 5.8, 5287, 3046, 63.45, 36.55]);
    assert.deepEqual(f.visible(), ['0.0', '0.0', '0.0', '0.0', '0.0', '0.0']);
    f.animation.play(); f.advance(500);
    assert.ok(Number(f.visible()[0]) > 0 && Number(f.visible()[0]) < 8333);
    assert.ok(Number(f.visible()[1]) > 0 && Number(f.visible()[1]) < 5.8);
    f.advance(1000);
    assert.deepEqual(f.visible(), ['8333.0', '5.8', '5287.0', '3046.0', '63.5', '36.5']);
    assert.equal(f.frames.size, 0);
});

test('rapid year or department changes cancel the old counter before replacement', () => {
    const f = fixture([8333]);
    f.animation.play(); f.advance(200); f.animation.cancel();
    const stopped = f.visible();
    f.advance(1000);
    assert.deepEqual(f.visible(), stopped);
    assert.equal(f.frames.size, 0);
});

test('zero and missing records retain their meaning throughout the animation', () => {
    const f = fixture([0, null, undefined]);
    f.animation.play(); f.advance(500); f.advance(1000);
    assert.deepEqual(f.visible(), ['0.0', 'Sin datos', 'Sin datos']);
});

test('reduced motion shows final values immediately without scheduling animation', () => {
    const f = fixture([8333, 5.8], true);
    assert.deepEqual(f.visible(), ['8333.0', '5.8']);
    f.animation.play();
    assert.equal(f.frames.size, 0);
});

test('assistive technology gets the final value rather than every intermediate frame', () => {
    const f = fixture([8333]);
    assert.equal(f.elements[0].children[0]['aria-hidden'], 'true');
    assert.equal(f.elements[0].children[1].textContent, '8333.0');
    f.animation.play(); f.advance(400);
    assert.equal(f.elements[0].children[1].textContent, '8333.0');
});
