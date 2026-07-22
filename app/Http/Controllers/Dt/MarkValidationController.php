<?php

namespace App\Http\Controllers\Dt;

use App\Http\Controllers\Controller;
use App\Models\Mark;
use App\Models\Scopes\OrganizationScope;
use App\Support\Rut;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Checksum verification for Labor Department (DT) inspectors.
 *
 * An inspector pastes the SHA-256 checksum printed on an attendance proof and
 * the matching mark's legal snapshot is returned, satisfying the integrity
 * verification required by Resolución 38 (Art. 11).
 */
class MarkValidationController extends Controller
{
    /**
     * Render the checksum form, along with the mark found by a prior lookup
     * when one was flashed to the session.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('dt/marks/validate', [
            'mark' => $request->session()->get('mark'),
        ]);
    }

    /**
     * Look up the mark by its checksum. Inspectors are not tenant-bound, so
     * {@see OrganizationScope} resolves no organization and the search spans
     * every employer — a DT inspector verifies proofs from any of them. A match
     * is flashed back to the form; a miss returns a validation error.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'checksum' => ['required', 'string'],
        ]);

        $mark = Mark::query()
            ->where('checksum', $validated['checksum'])
            ->first();

        if (! $mark instanceof Mark) {
            return back()->withErrors([
                'checksum' => __('ui.dt.marks.validate.not_found'),
            ]);
        }

        return to_route('dt.marks.validate')->with('mark', $this->present($mark));
    }

    /**
     * Shape the mark's immutable legal snapshot into the label/value pairs the
     * inspector infolist renders.
     *
     * @return array{
     *     employee_name: string|null,
     *     employee_rut: string|null,
     *     employer_name: string|null,
     *     employer_rut: string|null,
     *     date_time: string,
     *     type: string,
     *     premise_name: string|null,
     *     premise_address: string|null,
     *     coordinates: string|null,
     *     checksum: string,
     * }
     */
    private function present(Mark $mark): array
    {
        $coordinates = $mark->lat !== null && $mark->lng !== null
            ? $mark->lat.', '.$mark->lng
            : null;

        return [
            'employee_name' => $mark->employee_name,
            'employee_rut' => $mark->employee_rut === null ? null : Rut::format($mark->employee_rut),
            'employer_name' => $mark->employer_name,
            'employer_rut' => $mark->employer_rut === null ? null : Rut::format($mark->employer_rut),
            'date_time' => $mark->date_time->format('d-m-Y H:i:s'),
            'type' => $mark->type->label(),
            'premise_name' => $mark->premise_name,
            'premise_address' => $mark->premise_address,
            'coordinates' => $coordinates,
            'checksum' => $mark->checksum,
        ];
    }
}
