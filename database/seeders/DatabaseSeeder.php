<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\Event;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory(2500)->create();

        $users = User::select('id')->get();

        // Most users will be attendees, create events for only some of them
        foreach($users as $user) {
            if ($user->id % 25 === 0) { // 10% of users
                Event::factory()->count(random_int(2, 20))->for($user, 'user')->create();
                Event::factory()->count(random_int(0, 5))->finished()->for($user, 'user')->create();
            }
        }

        $john = User::factory()->johnDoe()->create();

        Event::factory()->count(10)->for($john, 'user')->create();
        Event::factory()->count(5)->finished()->for($john, 'user')->create();

        $events = Event::select('id')->get();

        // Each user will attend 1 to 10 events
        foreach($users as $user) {
            $user->attending()->attach($events->random(random_int(1, 50)));
        }

        $john->attending()->attach($events->random(number: 10));
    }
}
