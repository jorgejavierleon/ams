<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Premise;
use App\Models\TotemDevice;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Provisions the Totem kiosk app: an admin proves who they are with their own
 * credentials, picks a premise, and the app receives a device-level token that
 * is good only for kiosk:punch. The admin's credentials are never stored.
 */
class TotemDeviceController extends Controller
{
    /**
     * @throws ValidationException
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'premise_id' => ['required', 'integer'],
            'device_name' => ['required', 'string', 'max:255'],
        ]);

        $admin = User::where('email', $validated['email'])->first();

        if ($admin === null || ! Hash::check($validated['password'], $admin->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        if (! $admin->is_active) {
            throw ValidationException::withMessages([
                'email' => [__('auth.inactive')],
            ]);
        }

        abort_unless($admin->can('Manage:TotemDevice'), 403);

        // Unauthenticated request, so the tenant scope is inactive: pin the
        // lookup to the admin's own organization explicitly.
        $premise = Premise::withoutGlobalScopes()
            ->where('organization_id', $admin->organization_id)
            ->find($request->integer('premise_id'));

        if ($premise === null) {
            throw ValidationException::withMessages([
                'premise_id' => [__('validation.exists', ['attribute' => 'premise_id'])],
            ]);
        }

        $device = new TotemDevice(['premise_id' => $premise->id, 'name' => $validated['device_name']]);
        $device->organization_id = $premise->organization_id;
        $device->save();

        return response()->json([
            'token' => $device->issuePunchToken(),
            'device' => [
                'id' => $device->id,
                'name' => $device->name,
                'premise' => ['id' => $premise->id, 'name' => $premise->name],
            ],
        ], 201);
    }

    /**
     * Lets the app confirm its stored token is still valid and which premise
     * it is bound to.
     */
    public function show(Request $request): JsonResponse
    {
        /** @var TotemDevice $device */
        $device = $request->user();
        $device->loadMissing('premise:id,name');

        return response()->json([
            'id' => $device->id,
            'name' => $device->name,
            'premise' => ['id' => $device->premise_id, 'name' => $device->premise->name],
        ]);
    }
}
