<?php

namespace App\Models;

use App\Actions\CorrectLegalHourLimit;
use App\Exceptions\LegalHourLimitIsAppendOnly;
use App\Services\LegalHourLimits;
use Database\Factories\LegalHourLimitFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One version of Chile's legal working-hour limits, in force from
 * `effective_from` until the next version starts.
 *
 * These are **global**: there is no `organization_id` and no per-tenant
 * override. They are the law, identical for every employer in the country.
 * Tenant code reads them through {@see LegalHourLimits} and has no write path;
 * maintenance happens in the SaaS panel.
 *
 * Versions are **append-only**. Creating one goes through
 * {@see LegalHourLimitVersions::add()}, updating one only through
 * {@see CorrectLegalHourLimit}, and deleting one is refused
 * outright — by this model, and by the `restrict` foreign key on `workdays`.
 *
 * @property int $id
 * @property Carbon $effective_from
 * @property float $ordinary_weekly_hours
 * @property float $ordinary_daily_hours
 * @property float $max_overtime_daily_hours
 * @property float $max_overtime_weekly_hours
 * @property float $max_total_daily_hours
 * @property float $max_total_weekly_hours
 * @property string $legal_reference
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class LegalHourLimit extends Model
{
    /** @use HasFactory<LegalHourLimitFactory> */
    use HasFactory;

    /**
     * The columns a version is made of. Not a `$fillable` convenience: the
     * correction flow validates against this list so a correction cannot reach
     * a column that is not part of the legal figures.
     *
     * @var list<string>
     */
    public const FIGURES = [
        'effective_from',
        'ordinary_weekly_hours',
        'ordinary_daily_hours',
        'max_overtime_daily_hours',
        'max_overtime_weekly_hours',
        'max_total_daily_hours',
        'max_total_weekly_hours',
        'legal_reference',
        'notes',
    ];

    /**
     * Which write, if any, the two sanctioned flows have unlocked for the
     * current call. Everything else is refused.
     */
    private static ?string $unlockedWrite = null;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'ordinary_weekly_hours' => 'float',
            'ordinary_daily_hours' => 'float',
            'max_overtime_daily_hours' => 'float',
            'max_overtime_weekly_hours' => 'float',
            'max_total_daily_hours' => 'float',
            'max_total_weekly_hours' => 'float',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (): void {
            if (self::$unlockedWrite !== 'create') {
                throw LegalHourLimitIsAppendOnly::cannotCreate();
            }
        });

        static::updating(function (): void {
            if (self::$unlockedWrite !== 'correct') {
                throw LegalHourLimitIsAppendOnly::cannotUpdate();
            }
        });

        // No unlock exists for deletion. A version a day was judged against has
        // to remain readable for that day to stay explicable.
        static::deleting(function (): void {
            throw LegalHourLimitIsAppendOnly::cannotDelete();
        });
    }

    /**
     * Run the callback with appends permitted. Called only by
     * {@see LegalHourLimitVersions::add()}.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public static function whileAppending(callable $callback): mixed
    {
        return self::whileUnlocked('create', $callback);
    }

    /**
     * Run the callback with edits permitted. Called only by
     * {@see CorrectLegalHourLimit}, which recalculates the days
     * the corrected version was applied to.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public static function whileCorrecting(callable $callback): mixed
    {
        return self::whileUnlocked('correct', $callback);
    }

    /**
     * The computed days that were judged against this version.
     *
     * @return HasMany<Workday, $this>
     */
    public function workdays(): HasMany
    {
        return $this->hasMany(Workday::class);
    }

    /**
     * Order versions oldest first, the order the timeline reads in.
     *
     * @param  Builder<LegalHourLimit>  $query
     */
    public function scopeChronological(Builder $query): void
    {
        $query->orderBy('effective_from');
    }

    /**
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    private static function whileUnlocked(string $write, callable $callback): mixed
    {
        $previous = self::$unlockedWrite;
        self::$unlockedWrite = $write;

        try {
            return $callback();
        } finally {
            self::$unlockedWrite = $previous;
        }
    }
}
