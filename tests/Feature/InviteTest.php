<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\Notification;


test('invites_store', function () {
    Notification::fake();
    
    Sanctum::actingAs($this->organizer);

    $event = $this->getEvents(count: 1, organizer: $this->organizer, overrides: [
        'public' => false,
    ]);

    $users = User::factory()->count(random_int(5, 10))->create();

    $this->postJson(route('invites.store'), [
        'event_id' => $event->id,
        'users' => $users->pluck('id')->toArray(),
    ])->assertValid()
        ->assertCreated()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJson([
            'message' => 'Invites created successfully.',
            'invites' => $users->map(fn($user) => $this->getUserResource($user))->toArray(),
        ]);

    $this->assertDatabaseCount('invites', $users->count());

    Notification::assertCount($users->count());
    Notification::assertSentTo($users->first(), \App\Notifications\EventInviteNotification::class);

    // Don't duplicate invites
    $this->postJson(route('invites.store'), [
        'event_id' => $event->id,
        'users' => $users->pluck('id')->toArray(),
    ])->assertCreated();

    $this->assertDatabaseCount('invites', $users->count());
});

test('invites_store_bad_users', function () {
    Sanctum::actingAs($this->organizer);

    $event = $this->getEvents(count: 1, organizer: $this->organizer, overrides: [
        'public' => false,
    ]);

    $this->postJson(route('invites.store'), [
        'event_id' => $event->id,
        'users' => [1234, $this->organizer->id], 
    ])->assertValid()
        ->assertCreated()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJson([
            'message' => 'Invites created successfully.',
            'invites' => [],
        ]);

    $this->assertDatabaseCount('invites', 0);
});

test('invites_store_unauthorized', function () {
    Sanctum::actingAs($this->user);

    $event = $this->getEvents(count: 1, organizer: $this->organizer, overrides: [
        'public' => false,
    ]);

    $this->postJson(route('invites.store'), [
        'event_id' => $event->id,
        'users' => [$this->user->id],
    ])->assertForbidden()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJson([
            'message' => 'This action is unauthorized.',
        ]);
});

test('invites_store_not_organizer', function () {
    Sanctum::actingAs($this->organizer);

    $event = $this->getEvents(count: 1, overrides: [
        'public' => false,
    ]);

    $this->postJson(route('invites.store'), [
        'event_id' => $event->id,
        'users' => [$this->organizer->id],
    ])->assertForbidden()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJson([
            'message' => 'You are not authorized to invite users to this event.',
        ]);
});

test('invites_store_not_private_event', function () {
    Sanctum::actingAs($this->organizer);

    $event = $this->getEvents(count: 1, organizer: $this->organizer);

    $this->postJson(route('invites.store'), [
        'event_id' => $event->id,
        'users' => [$this->user->id],
    ])->assertForbidden()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJson([
            'message' => 'You can only invite users to private events.',
        ]);
});

test('invites_store_event_in_past', function () {
    Sanctum::actingAs($this->organizer);

    $event = $this->getEvents(count: 1, organizer: $this->organizer, past: true, overrides: [
        'public' => false,
    ]);

    $this->postJson(route('invites.store'), [
        'event_id' => $event->id,
        'users' => [$this->user->id],
    ])->assertForbidden()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJson([
            'message' => 'You cannot invite users to an event that has already started.',
        ]);
});

test('invites_store_validation', function () {
    $event = $this->getEvents(count: 1, organizer: $this->organizer, overrides: [
        'public' => false,
    ]);

    $this->checkForm(
        route: route('invites.store'),
        defaults: [
        'event_id' => $event->id,
        'users' => [1, 2, 3],
    ],
        rules: [
            [['event_id', 'users'], 'required', ''],
            ['event_id', 'integer', 'invalide-id'],
            ['event_id', 'exists', 1234],
            ['users', 'array', 'not-an-array'],
        ],
        user: $this->organizer,
    );
});