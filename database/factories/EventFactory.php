<?php

namespace Database\Factories;

use DateTime;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Event>
 */
class EventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => $this->faker->sentence,
            'description' => $this->faker->paragraph,
            'location' => random_int(0, 1) ? $this->faker->city : 'Online',
            'start_date' => ($start = $this->getRandomDateTime('+1 week', '+1 month')),
            'end_date' => (clone $start)->modify('+' . (30 * random_int(1, 1440)) . ' minutes'),
            'cost' => random_int(1, 9), 
            'is_public' => true,
            'created_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'event_type_id' => 1, 
        ];
    }

    public function finished(): static
    {
        return $this->state(fn (array $attributes) => [
            'start_date' => ($start = $this->getRandomDateTime('-1 year', '-1 month')),
            'end_date' => (clone $start)->modify('+' . (30 * random_int(1, 1440)) . ' minutes'),
        ]);
    }

    private function getRandomDateTime(string $start, string $end): DateTime
    {
        $date = $this->faker->dateTimeBetween($start, $end);

        //We only want events that start at 00:00 or 30 minutes past the hour
        $minute = (int) $date->format('i');
        $date->setTime((int) $date->format('H'), ($minute >= 15 && $minute <= 45) ? 30 : 0, 0);

        return $date;
    }
}
