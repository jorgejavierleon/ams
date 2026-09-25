<?php

namespace App\Actions;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The only path by which Organization::owner_id ever changes (KOL-133.2), per
 * Organization::owner()'s docblock: ownership is never generic mass
 * assignment. A single-column update is already atomic, so the transaction
 * here exists to pair it with the audit log entry.
 */
class TransferOrganizationOwnership
{
    public function handle(Organization $organization, User $newOwner): void
    {
        if ($newOwner->organization_id !== $organization->id) {
            throw ValidationException::withMessages([
                'user_id' => __('ui.settings.ownership.errors.outside_organization'),
            ]);
        }

        if (! $newOwner->is_active) {
            throw ValidationException::withMessages([
                'user_id' => __('ui.settings.ownership.errors.inactive_user'),
            ]);
        }

        if ($newOwner->id === $organization->owner_id) {
            throw ValidationException::withMessages([
                'user_id' => __('ui.settings.ownership.errors.already_owner'),
            ]);
        }

        DB::transaction(function () use ($organization, $newOwner): void {
            $previousOwnerId = $organization->owner_id;

            $organization->forceFill(['owner_id' => $newOwner->id])->save();

            activity()
                ->causedBy(Auth::user())
                ->performedOn($organization)
                ->event('ownership_transferred')
                ->withProperties([
                    'old' => ['owner_id' => $previousOwnerId],
                    'attributes' => ['owner_id' => $newOwner->id],
                ])
                ->log(__('ui.settings.ownership.activity.transferred'));
        });
    }
}
