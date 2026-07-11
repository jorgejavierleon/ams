<?php

namespace Database\Seeders;

use App\Actions\SyncOfficialHolidays;
use App\Models\Holiday;
use Illuminate\Database\Seeder;

class HolidaySeeder extends Seeder
{
    /**
     * Seed Chile's official 2026 public holidays so a fresh database has the
     * feriados the workday/business-days calculations rely on, without hitting
     * the Boostr API on every refresh.
     *
     * The rows mirror what {@see SyncOfficialHolidays} imports:
     * official holidays with `organization_id = null` and `country = 'cl'`, the
     * `mandatory` flag being Boostr's `inalienable` (irrenunciable) flag. Rerun
     * `php artisan holidays:sync 2026` to refresh them from the live API.
     *
     * @var list<array{0: string, 1: string, 2: bool}>
     */
    private const HOLIDAYS_2026 = [
        ['2026-01-01', 'Año Nuevo', true],
        ['2026-04-03', 'Viernes Santo', false],
        ['2026-04-04', 'Sábado Santo', false],
        ['2026-05-01', 'Día Nacional del Trabajo', true],
        ['2026-05-21', 'Día de las Glorias Navales', false],
        ['2026-06-21', 'Día Nacional de los Pueblos Indígenas', false],
        ['2026-06-29', 'San Pedro y San Pablo', false],
        ['2026-07-16', 'Día de la Virgen del Carmen', false],
        ['2026-08-15', 'Asunción de la Virgen', false],
        ['2026-09-18', 'Independencia Nacional', true],
        ['2026-09-19', 'Día de las Glorias del Ejército', true],
        ['2026-10-12', 'Encuentro de Dos Mundos', false],
        ['2026-10-31', 'Día de las Iglesias Evangélicas y Protestantes', false],
        ['2026-11-01', 'Día de Todos los Santos', false],
        ['2026-12-08', 'Inmaculada Concepción', false],
        ['2026-12-25', 'Navidad', true],
    ];

    public function run(): void
    {
        foreach (self::HOLIDAYS_2026 as [$date, $name, $mandatory]) {
            Holiday::updateOrCreate(
                [
                    'organization_id' => null,
                    'country' => 'cl',
                    'date' => $date,
                ],
                [
                    'name' => $name,
                    'mandatory' => $mandatory,
                ],
            );
        }
    }
}
