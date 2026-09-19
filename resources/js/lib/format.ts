/**
 * Formatting helpers for report figures.
 *
 * A bookkeeper compares columns of numbers, so everything here leans on
 * fixed decimals and grouped thousands rather than compact notation.
 */

export function formatMoney(value: number, currency: string): string {
    // A range with no orders in it has no currency to name.
    if (!currency) {
        return new Intl.NumberFormat('en-CH', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }).format(value);
    }

    return new Intl.NumberFormat('en-CH', {
        style: 'currency',
        currency,
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(value);
}

/**
 * Money without the decimals, for axis ticks where the cents are noise.
 */
export function formatMoneyShort(value: number, currency: string): string {
    const rounded = Math.round(value);

    if (!currency) {
        return new Intl.NumberFormat('en-CH').format(rounded);
    }

    return new Intl.NumberFormat('en-CH', {
        style: 'currency',
        currency,
        maximumFractionDigits: 0,
    }).format(rounded);
}

export function formatNumber(value: number): string {
    return new Intl.NumberFormat('en-CH').format(value);
}
