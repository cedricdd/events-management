<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('invites_index', function () {
    Sanctum::actingAs($this->organizer);

    $event = $this->getPrivateEvent($this->organizer, 10);
    $users = $event->invitedUsers;

    $response = $this->getJson(route('invites.index', $event->id))
        ->assertValid()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonCount($users->count(), 'data')
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'email',
                    'country',
                    'profession',
                    'phone',
                    'organization',
                ],
            ],
        ]);

    $data = collect($response->json('data'));

    foreach($users as $user) {
        expect($data->contains($this->getUserResource($user)))->toBeTrue();
    }
});

test('invites_index_as_admin', function () {
    Sanctum::actingAs($this->admin);

    $event = $this->getPrivateEvent($this->organizer, 10);
    $users = $event->invitedUsers;

    $this->getJson(route('invites.index', $event->id))
        ->assertValid()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonCount($users->count(), 'data');
});

test('invites_index_unauthorized', function () {
    Sanctum::actingAs($this->user);

    $event = $this->getPrivateEvent($this->organizer, 10);

    $this->getJson(route('invites.index', $event->id))
        ->assertForbidden()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJson([
            'message' => 'This action is unauthorized.',
        ]);
});

test('invites_index_not_private_event', function () {
    Sanctum::actingAs($this->organizer);

    $event = $this->getEvents(count: 1, organizer: $this->organizer);

    $this->getJson(route('invites.index', $event->id))
        ->assertForbidden()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJson([
            'message' => 'This event is a public event, there are no invites.',
        ]);
});

test('invites_index_event_not_found', function () {
    Sanctum::actingAs($this->organizer);

    $this->getJson(route('invites.index', 1234))
        ->assertStatus(404)
        ->assertHeader('Content-Type', 'application/json')
        ->assertJson([
            'message' => 'Event not found',
        ]);
}); 

test('invites_store', function () {
    Sanctum::actingAs($this->organizer);

    $event = $this->getPrivateEvent($this->organizer);

    $users = User::factory()->count(random_int(5, 10))->create();

    $this->postJson(route('invites.store', $event->id), [
        'users' => $users->pluck('id')->toArray(),
    ])->assertValid()
        ->assertCreated()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJson([
            'message' => 'Invites created successfully.',
            'invites' => $users->map(fn($user) => $this->getUserResource($user))->toArray(),
        ]);

    $this->assertDatabaseCount('invites', $users->count());

    // Don't duplicate invites
    $this->postJson(route('invites.store', $event->id), [
        'users' => $users->pluck('id')->toArray(),
    ])->assertCreated();

    $this->assertDatabaseCount('invites', $users->count());
});

test('invites_store_bad_users', function () {
    Sanctum::actingAs($this->organizer);

    $event = $this->getPrivateEvent($this->organizer);

    $this->postJson(route('invites.store', $event->id), [
        'users' => [1234, 4321, $this->organizer->id], 
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

    $event = $this->getPrivateEvent($this->organizer);

    $this->postJson(route('invites.store', $event->id), [
        'users' => [$this->user->id],
    ])->assertForbidden()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJson([
            'message' => 'This action is unauthorized.',
        ]);
});

test('invites_store_not_organizer', function () {
    Sanctum::actingAs($this->organizer);

    $event = $this->getPrivateEvent($this->user);

    $this->postJson(route('invites.store', $event->id), [
        'users' => [$this->organizer->id],
    ])->assertForbidden()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJson([
            'message' => 'This action is unauthorized.',
        ]);
});

test('invites_store_not_private_event', function () {
    Sanctum::actingAs($this->organizer);

    $event = $this->getEvents(count: 1, organizer: $this->organizer);

    $this->postJson(route('invites.store', $event->id), [
        'users' => [$this->user->id],
    ])->assertForbidden()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJson([
            'message' => 'You can only invite users to private events.',
        ]);
});

test('invites_store_event_in_past', function () {
    Sanctum::actingAs($this->organizer);

    $event = $this->getPrivateEvent($this->organizer);
    $event->start_date = now()->subDays(1);
    $event->save();

    $this->postJson(route('invites.store', $event->id), [
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
        route: route('invites.store', $event),
        defaults: ['users' => [1, 2, 3]],
        rules: [
            ['users', 'array', 'not-an-array'],
        ],
        user: $this->organizer,
    );
});

test('invites_destroy', function () {
    Sanctum::actingAs($this->organizer);

    $event = $this->getPrivateEvent($this->organizer, 10);

    $this->deleteJson(route('invites.destroy', [$event->id, $event->invitedUsers->first()->id]))
        ->assertValid()
        ->assertNoContent();

    $this->assertDatabaseCount('invites', $event->invitedUsers->count() - 1);
    $this->assertDatabaseMissing('invites', [
        'event_id' => $event->id,
        'user_id' => $event->invitedUsers->first()->id,
    ]);
});

test('invites_destroy_unauthorized', function () {
    Sanctum::actingAs($this->user);

    $event = $this->getPrivateEvent($this->organizer, 10);

    $this->deleteJson(route('invites.destroy', [$event->id, $event->invitedUsers->first()->id]))
        ->assertForbidden()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJson([
            'message' => 'This action is unauthorized.',
        ]);
});

test('invites_destroy_event_not_found', function () {
    Sanctum::actingAs($this->organizer);

    $this->deleteJson(route('invites.destroy', [1234, 1]))
        ->assertStatus(404)
        ->assertHeader('Content-Type', 'application/json')
        ->assertJson([
            'message' => 'Event not found',
        ]);
});