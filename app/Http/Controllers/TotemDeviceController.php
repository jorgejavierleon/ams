<?php

namespace App\Http\Controllers;

use App\Models\TotemDevice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class TotemDeviceController extends Controller
{
    public function index(): Response
    {
        $devices = TotemDevice::query()
            ->inCurrentOrganization()
            ->with('premise:id,name')
            ->withMax('tokens as last_used_at', 'last_used_at')
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('totem-devices/index', [
            'devices' => $devices->map(fn (TotemDevice $device) => [
                'id' => $device->id,
                'name' => $device->name,
                'premise' => $device->premise?->name,
                'created_at' => $device->created_at?->toIso8601String(),
                'last_used_at' => $device->last_used_at
                    ? Carbon::parse($device->last_used_at)->toIso8601String()
                    : null,
            ]),
        ]);
    }

    public function destroy(TotemDevice $totemDevice): RedirectResponse
    {
        $totemDevice->revoke();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.totem_devices.flash.revoked')]);

        return to_route('totem-devices.index');
    }
}
