<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Concerns\FormatedRut;
use App\Observers\UserObserver;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
 * @property int|null $premise_id
 * @property int|null $supervisor_id
 * @property string $name
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $second_last_name
 * @property string|null $rut
 * @property string $email
 * @property string|null $personal_email
 * @property Carbon|null $contract_start_date
 * @property Carbon|null $contract_end_date
 * @property float $vacation_days
 * @property float $additional_vacation_days
 * @property float $administrative_days
 * @property bool $has_additional_sundays
 * @property string|null $nationality
 * @property string|null $gender
 * @property string|null $phone
 * @property string|null $emergency_contact_name
 * @property string|null $emergency_contact_phone
 * @property string $timezone
 * @property bool $is_dt
 * @property bool $is_active
 * @property bool $is_legal_rep
 * @property bool $is_admin
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property Carbon|null $password_changed_at
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string|null $avatar
 * @property-read string|null $formatted_rut
 */
#[Fillable(['name', 'first_name', 'last_name', 'second_last_name', 'rut', 'email', 'personal_email', 'password', 'is_dt', 'is_active', 'is_legal_rep', 'is_admin', 'password_changed_at', 'organization_id', 'company_id', 'position_id', 'premise_id', 'supervisor_id', 'contract_start_date', 'contract_end_date', 'vacation_days', 'additional_vacation_days', 'administrative_days', 'has_additional_sundays', 'nationality', 'gender', 'phone', 'emergency_contact_name', 'emergency_contact_phone', 'timezone'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
#[ObservedBy(UserObserver::class)]
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

    /**
     * @return BelongsTo<Premise, $this>
     */
    public function premise(): BelongsTo
    {
        return $this->belongsTo(Premise::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    /**
     * @return HasMany<ShiftAssignment, $this>
     */
    public function shiftAssignments(): HasMany
    {
        return $this->hasMany(ShiftAssignment::class);
    }

    /**
     * @return BelongsToMany<Shift, $this>
     */
    public function shifts(): BelongsToMany
    {
        return $this->belongsToMany(Shift::class, 'shift_assignments', 'user_id', 'shift_id');
    }

    protected function avatar(): Attribute
    {
        return Attribute::get(fn () => $this->getFirstMediaUrl('avatar') ?: null);
    }

    /**
     * Scope the query to employees of the current organization: users that
     * carry the `employee` role and are not legal representatives.
     *
     * @param  Builder<User>  $query
     */
    public function scopeEmployees(Builder $query): void
    {
        $query
            ->where('is_legal_rep', false)
            ->whereHas('roles', fn (Builder $roles) => $roles->where('name', 'employee'));
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
            'contract_start_date' => 'date',
            'contract_end_date' => 'date',
            'vacation_days' => 'float',
            'additional_vacation_days' => 'float',
            'administrative_days' => 'float',
            'has_additional_sundays' => 'boolean',
            'is_dt' => 'boolean',
            'is_active' => 'boolean',
            'is_legal_rep' => 'boolean',
            'is_admin' => 'boolean',
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
