/**
 * Chilean RUT/RUN helpers mirroring the backend's `App\Support\Rut`
 * normalisation and modulo-11 verifier check, so forms can format and
 * validate a RUT as the user types without a server round trip.
 */

function cleanRut(value: string): string {
    return value
        .toUpperCase()
        .replace(/[^0-9K]/g, '')
        .slice(0, 9);
}

/** Human display form as the user types: `12.345.678-5`. */
export function formatRut(raw: string): string {
    const clean = cleanRut(raw);

    if (clean.length < 2) {
        return clean;
    }

    const body = clean.slice(0, -1);
    const dv = clean.slice(-1);
    const groupedBody = body.replace(/\B(?=(\d{3})+(?!\d))/g, '.');

    return `${groupedBody}-${dv}`;
}

/** Strips any formatting, returning digits and verifier only: `123456785`. */
export function unformatRut(formatted: string): string {
    return cleanRut(formatted);
}

function computeDv(body: string): string {
    let sum = 0;
    let multiplier = 2;

    for (const digit of body.split('').reverse()) {
        sum += Number(digit) * multiplier;
        multiplier = multiplier === 7 ? 2 : multiplier + 1;
    }

    const remainder = 11 - (sum % 11);

    if (remainder === 11) {
        return '0';
    }

    if (remainder === 10) {
        return 'K';
    }

    return String(remainder);
}

/** Validates a (formatted or raw) RUT's modulo-11 verifier digit. */
export function validateRut(rut: string): boolean {
    const clean = cleanRut(rut);

    if (clean.length < 2) {
        return false;
    }

    const body = clean.slice(0, -1);
    const dv = clean.slice(-1);

    if (!/^\d+$/.test(body) || Number(body) === 0) {
        return false;
    }

    return computeDv(body) === dv;
}
