<?php

use App\Constants;

test('attendees_index', function () {
    $countPage = 2;
    $event = $this->getEvents(count: 1, attendeesCount: Constants::ATTENDEES_PER_PAGE * $countPage);

    $attendees = $event->attendees->sortBy(['name', 'asc']);
    $attendeeFirst = $attendees->first();
    $attendeeLast = $attendees->last();

    $this->get(route('attendees.index', $event))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'email',
                ],
            ],
            'meta' => [
                'current_page',
                'last_page',
                'per_page',
                'total',
                'path',
            ],
            'links' => [
                'first',
                'last',
                'prev',
                'next',
            ],
            'event' => [
                'id',
                'name',
                'description',
                'start_date',
                'end_date',
                'price',
                'location',
                'is_public',
                'user' => [
                    'id',
                    'name',
                    'email',
                ],
            ],
        ])
        ->assertJsonFragment([
            'current_page' => 1,
            'last_page' => $countPage,
            'per_page' => Constants::ATTENDEES_PER_PAGE,
            'total' => Constants::ATTENDEES_PER_PAGE * $countPage,
        ])
        ->assertJsonFragment([
            [
                'id' => $attendeeFirst->id,
                'name' => $attendeeFirst->name,
                'email' => $attendeeFirst->email,
            ],
            'event' => [
                'id' => $event->id,
                'name' => $event->name,
                'description' => $event->description,
                'start_date' => $event->start_date->format('Y-m-d H:i:s'),
                'end_date' => $event->end_date->format('Y-m-d H:i:s'),
                'price' => $event->price,
                'location' => $event->location,
                'is_public' => $event->is_public ? 1 : 0,
                'user' => [
                    'id' => $event->user->id,
                    'name' => $event->user->name,
                    'email' => $event->user->email,
                ],
            ],
        ])
        ->assertJsonMissing([
            [
                'id' => $attendeeLast->id,
                'name' => $attendeeLast->name,
                'email' => $attendeeLast->email,
            ]
        ]);


    $this->get(route('attendees.index', [$event, 'page' => $countPage]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonFragment([
            [
                'id' => $attendeeLast->id,
                'name' => $attendeeLast->name,
                'email' => $attendeeLast->email,
            ]
        ])
        ->assertJsonMissing([
            [
                'id' => $attendeeFirst->id,
                'name' => $attendeeFirst->name,
                'email' => $attendeeFirst->email,
            ]
        ]);
});
