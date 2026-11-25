import assert from 'node:assert/strict';
import test from 'node:test';
import {
    decimalUnits,
    discountSavings,
} from '../../resources/js/lib/payment-discount.ts';

await test('sale preview uses exact centavos and rounds savings to the nearest centavo', () => {
    const base = decimalUnits('250.05');
    const savings = discountSavings(base, decimalUnits('12.50'));
    assert.equal(savings, 3126);
    assert.equal(base - savings, 21879);
    assert.equal(discountSavings(1, 5000), 1);
    assert.equal(discountSavings(1200000, decimalUnits('10')), 120000);
    assert.equal(discountSavings(base, decimalUnits('100')), base);
    assert.equal(discountSavings(base, 0), 0);
});

await test('preview rejects malformed amounts instead of silently rounding the input', () => {
    for (const input of ['', '-1', '1.234', '1,200', 'invalid']) {
        assert.equal(decimalUnits(input), null);
    }
    assert.equal(decimalUnits('0'), 0);
    assert.equal(decimalUnits('1500.50'), 150050);
});
