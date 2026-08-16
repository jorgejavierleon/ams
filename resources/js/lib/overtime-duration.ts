/**
 * Overtime hours are stored and validated as a `H:i` duration (e.g. `01:30`),
 * but entered by the user as plain decimal hours (e.g. `1.5`) so they don't
 * have to think in hours-and-minutes.
 */

/** Converts decimal hours (e.g. `1.5`) to a zero-padded `H:i` duration. */
export function decimalHoursToTime(value: string): string {
    const decimal = Number(value);

    if (value === '' || Number.isNaN(decimal)) {
        return '';
    }

    const totalMinutes = Math.round(decimal * 60);
    const hours = Math.floor(totalMinutes / 60);
    const minutes = totalMinutes % 60;

    return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`;
}

/** Converts a `H:i` duration to decimal hours (e.g. `01:30` -> `1.5`). */
export function timeToDecimalHours(value: string | null): string {
    if (!value) {
        return '';
    }

    const [hours, minutes] = value.split(':').map(Number);
    const decimal = hours + minutes / 60;

    return Number.isInteger(decimal)
        ? String(decimal)
        : String(Math.round(decimal * 100) / 100);
}
