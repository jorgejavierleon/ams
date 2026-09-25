<?php

namespace App\Http\Requests\Settings;

use App\Http\Controllers\Api\TokenController as MobileTokenController;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class PersonalAccessTokenStoreRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string|Closure>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', $this->uniquePerUser()],
        ];
    }

    /**
     * A name must be unique among the authenticated user's own tokens, so a
     * second token with the same name can't silently replace (and revoke
     * access for) an existing one — unlike the mobile app's one-token-per-
     * device flow in {@see MobileTokenController}, this is a set of
     * independent, permanent credentials.
     */
    private function uniquePerUser(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($this->user()->tokens()->where('name', $value)->exists()) {
                $fail(__('ui.settings.security.tokens.name_taken'));
            }
        };
    }
}
