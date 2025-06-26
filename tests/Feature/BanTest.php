<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('bans_store', function () {
    Sanctum::actingAs($this->organizer);

    $count = random_int(5, 10);
    $users = User::factory()->count($count)->create();
    $users->prepend($this->user);

    $this->postJson(route('bans.store'), ['users' => $users->pluck('id')->toArray()])->assertValid()
        ->assertCreated()
        ->assertJsonCount($count + 1, 'users')
        ->assertJson([
            'message' => 'Bans added successfully.',
            'users' => $users->map(fn($user) => $this->getUserResource($user))->toArray(),
        ]);

    $this->assertDatabaseCount('bans', $count + 1);
    $this->assertDatabaseHas('bans', [
        'user_id' => $this->organizer->id,
        'attendee_id' => $this->user->id,
    ]);

    $event = $this->getEvents(count: 1, organizer: $this->organizer);

    Sanctum::actingAs($this->user);

    //Make sure the user can't join the event anymore
    $this->postJson(route('attendees.store', ['event' => $event->id]))->assertForbidden()
        ->assertJson([
            'message' => "The organizer of this event does not allow you to join the event.",
        ]);
});

test('bans_store_validation', function () {
    $this->checkForm(
        route: route('bans.store'),
        defaults: ['users' => [1, 2, 3, 4, 5]],
        rules: [
            ['users', 'array', 'not-an-array'],
        ],
        user: $this->organizer,
    );
});

test('bans_store_unauthenticated', function () {
    $this->postJson(route('bans.store'), ['users' => [1, 2, 3, 4, 5]])
        ->assertUnauthorized();
});

test('bans_index', function () {
    Sanctum::actingAs($this->organizer);

    $count = random_int(5, 10);
    $users = User::factory()->count($count)->create();

    $this->postJson(route('bans.store'), ['users' => $users->pluck('id')->toArray()])->assertValid();

    $users = $users->sortBy(['name', 'asc'])->values();

    $this->getJson(route('bans.index'))
        ->assertValid()
        ->assertJsonCount($count, 'data')
        ->assertJson(['data' => $users->map(fn($user) => $this->getUserResource($user))->toArray()]);

    // Admins can view other users' banned lists
    Sanctum::actingAs($this->admin);

    $this->getJson(route('bans.index', $this->organizer->id))
        ->assertValid()
        ->assertJsonCount($count, 'data')
        ->assertJson(['data' => $users->map(fn($user) => $this->getUserResource($user))->toArray()]);

    // Non-admins cannot view other users' banned lists
    Sanctum::actingAs($this->user);

    $this->getJson(route('bans.index', $this->organizer->id))
        ->assertForbidden()
        ->assertJson([
            'message' => "You are not authorized to view this user's banned list.",
        ]);
});

test('bans_index_unauthenticated', function () {
    $this->postJson(route('bans.index'))
        ->assertUnauthorized();
});
