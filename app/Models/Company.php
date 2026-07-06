<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\FormatedRut;
use Database\Factories\CompanyFactory;
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
 * @property string $rut
 * @property string $social_reason
 * @property string $business_line
 * @property string $email
 * @property string $country
 * @property int|null $region_id
 * @property int|null $commune_id
 * @property string $address
 * @property string $phone
 * @property string $company_type
 * @property bool $is_est
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'rut',
    'social_reason',
    'business_line',
    'email',
    'country',
    'region_id',
    'commune_id',
    'address',
    'phone',
    'company_type',
    'is_est',
    'is_active',
])]
class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use BelongsToOrganization, FormatedRut, HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (Company $company): void {
            $company->country ??= 'Chile';
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_est' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Legal representatives — company users flagged as such.
     *
     * @return HasMany<User, $this>
     */
    public function representatives(): HasMany
    {
        return $this->users()->where('is_legal_rep', true);
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return BelongsTo<Region, $this>
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /**
     * @return BelongsTo<Commune, $this>
     */
    public function commune(): BelongsTo
    {
        return $this->belongsTo(Commune::class);
    }
}
