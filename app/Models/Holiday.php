<?php

namespace App\Models;

use App\Models\Scopes\HolidayScope;
use Database\Factories\HolidayFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Chilean public holiday used by the workday calculator to mark days as
 * HOLIDAY instead of ABSENT.
 *
 * A holiday is either **official** (`organization_id = null`) — the canonical
 * national list synced from Boostr and shared read-only with every tenant — or
 * **organization-owned**, a custom holiday a single organization added for
 * itself. {@see HolidayScope} exposes both to that organization.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property string $country
 * @property string $name
 * @property Carbon $date
 * @property bool $mandatory
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['organization_id', 'country', 'name', 'date', 'mandatory'])]
class Holiday extends Model
{
    /** @use HasFactory<HolidayFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::addGlobalScope(new HolidayScope);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'mandatory' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Official holidays belong to no organization and cannot be edited by tenants.
     */
    public function isOfficial(): bool
    {
        return $this->organization_id === null;
    }
}
