<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Observers\SettingObserver;
use App\Services\OrganizationSettings;
use Database\Factories\SettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Per-organization configuration: which notification emails the platform sends
 * and the document-signing defaults. Exactly one row exists per organization,
 * scoped by {@see BelongsToOrganization}. Reads should go through
 * {@see OrganizationSettings}, which caches the row and is
 * invalidated by {@see SettingObserver} on every change.
 *
 * @property int $id
 * @property int $organization_id
 * @property bool $employee_missing_in_notification
 * @property bool $employee_missing_out_notification
 * @property bool $employer_missing_in_notification
 * @property bool $employer_missing_out_notification
 * @property bool $leave_approval_notification
 * @property bool $documents_signature_enabled
 * @property bool $documents_require_ordered_signing
 */
#[Fillable([
    'employee_missing_in_notification',
    'employee_missing_out_notification',
    'employer_missing_in_notification',
    'employer_missing_out_notification',
    'leave_approval_notification',
    'documents_signature_enabled',
    'documents_require_ordered_signing',
])]
#[ObservedBy(SettingObserver::class)]
class Setting extends Model
{
    /** @use HasFactory<SettingFactory> */
    use BelongsToOrganization, HasFactory;

    /**
     * Default values matching the migration, so a row created for a new
     * organization (via `firstOrCreate`) carries the correct booleans in memory
     * without a reload.
     *
     * @var array<string, bool>
     */
    protected $attributes = [
        'employee_missing_in_notification' => true,
        'employee_missing_out_notification' => true,
        'employer_missing_in_notification' => true,
        'employer_missing_out_notification' => true,
        'leave_approval_notification' => true,
        'documents_signature_enabled' => false,
        'documents_require_ordered_signing' => false,
    ];

    protected function casts(): array
    {
        return [
            'employee_missing_in_notification' => 'boolean',
            'employee_missing_out_notification' => 'boolean',
            'employer_missing_in_notification' => 'boolean',
            'employer_missing_out_notification' => 'boolean',
            'leave_approval_notification' => 'boolean',
            'documents_signature_enabled' => 'boolean',
            'documents_require_ordered_signing' => 'boolean',
        ];
    }
}
