<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\CostCenterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An accounting bucket the payroll reports segment by — *centro de costo*.
 *
 * Not an employer: {@see Company} is the legal entity, and a tenant has exactly
 * one. A cost centre has no RUT and must never be substituted for the employer
 * on a Resolución 38 report.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property string $name
 * @property string|null $code
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'code'])]
class CostCenter extends Model
{
    /** @use HasFactory<CostCenterFactory> */
    use BelongsToOrganization, HasFactory;

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasMany<User, $this>
     */
    public function activeUsers(): HasMany
    {
        return $this->users()->where('is_active', true);
    }
}
