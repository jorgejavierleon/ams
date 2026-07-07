<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Concerns\FormatedRut;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property int|null $organization_id
 * @property int|null $company_id
 * @property int|null $position_id
 * @property string $name
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $second_last_name
 * @property string|null $rut
 * @property string $email
 * @property string|null $personal_email
 * @property bool $is_dt
 * @property bool $is_active
 * @property bool $is_legal_rep
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property Carbon|null $password_changed_at
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'first_name', 'last_name', 'second_last_name', 'rut', 'email', 'personal_email', 'password', 'is_dt', 'is_active', 'is_legal_rep', 'password_changed_at', 'organization_id', 'company_id', 'position_id'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements HasMedia
{
    /** @use HasFactory<UserFactory> */
    use FormatedRut, HasFactory, HasRoles, InteractsWithMedia, Notifiable;

    protected $appends = ['avatar'];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('avatar')->singleFile();
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<Position, $this>
     */
    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    protected function avatar(): Attribute
    {
        return Attribute::get(fn () => $this->getFirstMediaUrl('avatar') ?: null);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'password' => 'hashed',
            'is_dt' => 'boolean',
            'is_active' => 'boolean',
            'is_legal_rep' => 'boolean',
        ];
    }

    public function hasActivePassword(): bool
    {
        if (is_null($this->password_changed_at)) {
            return true;
        }

        return $this->password_changed_at->diffInDays(Carbon::now()) <= config('auth.passwords_expires_days');
    }
}
