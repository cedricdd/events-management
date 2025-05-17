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
        $users = User::factory(2500)->create();

        // Most users will be attendees, create events for only some of them
        foreach ($users as $user) {
            if ($user->id % 25 === 0) { // 10% of users
                $user->update(['role' => 'organizer']);

                //Future events
                Event::factory()->count(random_int(2, 20))->for($user, 'organizer')->create();
                //Past events
                Event::factory()->count(random_int(0, 5))->finished()->for($user, 'organizer')->create();
            }
        }

        $johnAdmin = User::factory()->johnAdmin()->create();
        $johnOrganizer = User::factory()->johnOrganizer()->create();
        $johnBasic = User::factory()->johnBasic()->create();

        Event::factory()->count(10)->for($johnOrganizer, 'organizer')->create();
        Event::factory()->count(5)->finished()->for($johnOrganizer, 'organizer')->create();

        $events = Event::select('id', 'cost')->get();

        foreach ($events as $event) {
            $users = $users->shuffle();
            $attendees = random_int(1, max: 50);

            foreach ($users as $user) {
                if ($user->tokens >= $event->cost) {
                    $event->attendees()->attach($user);
                    $user->decrement('tokens', $event->cost);

                    if (--$attendees == 0) break;
                }
            }
        }

        // John Doe will attend 25 random events for free
        $johnBasic->attending()->attach($events->random(number: 25));
    }
}
