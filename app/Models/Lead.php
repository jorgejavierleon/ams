<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A lead captured from the public landing page's contact form (KOL-139).
 * Organization-less by design: whoever submits it isn't a tenant yet,
 * they're asking to become one.
 *
 * @property int $id
 * @property string $name
 * @property string|null $company
 * @property string $email
 * @property string|null $message
 */
#[Fillable(['name', 'company', 'email', 'message'])]
class Lead extends Model
{
    //
}
