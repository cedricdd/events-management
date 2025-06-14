<?php

use App\Constants;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use PHPUnit\TextUI\Configuration\Constant;
use Illuminate\Support\Facades\Notification;

test('attendees_index', function () {
    $event = $this->getEvents(count: 1, attendees: Constants::ATTENDEES_PER_PAGE);

    $attendees = $event->attendees;
    $attendeeFirst = $this->getUserResource($attendees->first());
    $attendeeLast = $this->getUserResource($attendees->last());

    $response = $this->getJson(route('attendees.index', $event))
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
        ])->assertJsonCount(Constants::ATTENDEES_PER_PAGE, 'data')
        ->assertJsonFragment([
            'current_page' => 1,
            'last_page' => 1,
            'per_page' => Constants::ATTENDEES_PER_PAGE,
            'total' => Constants::ATTENDEES_PER_PAGE,
        ]);

    expect(collect($response->json('data'))->contains($attendeeFirst))->toBeTrue();
    expect(collect($response->json('data'))->contains($attendeeLast))->toBeTrue();
});

test('attendees_index_with_event', function () {
    $event = $this->getEvents(count: 1, attendees: 1);

    $event->load(['organizer', 'type']);
    $event->loadCount('attendees');

    $response = $this->getJson(route('attendees.index', [$event, 'with' => 'event']))
        ->assertValid()
        ->assertHeader('Content-Type', 'application/json');

    expect($response->json('event'))->toBe($this->getEventResource($event));

});

test('attendees_index_sorting', function () {
    $event = $this->getEvents(count: 1, attendees: Constants::ATTENDEES_PER_PAGE * 2);
    $attendees = $event->attendees;

    foreach (Constants::USER_SORTING_OPTIONS as $name => $column) {
        if ($name == 'registration')
            $attendees = $attendees->sortBy(['pivot.id', 'asc']);
        else
            $attendees = $attendees->sortBy([$column, 'asc']);

        $response = $this->getJson(route('attendees.index', [$event, 'sort' => $name]))
            ->assertValid()
            ->assertHeader('Content-Type', 'application/json');

        expect(collect($response->json('data'))->contains($this->getUserResource($attendees->first())))->toBeTrue();
        expect(collect($response->json('data'))->contains($this->getUserResource($attendees->last())))->toBeFalse();
    }
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

test('attendees_index_out_of_range_page', function () {
    $event = $this->getEvents(count: 1, attendees: 1);

    // Test with a non-existing page
    $this->getJson(route('attendees.index', [$event, 'page' => 10]))
        ->assertValid()
        ->assertStatus(404)
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonFragment([
            'message' => "The page 10 does not exist",
        ]);
});

test('attendees_show', function () {
    $event = $this->getEvents(count: 1, attendees: 1);

    $attendee = $event->attendees->first();

    $this->getJson(route('attendees.show', [$event, $attendee]))
        ->assertValid()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonFragment($this->getUserResource($attendee));
});

test('attendees_show_with_event', function () {
    $event = $this->getEvents(count: 1, attendees: 'random');

    $event->load(['organizer', 'type']);
    $event->loadCount('attendees');

    $attendee = $event->attendees()->first();

    $this->getJson(route('attendees.show', [$event, $attendee, 'with' => 'event']))
        ->assertValid()
        ->assertHeader('Content-Type', 'application/json')
        ->assertExactJson([
            'data' => $this->getUserResource($attendee),
            'event' => $this->getEventResource($event),
        ]);
});

test('attendees_show_not_found', function () {
    $event = $this->getEvents(count: 1, attendees: 1);

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

test('attendees_destroy_as_owner', function () {
    Notification::fake();

    $attendeesCount = 10;
    $event = $this->getEvents(count: 1, attendees: $attendeesCount, organizer: $this->organizer);

    Sanctum::actingAs($this->organizer);

    $attendee = $event->attendees->first();
    $attributes = $attendee->getAttributes();

    // Remove the attendee from the event
    $this->deleteJson(route('attendees.destroy', [$event, $attendee]))->assertNoContent();

    // Check that the count of attendees has decreased
    expect($event->attendees()->count())->toBe($attendeesCount - 1);

    // Try to remove the same attendee again
    $this->deleteJson(route('attendees.destroy', [$event, $attendee]))
        ->assertStatus(403)
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonFragment([
            'message' => "This user is not registered to the event!",
        ]);

    // Check that the count of attendees has not changed
    expect($event->attendees()->count())->toBe($attendeesCount - 1);

    $attendee->refresh();

    // Check that the tokens have been incremented
    expect($attendee->tokens)->toBe($attributes['tokens'] + $event->cost);
    expect($attendee->tokens_spend)->toBe($attributes['tokens_spend'] - $event->cost);

    Notification::assertCount(1);
    Notification::assertSentTo($attendee, \App\Notifications\EventUnRegistrationNotification::class);
});

test('attendees_destroy_as_attendee', function () {
    Notification::fake();

    $count = random_int(1, 5);
    $event = $this->getEvents(count: 1, attendees: $count);

    $event->attendees()->attach($this->user);
    $attributes = $this->user->getAttributes();

    // Check that the count of attendees is 2
    expect($event->attendees()->count())->toBe($count + 1);

    Sanctum::actingAs($this->user);

    // Remove the attendee from the event
    $this->deleteJson(route('attendees.destroy', [$event, $this->user]))->assertNoContent();

    // Check that the count of attendees is 1
    expect($event->attendees()->count())->toBe($count);

    $this->user->refresh();

    // Check that the tokens have been incremented
    expect($this->user->tokens)->toBe($attributes['tokens'] + $event->cost);
    expect($this->user->tokens_spend)->toBe($attributes['tokens_spend'] - $event->cost);

    Notification::assertCount(1);
    Notification::assertSentTo($this->user, \App\Notifications\EventUnRegistrationNotification::class);
});

test('attendees_destroy_as_admin', function () {
    Notification::fake();

    $count = random_int(1, 5);
    $event = $this->getEvents(count: 1, attendees: $count);

    Sanctum::actingAs($this->admin);

    $attendee = $event->attendees->first();

    // Remove the attendee from the event
    $this->deleteJson(route('attendees.destroy', [$event, $attendee]))->assertNoContent();

    // Check that the count of attendees has decreased
    expect($event->attendees()->count())->toBe($count - 1);

    Notification::assertCount(1);
    Notification::assertSentTo($attendee, \App\Notifications\EventUnRegistrationNotification::class);
});

test('attendees_destroy_not_allowed', function () {
    $event = $this->getEvents(count: 1, attendees: 1);

    Sanctum::actingAs($this->user);

    $attendee = $event->attendees->first();

    // Try to remove the attendee from the event
    $this->deleteJson(route('attendees.destroy', [$event, $attendee]))
        ->assertForbidden()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonFragment([
            'message' => 'This action is unauthorized.',
        ]);
});

test('attendees_destroy_only_auth', function () {
    $event = $this->getEvents(count: 1, attendees: 1);

    $attendee = $event->attendees->first();

    // Try to remove the attendee from the event
    $this->deleteJson(route('attendees.destroy', [$event, $attendee]))
        ->assertUnauthorized()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonFragment([
            'message' => 'Unauthenticated.',
        ]);
});

test('attendees_destroy_not_found', function () {
    $event = $this->getEvents(count: 1, attendees: 'random');

    Sanctum::actingAs($this->user);

    // Test with a non-existing attendee
    $this->deleteJson(route('attendees.destroy', [$event, 100]))
        ->assertValid()
        ->assertStatus(404)
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonFragment([
            'message' => 'User not found',
        ]);

    // Test with a non-existing event
    $this->deleteJson(route('attendees.destroy', [10, $event->attendees->first()]))
        ->assertValid()
        ->assertStatus(404)
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonFragment([
            'message' => 'Event not found',
        ]);
});

test('attendees_store', function () {
    Notification::fake();

    $count = random_int(1, 5);
    $event = $this->getEvents(count: 1, attendees: $count);
    $event->load('organizer');
    $event->attendees_count = $count + 1;

    $attributes = $this->user->getAttributes();

    Sanctum::actingAs($this->user);

    $this->postJson(route('attendees.store', $event))
        ->assertValid()
        ->assertCreated()
        ->assertHeader('Content-Type', 'application/json')
        ->assertExactJson([
            'data' => $this->getUserResource($this->user, true),
            'event' => $this->getEventResource($event),
        ]);

    // Check that the count of attendees has increased
    expect($event->attendees()->count())->toBe($count + 1);

    // Check that the user is now an attendee of the event
    expect($event->attendees->last()->id)->toBe($this->user->id);

    $this->user->refresh();

    //Chech that the tokens have been decremented
    expect($this->user->tokens)->toBe($attributes['tokens'] - $event->cost);
    expect($this->user->tokens_spend)->toBe($attributes['tokens_spend'] + $event->cost);

    Notification::assertCount(1);
    Notification::assertSentTo($this->user, \App\Notifications\EventRegistrationNotification::class);
});

test('attendees_store_private_event', function () {
    Notification::fake();

    $count = random_int(1, 5);
    $event = $this->getEvents(count: 1, attendees: $count, overrides: [
        'is_public' => false,
    ]);

    Sanctum::actingAs($this->user);

    $this->postJson(route('attendees.store', $event))
        ->assertValid()
        ->assertStatus(403)
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonFragment([
            'message' => "You are not invited to this event.",
        ]);
});

test('attendees_store_already_attending', function () {
    $event = $this->getEvents(count: 1, attendees: 1);

    $user = $event->attendees->first();

    Sanctum::actingAs($user);

    $this->postJson(route('attendees.store', $event))->assertValid()
        ->assertStatus(409)
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonFragment([
            'message' => 'You are already registered for this event.',
        ]);
});

test('attendees_store_own_event', function () {
    $event = $this->getEvents(count: 1, organizer: $this->organizer);

    Sanctum::actingAs($this->organizer);

    $this->postJson(route('attendees.store', $event))->assertValid()
        ->assertStatus(409)
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonFragment([
            'message' => "You can't register to your own event.",
        ]);
});

test('attendees_store_not_found', function () {
    Sanctum::actingAs($this->user);

    // Test with a non-existing event
    $this->postJson(route('attendees.store', 10))->assertValid()
        ->assertStatus(404)
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonFragment([
            'message' => 'Event not found',
        ]);
});

test('attendees_store_only_auth', function () {
    $event = $this->getEvents(count: 1);

    // Try to store the attendee without authentication
    $this->postJson(route('attendees.store', $event))
        ->assertUnauthorized()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonFragment([
            'message' => 'Unauthenticated.',
        ]);
});

test('attendees_store_not_enough_tokens', function () {
    $event = $this->getEvents(count: 1);

    // Set the event's tokens to 0
    $this->user->tokens = 0;
    $this->user->save();

    Sanctum::actingAs($this->user);

    // Try to store the attendee
    $this->postJson(route('attendees.store', $event))
        ->assertValid()
        ->assertStatus(403)
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonFragment([
            'message' => "You don't have enough tokens to register for this event.",
        ]);
});

test('attendees_store_event_started', function () {
    $event = $this->getEvents(count: 1);

    // Set the event's start date to the past
    $event->start_date = now()->subDay();
    $event->save();

    Sanctum::actingAs($this->user);

    // Try to store the attendee
    $this->postJson(route('attendees.store', $event))
        ->assertValid()
        ->assertStatus(403)
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonFragment([
            'message' => "You can only register to an event before it start.",
        ]);
});