<?php

namespace Database\Seeders;

use App\Constants;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\User;
use App\Models\Event;
use App\Models\EventType;
use Illuminate\Support\Arr;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $types = [];

        foreach (Constants::TYPES as $type => $description) {
            // dump("Creating category: $type");
            $eventType = new EventType();
            $eventType->name = $type;
            $eventType->description = $description;
            $eventType->save();

            $types[] = $eventType;
        }

        $users = User::factory(2500)->create();

        // Most users will be attendees, create events for only some of them
        foreach ($users as $user) {
            if ($user->id % 25 === 0) {
                $user->update(['role' => 'organizer']);

                //Future events
                Event::factory()->count(random_int(2, 20))->make()->each(function (Event $event) use ($user, $types) {
                    $event->organizer()->associate($user);
                    $event->type()->associate(Arr::random($types));
                    $event->save();
                });
                //Past events
                Event::factory()->count(random_int(0, 5))->finished()->make()->each(function (Event $event) use ($user, $types) {
                    $event->organizer()->associate($user);
                    $event->type()->associate(Arr::random($types));
                    $event->save();
                });

                // Private events
                if ($user->id % 100 === 0) {
                    Event::factory()->count(random_int(2, 5))->make(['public' => 0])->each(function (Event $event) use ($user, $types) {
                        $event->organizer()->associate($user);
                        $event->type()->associate(Arr::random($types));
                        $event->save();
                    });
                }
            }
        }

        $johnAdmin = User::factory()->johnAdmin()->create();
        $johnOrganizer = User::factory()->johnOrganizer()->create();
        $johnBasic = User::factory()->johnBasic()->create();

        Event::factory()->count(20)->make()->each(function (Event $event) use ($johnOrganizer, $types) {
            $event->organizer()->associate($johnOrganizer);
            $event->type()->associate(Arr::random($types));
            $event->save();
        });
        Event::factory()->count(5)->finished()->make()->each(function (Event $event) use ($johnOrganizer, $types) {
            $event->organizer()->associate($johnOrganizer);
            $event->type()->associate(Arr::random($types));
            $event->save();
        });
        Event::factory()->count(5)->make(['public' => 0])->each(function (Event $event) use ($johnOrganizer, $types) {
            $event->organizer()->associate($johnOrganizer);
            $event->type()->associate(Arr::random($types));
            $event->save();
        });

        $events = Event::select('id', 'cost')->get();

        foreach ($events as $event) {
            $users = $users->shuffle();
            $ids = $users->slice(0, random_int(1, 50))->pluck('id');

            if(!$event->public) {
                // If the event is private, create invites for the attendees
                $event->invites()->attach($ids);
            }

            $event->attendees()->attach($ids);
        }

        // John Doe will attend 25 random events for free
        $johnBasic->attending()->attach($events->random(number: 25));
    }
}
