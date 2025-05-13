<?php

use App\Constants;
use App\Models\Event;
use Illuminate\Support\Arr;
use Laravel\Sanctum\Sanctum;

test('events_index', function () {
    $countPage = 2;
    $events = $this->getEvents(count: Constants::EVENTS_PER_PAGE * $countPage, attendeesCount: 3);

    $events = $events->sortBy(['start_date', 'asc']);
    $eventFirst = $this->getEventResource($events->first());
    $eventLast = $this->getEventResource($events->last());

    $this->getJson(route('events.index'))
        ->assertValid()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'description',
                    'start_date',
                    'end_date',
                    'price',
                    'location',
                    'is_public',
                ],
            ],
            'links' => [
                'first',
                'last',
                'prev',
                'next',
            ],
            'meta' => [
                'current_page',
                'last_page',
                'per_page',
                'total',
                'path',
            ],
        ])
        ->assertJsonCount(Constants::EVENTS_PER_PAGE, 'data')
        ->assertJsonFragment($eventFirst)
        ->assertJsonMissingExact($eventLast)
        ->assertJsonFragment([
            'current_page' => 1,
            'last_page' => $countPage,
            'per_page' => Constants::EVENTS_PER_PAGE,
            'total' => Constants::EVENTS_PER_PAGE * $countPage,
        ]);

    $this->get(route('events.index', ['page' => $countPage]))
        ->assertValid()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonFragment($eventLast)
        ->assertJsonMissingExact($eventFirst);
});

test('events_index_with_user', function () {
    $events = $this->getEvents(count: Constants::EVENTS_PER_PAGE);
    $event = $events->first();

    $this->getJson(route('events.index', ['with' => 'user']))
        ->assertValid()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonFragment($this->getUserResource($event->user));
});

test('events_index_with_attendees', function () {
    $events = $this->getEvents(count: Constants::EVENTS_PER_PAGE, attendeesCount: 3);
    $event = $events->first();

    $event->load('attendees');

    $this->getJson(route('events.index', ['with' => 'attendees']))
        ->assertValid()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonFragment([
            'attendees' => $event->attendees->map(function ($user) {
                return $this->getUserResource($user);
            })->toArray(),
        ]);
});

test('events_show', function () {
    $event = $this->getEvents(count: 1);

    $this->getJson(route('events.show', $event))
        ->assertValid()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonFragment($this->getEventResource($event));
});


test('events_show_with_user', function () {
    $event = $this->getEvents(count: 1);

    $this->getJson(route('events.show', [$event, 'with' => 'user']))
        ->assertValid()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonFragment($this->getUserResource($event->user));
});

test('events_show_with_attendees', function () {
    $event = $this->getEvents(count: 1, attendeesCount: 5);

    $this->getJson(route('events.show', [$event, 'with' => 'attendees']))
        ->assertValid()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonFragment([
            'attendees' => $event->attendees->map(function ($user) {
                return $this->getUserResource($user);
            })->toArray(),
        ]);
});


test('events_show_not_found', function () {
    // Test with a non-existing event
    $this->get(route('events.show', 1))
        ->assertValid()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonFragment([
            'message' => 'Event not found',
        ]);
});

test('events_store_successful', function () {
    $data = $this->getEventFormData();

    Sanctum::actingAs($this->user);

    $this->postJson(route('events.store'), $data)
        ->assertValid()
        ->assertCreated()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonFragment([
            'data' => [
                'id' => 1,
                'name' => $data['name'],
                'description' => $data['description'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'price' => $data['price'],
                'location' => $data['location'],
                'is_public' => $data['is_public'],
                'user' => $this->getUserResource($this->user),
            ]
        ]);

    $this->assertDatabaseHas('events', $data);
});

test('events_store_only_auth', function () {
    $data = $this->getEventFormData();

    $this->postJson(route('events.store'), $data)
        ->assertUnauthorized()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonFragment([
            'message' => 'Unauthenticated.',
        ]);
});

test('events_form_validation', function () {
    $this->checkForm(
        route('events.store'),
        $this->getEventFormData(),
        [
            [['name', 'description', 'start_date', 'end_date', 'location', 'price', 'is_public'], 'required', ''],
            [['name', 'description', 'location'], 'string', 0],
            [['name', 'location'], 'max.string', str_repeat('a', Constants::STRING_MAX_LENGTH + 1), ['max' => Constants::STRING_MAX_LENGTH]],
            ['description', 'max.string', str_repeat('a', Constants::DESCRIPTION_MAX_LENGTH + 1), ['max' => Constants::DESCRIPTION_MAX_LENGTH]],
            [['start_date', 'end_date'], 'date', 'invalid-date'],
            ['start_date', 'after_or_equal', now()->subDay()->format('Y-m-d H:i:s'), ['date' => 'today']],
            ['end_date', 'after_or_equal', now()->format('Y-m-d H:i:s'), ['date' => 'start date']],
            ['price', 'numeric', 'invalide-price'],
            ['price', 'min.numeric', -10, ['min' => 0]],
            ['is_public', 'boolean', 'invalid-boolean'],
        ]
    );
    //TODO add user once it's managed
});

test('events_update_successful', function () {
    $event = $this->getEvents(count: 1, attendeesCount: 3, user: $this->user);

    $data = $this->getEventFormData();

    Sanctum::actingAs($this->user);

    $this->putJson(route('events.update', $event), $data)
        ->assertValid()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonFragment([
            'message' => 'Event updated successfully',
            'id' => $event->id,
            'name' => $data['name'],
            'description' => $data['description'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'price' => $data['price'],
            'location' => $data['location'],
            'is_public' => $data['is_public'],
        ])
        ->assertJsonFragment(['user' => $this->getUserResource($event->user)]);

    //Make sure the event is updated in the database
    $this->assertDatabaseHas('events', $data + ['id' => $event->id]);

    //Make sure the user didn't change
    expect($event->user->toArray())->toBe(Event::find($event->id)->user->toArray());
});

test('events_update_with_attendees', function () {
    $event = $this->getEvents(count: 1, attendeesCount: 3, user: $this->user);

    Sanctum::actingAs($this->user);

    $this->putJson(route('events.update', [$event, 'with' => 'attendees']), $this->getEventFormData())
        ->assertValid()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonFragment([
            'attendees' => $event->attendees->map(function ($user) {
                return $this->getUserResource($user);
            })->toArray(),
        ]);
});

test("events_update_fields_optional", function () {
    $data = $this->getEventFormData();

    Sanctum::actingAs($this->user);

    foreach ($data as $key => $value) {
        $event = $this->getEvents(count: 1, user: $this->user);

        if ($event->{$key} instanceof DateTime)
            $value = $event->{$key}->format('Y-m-d H:i:s');
        elseif (is_bool($event->{$key}))
            $value = $event->{$key} ? 1 : 0;
        else
            $value = $event->{$key};

        $this->putJson(route('events.update', $event), Arr::except($data, $key))
            ->assertValid()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonFragment([$key => $value]);
    }
});

test('events_update_not_found', function () {
    Sanctum::actingAs($this->user);

    $this->putJson(route('events.update', 1), $this->getEventFormData())
        ->assertValid()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonFragment([
            'message' => 'Event not found',
        ]);
});

test('events_update_only_auth', function () {
    $event = $this->getEvents(count: 1);

    $this->putJson(route('events.update', $event), $this->getEventFormData())
        ->assertUnauthorized()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonFragment([
            'message' => 'Unauthenticated.',
        ]);
});

test('events_update_not_owner', function () {
    $event = $this->getEvents(count: 1);

    Sanctum::actingAs($this->user);

    $this->putJson(route('events.update', $event), $this->getEventFormData())
        ->assertForbidden()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonFragment([
            'message' => 'You do not have permission to update this event.',
        ]);
});

test("events_destroy", function () {
    $event = $this->getEvents(count: 1);

    //TODO add user once it's managed
    $this->deleteJson(route('events.destroy', $event))
        ->assertValid()
        ->assertStatus(204);

    //Make sure the event is deleted from the database
    $this->assertDatabaseMissing('events', ['id' => $event->id]);
});

test('events_destroy_not_found', function () {
    $this->deleteJson(route('events.update', 1), $this->getEventFormData())
        ->assertValid()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonFragment([
            'message' => 'Event not found',
        ]);
});