<?php

use App\Constants;
use App\Models\User;

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
        ])
        ->assertJsonCount(Constants::ATTENDEES_PER_PAGE, 'data')
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

test('attendees_index_with_event', function () {
    $event = $this->getEvents(count: 1, attendeesCount: 1);

    $attendee = $event->attendees->first();

    $this->get(route('attendees.index', [$event, 'with' => 'event']))
        ->assertValid()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonStructure([
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
        ]);
});

test('attendees_index_not_found', function () {
    $this->get(route('attendees.index', 10))
        ->assertValid()
        ->assertStatus(404)
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonFragment([
            'message' => 'Event not found',
        ]);
});

test('attendees_show', function () {
    $event = $this->getEvents(count: 1, attendeesCount: 1);

    $attendee = $event->attendees->first();

    $this->get(route('attendees.show', [$event, $attendee]))
        ->assertValid()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'email',
            ],
        ])
        ->assertJsonFragment([
            [
                'id' => $attendee->id,
                'name' => $attendee->name,
                'email' => $attendee->email,
            ]
        ]);
});

test('attendees_show_not_found', function () {
    $event = $this->getEvents(count: 1, attendeesCount: 1);

    $attendee = $event->attendees->first();

    // Test with a non-existing attendee
    $this->get(route('attendees.show', [$event, 10]))
        ->assertValid()
        ->assertStatus(404)
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonFragment([
            'message' => 'User not found',
        ]);

    // Test with a non-existing event
    $this->get(route('attendees.show', [10, $attendee]))
        ->assertValid()
        ->assertStatus(404)
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonFragment([
            'message' => 'Event not found',
        ]);
});

test('attendees_destroy', function () {
    $attendeesCount = 10;
    $event = $this->getEvents(count: 1, attendeesCount: $attendeesCount);

    $attendee = $event->attendees->first();

    // Remove the attendee from the event
    $this->delete(route('attendees.destroy', [$event, $attendee]))->assertNoContent();

    // Check that the count of attendees has decreased
    expect($event->attendees()->count())->toBe($attendeesCount - 1);

    // Try to remove the same attendee again
    $this->delete(route('attendees.destroy', [$event, $attendee]))->assertNoContent();

    // Check that the count of attendees has not changed
    expect($event->attendees()->count())->toBe($attendeesCount - 1);

    // Try to remove a non-existing attendee
    $this->delete(route('attendees.destroy', [$event, $attendeesCount * 2]))->assertNoContent();

    // Check that the count of attendees has not changed
    expect($event->attendees()->count())->toBe($attendeesCount - 1);
});

test('attendees_store', function () {
    $event = $this->getEvents(count: 1);

    $user = User::factory()->create();

    $this->post(route('attendees.store', $event), [
        'user_id' => $user->id,
    ])->assertValid()
        ->assertCreated()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'email',
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
            [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ]
        ])
        ->assertJsonFragment([
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
        ]);

    // Check that the count of attendees has increased
    expect($event->attendees()->count())->toBe(1);

    // Check that the user is now an attendee of the event
    expect($event->attendees->first()->id)->toBe($user->id);
});

test('attendees_store_already_attending', function () {
    $event = $this->getEvents(count: 1, attendeesCount: 1);

    $user = $event->attendees->first();

    $this->post(route('attendees.store', $event), [
        'user_id' => $user->id,
    ])->assertValid()
        ->assertStatus(422)
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonFragment([
            'message' => 'User is already attending this event.',
        ]);
});

test('attendees_store_not_found', function () {
    $user = User::factory()->create();

    // Test with a non-existing event
    $this->post(route('attendees.store', 10), [
        'user_id' => $user->id,
    ])->assertValid()
        ->assertStatus(404)
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonFragment([
            'message' => 'Event not found',
        ]);
});