<?php

namespace App\Models;

use Database\Factories\DocumentVarFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A global document placeholder resolved at render time by the document
 * template engine. Document variables are managed exclusively by the SaaS
 * super-admin and reused across every tenant organization, so — unlike most
 * models — they carry no organization scoping.
 *
 * The `key` is the literal token that appears in a template, stored with its
 * surrounding braces (e.g. `{{employee_name}}`), and must be globally unique.
 *
 * @property int $id
 * @property string $name
 * @property string $key
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'key', 'description'])]
class DocumentVar extends Model
{
    /** @use HasFactory<DocumentVarFactory> */
    use HasFactory;
}
