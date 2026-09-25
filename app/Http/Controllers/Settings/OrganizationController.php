<?php

namespace App\Http\Controllers\Settings;

use App\Actions\TransferOrganizationOwnership;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();

        abort_unless($user->isOwner() || $user->hasRole('admin'), 403);

        $organization = $user->organization;

        $eligibleUsers = User::query()
            ->where('organization_id', $organization->id)
            ->where('is_active', true)
            ->when($organization->owner_id !== null, fn ($query) => $query->where('id', '!=', $organization->owner_id))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return Inertia::render('settings/organization', [
            'organization' => [
                'name' => $organization->name,
            ],
            'owner' => $organization->owner ? [
                'id' => $organization->owner->id,
                'name' => $organization->owner->name,
                'email' => $organization->owner->email,
            ] : null,
            'isOwner' => $user->isOwner(),
            'eligibleUsers' => $eligibleUsers,
        ]);
    }

    public function transfer(Request $request, TransferOrganizationOwnership $transferOwnership): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->isOwner(), 403);

        $data = $request->validate([
            'user_id' => [
                'required', 'integer',
                Rule::exists('users', 'id')->where('organization_id', $user->organization_id),
            ],
        ]);

        $transferOwnership->handle($user->organization, User::findOrFail($data['user_id']));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.settings.ownership.flash.transferred')]);

        return back();
    }
}
