<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Position;
use App\Models\User;
use Illuminate\Database\Seeder;

class PositionSeeder extends Seeder
{
    /**
     * Seed a realistic set of cargos (positions) for the demo organization and
     * assign one to every demo employee, so the Cargos list shows meaningful
     * employee counts and each employee record displays a cargo.
     *
     * Must run after UserSeeder, which creates the demo organization and its
     * employees.
     */
    public function run(): void
    {
        $organization = Organization::query()
            ->where('slug', 'demo-organization')
            ->first();

        if ($organization === null) {
            return;
        }

        $names = [
            'Gerente General',
            'Jefe de Administración y Finanzas',
            'Contador',
            'Analista de Recursos Humanos',
            'Supervisor de Operaciones',
            'Vendedor',
            'Cajero',
            'Bodeguero',
            'Asistente Administrativo',
            'Guardia de Seguridad',
        ];

        $positions = collect($names)->map(function (string $name) use ($organization): Position {
            $position = new Position(['name' => $name]);
            $position->organization_id = $organization->id;
            $position->save();

            return $position;
        })->keyBy('name');

        // Weight the distribution so a few cargos concentrate most employees
        // (as a real retail org would), leaving the rest with one each. This
        // makes the Cargos list's avatar overflow ("+N") visible in the demo.
        $weights = ['Vendedor' => 6, 'Cajero' => 3];

        $assignmentPool = collect($names)->flatMap(fn (string $name) => array_fill(
            0,
            $weights[$name] ?? 1,
            $positions[$name],
        ))->values();

        // Give every demo employee a cargo so the Cargos list has employee
        // counts and the Employees list shows each worker's cargo.
        User::query()
            ->employees()
            ->where('organization_id', $organization->id)
            ->orderBy('id')
            ->get()
            ->each(fn (User $employee, int $index) => $employee->update([
                'position_id' => $assignmentPool[$index % $assignmentPool->count()]->id,
            ]));
    }
}
