export function decimalUnits(value: string): number | null {
    if (!/^\d{1,7}(?:\.\d{1,2})?$/.test(value)) return null;
    const [whole, fraction = ''] = value.split('.');
    return Number(whole) * 100 + Number(fraction.padEnd(2, '0'));
}

export function discountSavings(base: number, points: number): number {
    return Math.floor((base * points + 5000) / 10000);
}
