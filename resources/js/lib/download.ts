/**
 * Extracts a filename from a fetch Response's Content-Disposition header
 * (e.g. `attachment; filename="empleados.xlsx"`), falling back when the
 * header is missing or unparsable.
 */
export function filenameFromContentDisposition(
    response: Response,
    fallback: string,
): string {
    const match = /filename="?([^"]+)"?/.exec(
        response.headers.get('content-disposition') ?? '',
    );

    return match?.[1] ?? fallback;
}
