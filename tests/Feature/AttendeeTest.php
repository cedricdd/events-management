<?php

use App\Constants;
use App\Http\Resources\EventResource;
use App\Http\Resources\UserResource;
use App\Models\User;

test('attendees_index', function () {
    $countPage = 2;
    $event = $this->getEvents(count: 1, attendeesCount: Constants::ATTENDEES_PER_PAGE * $countPage);

    $attendees = $event->attendees->sortBy(['name', 'asc']);
    $attendeeFirst = $this->getUserResource($attendees->first());
    $attendeeLast = $this->getUserResource($attendees->last());

    $this->getJson(route('attendees.index', $event))
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
        ->assertJsonFragment($attendeeFirst)
        ->assertJsonMissingExact($attendeeLast);


    $this->getJson(route('attendees.index', [$event, 'page' => $countPage]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonFragment($attendeeLast)
        ->assertJsonMissingExact($attendeeFirst);
});

test('attendees_index_with_event', function () {
    $event = $this->getEvents(count: 1, attendeesCount: 1);

    $this->getJson(route('attendees.index', [$event, 'with' => 'event']))
        ->assertValid()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonFragment(['event' => $this->getEventResource($event, withUser: true)]);
});

test('attendees_index_not_found', function () {
    $this->getJson(route('attendees.index', 10))
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

    $this->getJson(route('attendees.show', [$event, $attendee]))
        ->assertValid()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonFragment($this->getUserResource($attendee));
});

test('attendees_show_with_event', function () {
    $event = $this->getEvents(count: 1, attendeesCount: 1);

    $attendee = $event->attendees->first();

    $this->getJson(route('attendees.show', [$event, $attendee, 'with' => 'event']))
        ->assertValid()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonFragment($this->getEventResource($event, withUser: true));
});

test('attendees_show_not_found', function () {
    $event = $this->getEvents(count: 1, attendeesCount: 1);

    $attendee = $event->attendees->first();

    // Test with a non-existing attendee
    $this->getJson(route('attendees.show', [$event, 10]))
        ->assertValid()
        ->assertStatus(404)
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonFragment([
            'message' => 'User not found',
        ]);

    // Test with a non-existing event
    $this->getJson(route('attendees.show', [10, $attendee]))
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
    $this->deleteJson(route('attendees.destroy', [$event, $attendee]))->assertNoContent();

    // Check that the count of attendees has decreased
    expect($event->attendees()->count())->toBe($attendeesCount - 1);

    // Try to remove the same attendee again
    $this->deleteJson(route('attendees.destroy', [$event, $attendee]))->assertNoContent();

    // Check that the count of attendees has not changed
    expect($event->attendees()->count())->toBe($attendeesCount - 1);

    // Try to remove a non-existing attendee
    $this->deleteJson(route('attendees.destroy', [$event, $attendeesCount * 2]))->assertNoContent();

    // Check that the count of attendees has not changed
    expect($event->attendees()->count())->toBe($attendeesCount - 1);
});

test('attendees_store', function () {
    $event = $this->getEvents(count: 1);

    $user = User::factory()->create();

    $this->postJson(route('attendees.store', $event), [
        'user_id' => $user->id,
    ])->assertValid()
        ->assertCreated()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonFragment($this->getUserResource($user))
        ->assertJsonFragment($this->getEventResource($event, withUser: true));

    // Check that the count of attendees has increased
    expect($event->attendees()->count())->toBe(1);

    // Check that the user is now an attendee of the event
    expect($event->attendees->first()->id)->toBe($user->id);
});

test('attendees_store_already_attending', function () {
    $event = $this->getEvents(count: 1, attendeesCount: 1);

    $user = $event->attendees->first();

    $this->postJson(route('attendees.store', $event), [
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
    $this->postJson(route('attendees.store', 10), [
        'user_id' => $user->id,
    ])->assertValid()
        ->assertStatus(404)
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonFragment([
            'message' => 'Event not found',
        ]);
});