<?php

namespace App\Models;

use App\Support\CurrentOrganization;
use Database\Factories\TotemDeviceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

/**
 * An unattended Totem tablet/phone bound to one premise. It is its own Sanctum
 * principal, distinct from User, so no employee's credentials live on shared
 * hardware. Deliberately does NOT use BelongsToOrganization: Sanctum loads
 * this model as a token's `tokenable` while resolving the `sanctum` guard, and
 * the tenant scope asks that same guard for the current user, which recurses
 * without end. Callers scope with {@see self::scopeInCurrentOrganization()}.
 * Its token carries only {@see self::PUNCH_ABILITY}; revoking the
 * device deletes the token and soft-deletes the row, keeping the audit trail.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $premise_id
 * @property string $name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read string|null $last_used_at Latest token use, present when loaded with withMax().
 */
#[Fillable(['premise_id', 'name'])]
class TotemDevice extends Authenticatable
{
    /** @use HasFactory<TotemDeviceFactory> */
    use HasApiTokens, HasFactory, SoftDeletes;

    public const PUNCH_ABILITY = 'kiosk:punch';

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @param  Builder<TotemDevice>  $query
     * @return Builder<TotemDevice>
     */
    public function scopeInCurrentOrganization(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('organization_id'), CurrentOrganization::id());
    }

    /**
     * Route binding never reaches another organization's device.
     *
     * @param  mixed  $value
     * @param  string|null  $field
     */
    public function resolveRouteBinding($value, $field = null): ?self
    {
        return $this->newQuery()->inCurrentOrganization()
            ->where($field ?? $this->getRouteKeyName(), $value)
            ->first();
    }

    /**
     * @return BelongsTo<Premise, $this>
     */
    public function premise(): BelongsTo
    {
        return $this->belongsTo(Premise::class);
    }

    /**
     * Mints the device's only credential.
     */
    public function issuePunchToken(): string
    {
        return $this->createToken($this->name, [self::PUNCH_ABILITY])->plainTextToken;
    }

    /**
     * Invalidates the device's token immediately and removes it from the list.
     */
    public function revoke(): void
    {
        $this->tokens()->delete();
        $this->delete();
    }
}
