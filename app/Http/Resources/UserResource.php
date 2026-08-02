<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The mobile API representation of the signed-in employee: the identity fields
 * the app renders plus the effective permission names it gates features on.
 * Permissions are always a flat array — never null, never absent — so the
 * client can fail closed on an unknown ability.
 *
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array{id: int, name: string, first_name: string|null, last_name: string|null, rut: string|null, email: string, avatar: string|null, permissions: array<int, string>}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'rut' => $this->rut,
            'email' => $this->email,
            'avatar' => $this->avatar,
            'permissions' => $this->getAllPermissions()->pluck('name')->values()->all(),
        ];
    }
}
