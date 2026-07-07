<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\PremiseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $organization_id
 * @property int|null $company_id
 * @property string $name
 * @property string|null $code
 * @property string|null $country
 * @property string|null $region
 * @property string|null $commune
 * @property string|null $address
 * @property float|null $lat
 * @property float|null $lng
 * @property string|null $responsable_name
 * @property string|null $responsable_email
 * @property string|null $responsable_phone
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'company_id',
    'name',
    'code',
    'country',
    'region',
    'commune',
    'address',
    'lat',
    'lng',
    'responsable_name',
    'responsable_email',
    'responsable_phone',
])]
class Premise extends Model
{
    /** @use HasFactory<PremiseFactory> */
    use BelongsToOrganization, HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
        ];
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

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
