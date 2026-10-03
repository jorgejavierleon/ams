<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A lead captured from the public landing page's demo-request form
 * (KOL-139). Organization-less by design: whoever submits it isn't a tenant
 * yet, they're asking to become one.
 *
 * @property int $id
 * @property string $email
 */
#[Fillable(['email'])]
class DemoRequest extends Model
{
    //
}
