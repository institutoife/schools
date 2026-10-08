import test from 'node:test';
import assert from 'node:assert/strict';
import {chartScale, sortNumeric, sexPieSlices, pieSectorPath} from '../../public/js/education-history-utils.js';

test('detail scale makes small changes visible while exposing the real bounds', () => {
    const scale = chartScale([1000,1010,995,1005]);
    assert.ok(scale.min > 0);
    assert.ok(scale.min <= 995 && scale.max >= 1010);
    assert.ok(scale.max - scale.min < 1000);
    assert.ok(scale.ticks.length >= 3 && scale.ticks.length <= 10);
    assert.equal(chartScale([1000,1010], {fromZero:true}).min,0);
});

test('shared sex scale contains both series and percentages remain between 0 and 100', () => {
    const scale = chartScale([15,50,20,60,25,55]);
    assert.ok(scale.min <= 15 && scale.max >= 60);
    const percentages = chartScale([96,97,99,100], {percentage:true});
    assert.ok(percentages.min >= 0 && percentages.max === 100);
    assert.equal(percentages.ticks.at(-1),100);
});

test('flat, zero, absent and tiny percentage series produce usable scales', () => {
    for(const values of [[0,0,0],[500,500,500],[],[null,undefined]]) {
        const scale = chartScale(values);
        assert.ok(scale.max > scale.min);
        assert.ok(scale.ticks.every(Number.isFinite));
    }
    const tiny=chartScale([.01,.02,.03], {percentage:true});
    assert.ok(tiny.min <= .01 && tiny.max >= .03);
});

test('every numeric table column sorts both ways, preserving zeros and placing missing values last', () => {
    for(const field of ['year','total','hombres','mujeres','porcentaje_hombres','porcentaje_mujeres','tasa','matricula','colegios']) {
        const rows = [{[field]:2},{[field]:null},{[field]:100},{[field]:0}];
        assert.deepEqual(sortNumeric(rows,field,'desc').map(r=>r[field]),[100,2,0,null]);
        assert.deepEqual(sortNumeric(rows,field,'asc').map(r=>r[field]),[0,2,100,null]);
        assert.equal(rows[0][field],2);
    }
});

test('pie slices preserve the actual sex proportions and the IFE palette', () => {
    const {status, slices} = sexPieSlices({total:100,hombres:60,mujeres:40});
    assert.equal(status,'complete');
    assert.deepEqual(slices.map(s=>[s.label,s.value,s.percent,s.color]),[
        ['Mujeres',40,40,'rgb(38,186,165)'],['Hombres',60,60,'rgb(55,95,122)'],
    ]);
    assert.ok(Math.abs(slices.at(-1).end - slices[0].start - Math.PI*2) < .000001);
    assert.equal(slices[0].end,slices[1].start);
});

test('pie keeps unknown cases separate and does not invent proportions for zero or invalid totals', () => {
    const partial=sexPieSlices({total:100,hombres:null,mujeres:40});
    assert.equal(partial.status,'partial');
    assert.deepEqual(partial.slices.map(s=>[s.label,s.percent]),[['Mujeres',40],['Sin desglose',60]]);
    assert.equal(sexPieSlices({total:0,hombres:0,mujeres:0}).status,'zero');
    assert.equal(sexPieSlices({total:null,hombres:40,mujeres:30}).status,'missing');
    assert.equal(sexPieSlices({total:10,hombres:9,mujeres:8}).status,'invalid');
    assert.equal(sexPieSlices({total:0,hombres:1,mujeres:0}).status,'invalid');
});

test('animated sectors support empty sweeps, one-sex full circles and the halfway arc', () => {
    assert.equal(pieSectorPath(280,190,215,140,0,0),'');
    assert.equal(pieSectorPath(280,190,215,140,1,0),'');
    const one=sexPieSlices({total:100,hombres:100,mujeres:0});
    const full=pieSectorPath(280,190,215,140,one.slices[0].start,one.slices[0].end);
    assert.equal((full.match(/ A/g)||[]).length,2);
    assert.ok(!full.includes('NaN'));
    const half=pieSectorPath(280,190,215,140,0,Math.PI);
    assert.ok(half.startsWith('M280,190 L495,190 A215,140'));
});
