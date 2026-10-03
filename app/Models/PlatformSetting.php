<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Platform-wide configuration (KOL-137.2), exactly one row. Currently holds
 * only the baseline used to default an organization's email limits; read
 * through {@see current()} rather than queried directly, so callers never
 * have to think about the row not existing yet.
 *
 * @property int $id
 * @property int|null $expected_emails_per_user_per_month
 */
#[Fillable(['expected_emails_per_user_per_month'])]
class PlatformSetting extends Model
{
    public static function current(): self
    {
        // Not firstOrCreate(['id' => 1]): 'id' is deliberately outside
        // #[Fillable], so create() would silently drop it and, since MySQL's
        // auto-increment counter does not roll back with a test transaction,
        // the row this creates would drift away from id 1 and never be found
        // again. Looking up the single existing row by nothing but its
        // existence sidesteps that entirely.
        return static::query()->first() ?? static::query()->create();
    }
}
