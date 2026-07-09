<?php

namespace App\Actions;

use App\Models\Holiday;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Imports Chile's official public holidays for a given year from the free
 * Boostr API (https://api.boostr.cl) and upserts them as official holidays
 * (`organization_id = null`).
 *
 * The former official government API (apis.digital.gob.cl) is deprecated and the
 * old app scraped feriadoschilenos.cl; Boostr exposes the `inalienable`
 * (irrenunciable) flag the workday calculator needs and requires no API key.
 */
class SyncOfficialHolidays
{
    private const ENDPOINT = 'https://api.boostr.cl/holidays/%d.json';

    /**
     * Fetch and upsert the official holidays for the year.
     *
     * @return int the number of holidays imported
     *
     * @throws RuntimeException when the API is unreachable or returns an error
     */
    public function handle(int $year): int
    {
        $response = Http::acceptJson()
            ->timeout(15)
            ->get(sprintf(self::ENDPOINT, $year));

        if ($response->failed() || $response->json('status') !== 'success') {
            throw new RuntimeException("Unable to fetch holidays for {$year} from Boostr.");
        }

        /** @var array<int, array{date: string, title: string, inalienable: bool}> $holidays */
        $holidays = $response->json('data', []);

        foreach ($holidays as $holiday) {
            Holiday::updateOrCreate(
                [
                    'organization_id' => null,
                    'country' => 'cl',
                    'date' => $holiday['date'],
                ],
                [
                    'name' => $holiday['title'],
                    'mandatory' => (bool) $holiday['inalienable'],
                ],
            );
        }

        return count($holidays);
    }
}
