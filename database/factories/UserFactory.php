<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
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
            'country' => fake()->country(),
            'profession' => fake()->jobTitle(),
            'phone' => fake()->phoneNumber(),
            'organization' => fake()->company(),
            'tokens' => random_int(10, 999),
            'tokens_spend' => random_int(0, 999),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn(array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function johnAdmin(): static
    {
        return $this->state(fn(array $attributes) => [
            'name' => 'John Doe',
            'email' => "john@admin.com",
            'role' => 'admin',
        ]);
    }

    public function johnOrganizer(): static
    {
        return $this->state(fn(array $attributes) => [
            'name' => 'John Doe',
            'email' => "john@organizer.com",
            'role' => 'organizer',
        ]);
    }

    public function johnBasic(): static
    {
        return $this->state(fn(array $attributes) => [
            'name' => 'John Doe',
            'email' => "john@basic.com",
        ]);
    }
}
