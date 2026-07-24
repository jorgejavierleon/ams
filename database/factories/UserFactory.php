<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Fortify\RecoveryCode;
use Laravel\Fortify\TwoFactorAuthenticationProvider;
use Spatie\Permission\Models\Role;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes): array => [
            'two_factor_secret' => encrypt(app(TwoFactorAuthenticationProvider::class)->generateSecretKey()),
            'two_factor_recovery_codes' => encrypt(json_encode(
                Collection::times(8, fn (): string => RecoveryCode::generate())->all()
            )),
            'two_factor_confirmed_at' => now(),
        ]);
    }

    /**
     * Indicate that the user is an organization employee.
     *
     * Requires an `organization_id` to be provided (employees are tenant
     * scoped); assigns the `employee` role after creation.
     */
    public function employee(): static
    {
        return $this
            ->state(function (array $attributes): array {
                $firstName = fake()->firstName();
                $lastName = fake()->lastName();

                return [
                    'name' => "{$firstName} {$lastName}",
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'rut' => fake()->numerify('########').'-'.fake()->randomElement(['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'K']),
                    'personal_email' => fake()->unique()->safeEmail(),
                    'timezone' => 'America/Santiago',
                    'is_active' => true,
                ];
            })
            ->afterCreating(function (User $user): void {
                Role::firstOrCreate(['name' => 'employee', 'guard_name' => 'web']);
                $user->assignRole('employee');
            });
    }

    /**
     * Indicate that the user is a DT inspector.
     */
    public function dtUser(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_dt' => true,
            'password_changed_at' => now(),
        ]);
    }

    /**
     * Indicate that the user is a SaaS super-admin.
     */
    public function saasUser(): static
    {
        return $this->afterCreating(function (User $user): void {
            Role::firstOrCreate(['name' => 'saas', 'guard_name' => 'web']);
            $user->assignRole('saas');
        });
    }
}
