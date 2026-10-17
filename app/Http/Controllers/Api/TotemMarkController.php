<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Managers\MarkManager;
use App\Models\TotemDevice;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Punches from a Totem kiosk. The device token says where the punch happens;
 * the employee identifies themselves with RUT and password, and the punch then
 * goes through exactly the same path as the mobile app's.
 */
class TotemMarkController extends Controller
{
    public function __construct(private readonly MarkController $marksApi) {}

    /**
     * @throws ValidationException
     */
    public function store(Request $request, MarkManager $marks): JsonResponse
    {
        $validated = $request->validate([
            'rut' => ['required', 'string'],
            'password' => ['required', 'string'],
            ...MarkController::punchRules(),
        ]);

        /** @var TotemDevice $device */
        $device = $request->user();

        $user = $this->identify($device, $validated['rut'], $validated['password']);

        // From here the request is the employee's own: anything recorded "by the
        // authenticated user" (e.g. the addition filed for an over-age queued
        // punch) must name them, not the device, which is not a row in `users`.
        Auth::setUser($user);

        // A kiosk is bolted to its premise, so with no fix of its own the punch
        // happened at the premise's coordinates.
        if (($validated['lat'] ?? null) === null || ($validated['lng'] ?? null) === null) {
            $validated['lat'] = $device->premise->lat;
            $validated['lng'] = $device->premise->lng;
            $validated['accuracy_m'] = null;
        }

        try {
            return $this->marksApi->recordPunch($user, $marks, $validated);
        } finally {
            Auth::setUser($device);
        }
    }

    /**
     * Wrong RUT, wrong password and a RUT from another premise all answer the
     * same way, so the kiosk cannot be used to discover who works where.
     *
     * @throws ValidationException
     */
    private function identify(TotemDevice $device, string $rut, string $password): User
    {
        $user = User::query()
            ->where('organization_id', $device->organization_id)
            ->where('premise_id', $device->premise_id)
            ->where('rut', $this->normalizeRut($rut))
            ->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages(['rut' => [__('auth.failed')]]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages(['rut' => [__('auth.inactive')]]);
        }

        return $user;
    }

    /**
     * RUTs are stored as `12345678-K`: drop the dots and spaces a person types.
     */
    private function normalizeRut(string $rut): string
    {
        return strtoupper(str_replace(['.', ' '], '', $rut));
    }
}
