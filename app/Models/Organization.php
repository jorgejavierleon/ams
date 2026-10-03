<?php

namespace App\Models;

use App\Enums\Plan;
use App\Models\Concerns\FormatedRut;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string|null $rut
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $address
 * @property string $slug
 * @property Plan $plan
 * @property int|null $soft_email_limit_override
 * @property int|null $hard_email_limit_override
 * @property int|null $owner_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read string|null $formatted_rut
 * @property-read User|null $owner
 */
#[Fillable(['name', 'rut', 'email', 'phone', 'address', 'slug', 'plan'])]
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use FormatedRut, HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'plan' => Plan::class,
        ];
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

    /**
     * @return HasMany<EmailSend, $this>
     */
    public function emailSends(): HasMany
    {
        return $this->hasMany(EmailSend::class);
    }

    /**
     * The effective monthly soft email limit (KOL-137.2): the admin's saved
     * override when there is one, otherwise {@see defaultEmailLimit()}.
     */
    public function softEmailLimit(): int
    {
        return $this->soft_email_limit_override ?? $this->defaultEmailLimit();
    }

    /**
     * The effective monthly hard email limit (KOL-137.2). See {@see softEmailLimit()}.
     */
    public function hardEmailLimit(): int
    {
        return $this->hard_email_limit_override ?? $this->defaultEmailLimit();
    }

    /**
     * active_users_count × the platform-wide baseline (KOL-137.2), computed
     * live so a baseline change immediately reaches every organization that
     * has never had this figure overridden.
     */
    public function defaultEmailLimit(): int
    {
        return $this->activeUsers()->count() * (PlatformSetting::current()->expected_emails_per_user_per_month ?? 0);
    }

    /**
     * The one user who unconditionally bypasses every authorization check in
     * this organization (KOL-133), regardless of role or permission changes.
     * Deliberately not in #[Fillable]: ownership must only change through a
     * dedicated transfer action (KOL-133.2), never generic mass assignment.
     *
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
