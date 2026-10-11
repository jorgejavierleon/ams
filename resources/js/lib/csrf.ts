/**
 * Reads the `XSRF-TOKEN` cookie Laravel's `PreventRequestForgery` middleware
 * already queues on every response, for `fetch()` calls that must mutate
 * state without going through Inertia's router — which would remount the
 * current page's layout, defeating the "no reload" point of a poll-backed
 * widget like the notification bell (KOL-87).
 */
export function csrfHeader(): Record<string, string> {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);

    if (!match) {
        return {};
    }

    return { 'X-XSRF-TOKEN': decodeURIComponent(match[1]) };
}
