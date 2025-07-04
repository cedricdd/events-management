<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use App\Jobs\SendEventInviteEmail;
use Illuminate\Support\Facades\Queue;
use App\Jobs\SendEventInviteDeletionEmail;

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

test('invites_index_unauthenticated', function () {
    $event = $this->getPrivateEvent($this->organizer, 10);

    $this->deleteJson(route('invites.index', $event->id), ['users' => [$this->user->id]])->assertUnauthorized();
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
    Queue::fake();
    
    Sanctum::actingAs($this->organizer);

    $event = $this->getPrivateEvent($this->organizer);
    $count = random_int(5, 10);
    $users = User::factory()->count($count)->create();

    $this->postJson(route('invites.store', $event->id), [
        'users' => $users->pluck('id')->toArray(),
    ])->assertValid()
        ->assertCreated()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJson([
            'message' => 'Invites created successfully.',
            'data' => $users->map(fn($user) => $this->getUserResource($user))->toArray(),
        ])->assertJsonCount($count, 'data');

    $this->assertDatabaseCount('invites', $count);

    // Don't duplicate invites
    $this->postJson(route('invites.store', $event->id), [
        'users' => $users->pluck('id')->toArray(),
    ])->assertCreated();

    $this->assertDatabaseCount('invites', $count);

    Queue::assertPushed(SendEventInviteEmail::class);
    Queue::assertCount($count);
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
            'data' => [],
        ])->assertJsonCount(0, 'data');

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

test('invites_store_unauthenticated', function () {
    $event = $this->getPrivateEvent($this->organizer, 10);

    $this->deleteJson(route('invites.store', $event->id), ['users' => [$this->user->id]])->assertUnauthorized();
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
    Queue::fake();

    Sanctum::actingAs($this->organizer);

    $event = $this->getPrivateEvent($this->organizer, 10);
    $users = $event->invitedUsers->slice(2, 5)->values();

    $this->deleteJson(route('invites.destroy', $event->id), ['users' => $users->pluck('id')])
        ->assertValid()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJson([
            'message' => 'Invites removed successfully.',
            'data' => $users->map(fn($user) => $this->getUserResource($user))->toArray(),
        ])->assertJsonCount(10 - $users->count(), 'data');
        

    $this->assertDatabaseCount('invites', 10 - count($users));
    $this->assertDatabaseMissing('invites', [
        'event_id' => $event->id,
        'user_id' => $users->first()->id,
    ]);

    Queue::assertPushed(SendEventInviteDeletionEmail::class);
    Queue::assertCount(count($users));
});

test('invites_destroy_unauthenticated', function () {
    $event = $this->getPrivateEvent($this->organizer, 10);

    $this->deleteJson(route('invites.destroy', $event->id))->assertUnauthorized();
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