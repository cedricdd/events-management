<?php

use App\Constants;
use App\Models\Event;
use App\Models\EventType;
use Illuminate\Support\Arr;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\Notification;
use App\Notifications\EventCreationNotification;
use App\Notifications\EventDeletionNotification;
use App\Notifications\EventModificationNotification;

test('events_index', function () {
    $events = $this->getEvents(count: Constants::EVENTS_PER_PAGE, attendees: 3);
    $events->loadCount('attendees');

    $eventFirst = $this->getEventResource($events->first());
    $eventLast = $this->getEventResource($events->last());

    $response = $this->getJson(route('events.index'))
        ->assertValid()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'description',
                    'location',
                    'cost',
                    'start_date',
                    'end_date',
                    'type',
                    'attendees_count',
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
        ->assertJsonFragment([
            'current_page' => 1,
            'last_page' => 1,
            'per_page' => Constants::EVENTS_PER_PAGE,
            'total' => Constants::EVENTS_PER_PAGE,
        ]);

    expect(collect($response->json('data'))->contains($eventFirst))->toBeTrue();
    expect(collect($response->json('data'))->contains($eventLast))->toBeTrue();
});

test('events_index_with_organizer', function () {
    $events = $this->getEvents(count: Constants::EVENTS_PER_PAGE);

    $event = $events->first();
    $event->load('organizer');
    $event->loadCount('attendees');

    $response = $this->getJson(route('events.index', ['with' => 'organizer']))
        ->assertValid()
        ->assertHeader('Content-Type', 'application/json');

    expect(collect($response->json('data'))->contains($this->getEventResource($event)))->toBeTrue();
});

test('events_index_with_attendees', function () {
    $events = $this->getEvents(count: Constants::EVENTS_PER_PAGE, attendees: 3);

    $event = $events->first();
    $event->load('attendees');
    $event->loadCount('attendees');

    $response = $this->getJson(route('events.index', ['with' => 'attendees']))
        ->assertValid()
        ->assertHeader('Content-Type', 'application/json');

    expect(collect($response->json('data'))->contains($this->getEventResource($event)))->toBeTrue();
});

test('events_index_sorting', function () {
    $events = $this->getEvents(count: Constants::EVENTS_PER_PAGE * 2, attendees: 'random');

    $events->loadCount('attendees');

    foreach (Constants::EVENT_SORTING_OPTIONS as $name => $column) {
        $events = $events->sortBy([$column, 'asc']);

        $eventFirst = $this->getEventResource($events->first());
        $eventLast = $this->getEventResource($events->last());

        $response = $this->getJson(route('events.index', ['sort' => $name]))
            ->assertValid()
            ->assertHeader('Content-Type', 'application/json');

        expect(collect($response->json('data'))->contains($eventFirst))->toBeTrue();
        expect(collect($response->json('data'))->contains($eventLast))->toBeFalse();
    }
});

test('events_index_out_of_range_page', function () {
    $this->getEvents(count: Constants::EVENTS_PER_PAGE);

    $this->getJson(route('events.index', ['page' => 10]))
        ->assertValid()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonFragment([
            'message' => "The page 10 does not exist",
        ])->assertStatus(404);
});

test('events_index_past_events', function () {
    $incative = $this->getEvents(count: Constants::EVENTS_PER_PAGE, attendees: 3, past: true);
    $active = $this->getEvents(count: Constants::EVENTS_PER_PAGE, attendees: 3, past: false);

    $incative->loadCount('attendees');
    $active->loadCount('attendees');

    // We should only get the past events
    $response = $this->getJson(route('events.index', ['past' => 1]))
        ->assertValid()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonFragment([
            'current_page' => 1,
            'last_page' => 1,
            'per_page' => Constants::EVENTS_PER_PAGE,
            'total' => Constants::EVENTS_PER_PAGE,
        ]);

    foreach ($incative as $event) {
        expect(collect($response->json('data'))->contains($this->getEventResource($event)))->toBeTrue();
    }
    foreach ($active as $event) {
        expect(collect($response->json('data'))->contains($this->getEventResource($event)))->toBeFalse();
    }

    // We should only get the active events
    $response = $this->getJson(route('events.index'))
        ->assertValid()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonFragment([
            'current_page' => 1,
            'last_page' => 1,
            'per_page' => Constants::EVENTS_PER_PAGE,
            'total' => Constants::EVENTS_PER_PAGE,
        ]);

    foreach ($incative as $event) {
        expect(collect($response->json('data'))->contains($this->getEventResource($event)))->toBeFalse();
    }
    foreach ($active as $event) {
        expect(collect($response->json('data'))->contains($this->getEventResource($event)))->toBeTrue();
    }
});

test('events_show', function () {
    $event = $this->getEvents(count: 1, attendees: 'random');
    $event->loadCount('attendees');

    $this->getJson(route('events.show', $event))
        ->assertValid()
        ->assertHeader('Content-Type', 'application/json')
        ->assertExactJson([
            'data' => $this->getEventResource($event),
        ]);
});


test('events_show_with_organizer', function () {
    $event = $this->getEvents(count: 1, attendees: 'random');
    $event->loadCount('attendees');
    $event->load('organizer');

    $this->getJson(route('events.show', [$event, 'with' => 'organizer']))
        ->assertValid()
        ->assertHeader('Content-Type', 'application/json')
        ->assertExactJson([
            'data' => $this->getEventResource($event),
        ]);
});

test('events_show_with_attendees', function () {
    $event = $this->getEvents(count: 1, attendees: 'random');
    $event->loadCount('attendees');
    $event->load('attendees');

    $this->getJson(route('events.show', [$event, 'with' => 'attendees']))
        ->assertValid()
        ->assertHeader('Content-Type', 'application/json')
        ->assertExactJson([
            'data' => $this->getEventResource($event),
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
    Notification::fake();

    $data = $this->getEventFormData();

    Sanctum::actingAs($this->organizer);

    $this->postJson(route('events.store'), $data)
        ->assertValid()
        ->assertCreated()
        ->assertHeader('Content-Type', 'application/json')
        ->assertExactJson([
            'data' => [
                'id' => 1,
                'name' => $data['name'],
                'description' => $data['description'],
                'location' => $data['location'],
                'cost' => $data['cost'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'type' => $data['type'],
                'public' => $data['public'] ? "yes" : "no",
                'organizer' => $this->getUserResource($this->organizer),
            ],
            "message" => 'Event created successfully',
        ]);

    $this->assertDatabaseHas('events', Arr::except($data, 'type') + ['id' => 1]);

    Notification::assertCount(1);
    Notification::assertSentTo([$this->organizer], EventCreationNotification::class);
});

test('events_store_duplicate', function () {
    $data = $this->getEventFormData();

    Sanctum::actingAs($this->organizer);

    $this->postJson(route('events.store'), $data)
        ->assertValid()
        ->assertCreated()
        ->assertHeader('Content-Type', 'application/json');

    $this->postJson(route('events.store'), $data)
        ->assertValid()
        ->assertStatus(409)
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonFragment([
            'message' => "A similar event already exists!",
            'event' => $this->getEventResource(Event::first()),
        ]);
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

test('events_store_not_basic', function () {
    $data = $this->getEventFormData();

    Sanctum::actingAs($this->user);

    $this->postJson(route('events.store'), $data)
        ->assertForbidden()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonFragment([
            'message' => 'This action is unauthorized.',
        ]);
});

test('events_form_validation', function () {
    $this->checkForm(
        route: route('events.store'),
        defaults: $this->getEventFormData(),
        rules: [
            [['name', 'description', 'start_date', 'end_date', 'location', 'cost', 'public', 'type'], 'required', ''],
            [['name', 'description', 'location', 'type'], 'string', 0],
            [['name', 'location'], 'max.string', str_repeat('a', Constants::STRING_MAX_LENGTH + 1), ['max' => Constants::STRING_MAX_LENGTH]],
            ['description', 'max.string', str_repeat('a', Constants::DESCRIPTION_MAX_LENGTH + 1), ['max' => Constants::DESCRIPTION_MAX_LENGTH]],
            [['start_date', 'end_date'], 'date', 'invalid-date'],
            ['start_date', 'after', now()->format('Y-m-d H:i:s'), ['date' => '+' . Constants::MIN_HOURS_BEFORE_START_EVENT . ' hours']],
            ['end_date', 'after', now()->format('Y-m-d H:i:s'), ['date' => 'start date']],
            ['cost', 'integer', 'invalide-cost'],
            ['cost', 'min.numeric', -10, ['min' => 0]],
            ['cost', 'max.numeric', 1000, ['max' => 100]],
            ['public', 'boolean', 'invalid-boolean'],
            ['type', 'exists', 'invalid-type'],
        ],
        user: $this->organizer,
    );
});

test('events_update_successful', function () {
    Notification::fake();

    $event = $this->getEvents(count: 1, organizer: $this->organizer);

    $data = $this->getEventFormData();

    Sanctum::actingAs($this->organizer);

    $this->putJson(route('events.update', $event), $data)
        ->assertValid()
        ->assertHeader('Content-Type', 'application/json')
        ->assertExactJson([
            'data' => [
                'id' => $event->id,
                'name' => $data['name'],
                'description' => $data['description'],
                'location' => $data['location'],
                'cost' => $data['cost'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'type' => $data['type'],
                'public' => $data['public'] ? "yes" : "no",
                'attendees_count' => 0,
                'organizer' => $this->getUserResource($this->organizer),
            ],
            "message" => 'Event updated successfully',
        ]);

    //Make sure the event is updated in the database
    $this->assertDatabaseHas('events', Arr::except($data, 'type') + ['id' => $event->id]);

    //Make sure the organizer didn't change
    expect($event->organizer->toArray())->toBe(Event::find($event->id)->organizer->toArray());

    Notification::assertNothingSent();
});

test('events_update_by_admin', function () {
    Notification::fake();

    $event = $this->getEvents(count: 1);

    $data = $this->getEventFormData();

    Sanctum::actingAs($this->admin);

    $this->putJson(route('events.update', $event), $data)
        ->assertValid()
        ->assertHeader('Content-Type', 'application/json')
        ->assertExactJson([
            'data' => [
                'id' => $event->id,
                'name' => $data['name'],
                'description' => $data['description'],
                'location' => $data['location'],
                'cost' => $data['cost'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'type' => $data['type'],
                'public' => $data['public'] ? "yes" : "no",
                'attendees_count' => 0,
                'organizer' => $this->getUserResource($event->organizer),
            ],
            "message" => 'Event updated successfully',
        ]);

    //Make sure the event is updated in the database
    $this->assertDatabaseHas('events', Arr::except($data, 'type') + ['id' => $event->id]);
});

test("events_update_fields_optional", function () {
    Notification::fake();

    $event = $this->getEvents(count: 1, organizer: $this->organizer);

    $data = Arr::except($this->getEventFormData(), ['public']);

    Sanctum::actingAs($this->organizer);

    foreach ($data as $key => $value) {
        $this->putJson(route('events.update', $event), [$key => $value])
            ->assertValid()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonFragment([$key => $value]);
    }
});

test('events_update_duplicate', function () {
    $events = $this->getEvents(count: 2, organizer: $this->organizer);

    $data = $this->getEventFormData();

    Sanctum::actingAs($this->organizer);

    $this->putJson(route('events.update', $events->first()), $data)->assertValid();

    // Try to update the event with the same data
    $this->putJson(route('events.update', $events->last()), $data)
        ->assertValid()
        ->assertStatus(409)
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonFragment([
            'message' => "A similar event already exists!",
            'id' => $events->first()->id,
        ]);
});

test('events_update_too_late', function () {
    $event = $this->getEvents(count: 1, attendees: 3, organizer: $this->organizer);

    $data = $this->getEventFormData();
    Sanctum::actingAs($this->organizer);

    // Set the start date to be too close and forbidding the update
    $event->start_date = now()->addHours(Constants::MIN_HOURS_BEFORE_START_EVENT - 1);
    $event->save();

    $this->putJson(route('events.update', $event), $data)
        ->assertForbidden()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonFragment([
            'message' => "The start of this event is too close, modification are not allowed anymore!",
        ]);
});

test('events_update_no_changes', function () {
    $event = $this->getEvents(count: 1, attendees: 3, organizer: $this->organizer);

    Sanctum::actingAs($this->organizer);

    $this->putJson(route('events.update', $event), ['name' => $event->name, 'location' => $event->location])
        ->assertValid()
        ->assertStatus(409)
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonFragment([
            'message' => "No changes were made to the event.",
        ]);
});

test('events_update_with_registered', function () {
    Notification::fake();

    $attendeesCount = 5;
    $event = $this->getEvents(count: 1, attendees: $attendeesCount, organizer: $this->organizer);

    $data = $this->getEventFormData();

    Sanctum::actingAs($this->organizer);

    // When an event has attendees, we don't allow any changes other than name, description & type
    $this->putJson(route('events.update', $event), $data)
        ->assertValid()
        ->assertHeader('Content-Type', 'application/json')
        ->assertExactJson([
            'data' => [
                'id' => $event->id,
                'name' => $data['name'],
                'description' => $data['description'],
                'location' => $event->location,
                'cost' => $event->cost,
                'start_date' => $event->start_date->format('Y-m-d H:i:s'),
                'end_date' => $event->end_date->format('Y-m-d H:i:s'),
                'type' => $data['type'],
                'public' => $event->public ? "yes" : "no",
                'attendees_count' => $attendeesCount,
                'organizer' => $this->getUserResource($event->organizer),
            ],
            "message" => 'Event updated successfully',
        ]);

    Notification::assertCount($attendeesCount);
    Notification::assertSentTo([$event->attendees], EventModificationNotification::class);
});

test('events_update_end_before_start', function () {
    $event = $this->getEvents(count: 1, organizer: $this->organizer);

    Sanctum::actingAs($this->organizer);

    $this->putJson(route('events.update', $event), ['end_date' => Carbon\Carbon::parse($event->start_date)->subHour()->format('Y-m-d H:i:s')])
        ->assertForbidden()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonFragment([
            'message' => "The end date must be after the start date.",
        ]);
});

test('events_update_not_found', function () {
    Sanctum::actingAs($this->organizer);

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

    Sanctum::actingAs($this->organizer);

    $this->putJson(route('events.update', $event), $this->getEventFormData())
        ->assertForbidden()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonFragment([
            'message' => 'This action is unauthorized.',
        ]);
});

test("events_destroy", function () {
    Notification::fake();

    $event = $this->getEvents(count: 1, attendees: 5, organizer: $this->organizer);
    $attendees = $event->attendees;

    Sanctum::actingAs($this->organizer);

    $this->deleteJson(route('events.destroy', $event))
        ->assertValid()
        ->assertStatus(204);

    //Make sure the event is deleted from the database
    $this->assertDatabaseMissing('events', ['id' => $event->id]);

    //Make sure the attendees are refunded
    foreach ($attendees as $attendee) {
        $this->assertDatabaseHas('users', ['id' => $attendee->id, 'tokens' => $attendee->tokens + $event->cost, 'tokens_spend' => $attendee->tokens_spend - $event->cost]);
    }

    Notification::assertCount($attendees->count());
    Notification::assertSentTo($attendees, EventDeletionNotification::class);
});

test('events_destroy_too_close_to_start', function () {
    Notification::fake();

    $event = $this->getEvents(count: 1, organizer: $this->organizer);

    // Set the start date to be too close and forbidding the update
    $event->start_date = now()->addHours(Constants::MIN_HOURS_BEFORE_START_EVENT - 1);
    $event->save();

    Sanctum::actingAs($this->organizer);

    $this->deleteJson(route('events.destroy', $event))
        ->assertForbidden()
        ->assertHeader('Content-Type', 'application/json')
        ->assertExactJson([
            'message' => 'The deletion of this event is not allowed anymore!',
        ]);

    //Make sure the event can be deleted by an admin
    Sanctum::actingAs($this->admin);

    $this->deleteJson(route('events.destroy', $event))
        ->assertValid()
        ->assertStatus(204);

    //Make sure the event is deleted from the database
    $this->assertDatabaseMissing('events', ['id' => $event->id]);
});

test('events_destroy_past_event', function () {
    Notification::fake();

    $event = $this->getEvents(count: 1, attendees: 5, past: true);
    $attendees = $event->attendees->keyBy('id');

    Sanctum::actingAs($this->admin);

    $this->deleteJson(route('events.destroy', $event))
        ->assertValid()
        ->assertStatus(204);

    //Make sure the event is deleted from the database
    $this->assertDatabaseMissing('events', ['id' => $event->id]);

    //Make sure the attendees are not refunded
    foreach ($event->attendees as $attendee) {
        expect($attendees->get($attendee->id)->tokens)->toBe($attendee->tokens);
    }
});

test('events_destroy_only_auth', function () {
    $event = $this->getEvents(count: 1);

    $this->deleteJson(route('events.destroy', $event))
        ->assertUnauthorized()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonFragment([
            'message' => 'Unauthenticated.',
        ]);
});

test('events_destroy_not_owner', function () {
    $event = $this->getEvents(count: 1);

    Sanctum::actingAs($this->user);

    $this->deleteJson(route('events.destroy', $event))
        ->assertForbidden()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonFragment([
            'message' => 'This action is unauthorized.',
        ]);
});

test('events_destroy_not_found', function () {
    Sanctum::actingAs($this->user);

    $this->deleteJson(route('events.update', 1), $this->getEventFormData())
        ->assertValid()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonFragment([
            'message' => 'Event not found',
        ]);
});

test('events_organizer', function () {
    $this->getEvents(count: Constants::EVENTS_PER_PAGE, attendees: 3); // Add some events not belonging to the organizer
    $events = $this->getEvents(count: Constants::EVENTS_PER_PAGE, attendees: 3, organizer: $this->organizer);
    $events->loadCount('attendees');

    $eventFirst = $this->getEventResource($events->first());
    $eventLast = $this->getEventResource($events->last());

    $response = $this->getJson(route('events.organizer', $this->organizer))
        ->assertValid()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'description',
                    'location',
                    'cost',
                    'start_date',
                    'end_date',
                    'type',
                    'attendees_count',
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
        ->assertJsonFragment([
            'current_page' => 1,
            'last_page' => 1,
            'per_page' => Constants::EVENTS_PER_PAGE,
            'total' => Constants::EVENTS_PER_PAGE,
        ]);

    expect(collect($response->json('data'))->contains($eventFirst))->toBeTrue();
    expect(collect($response->json('data'))->contains($eventLast))->toBeTrue();
});

test('events_type', function () {
    $type = EventType::first();

    $this->getEvents(count: Constants::EVENTS_PER_PAGE, type: $type);

    $this->getJson(route('events.type', $type->name))
        ->assertValid()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'description',
                    'location',
                    'cost',
                    'start_date',
                    'end_date',
                    'type',
                    'attendees_count',
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
        ])->assertJsonCount(Constants::EVENTS_PER_PAGE, 'data');
});

test('events_type_sorting', function () {
    $type = EventType::first();
    $events = $this->getEvents(count: Constants::EVENTS_PER_PAGE * 2, attendees: 'random', type: $type);

    $events->loadCount('attendees');

    foreach (Constants::EVENT_SORTING_OPTIONS as $name => $column) {
        $events = $events->sortBy([$column, 'asc']);

        $eventFirst = $this->getEventResource($events->first());
        $eventLast = $this->getEventResource($events->last());

        $response = $this->getJson(route('events.type', [$type->name, 'sort' => $name]))
            ->assertValid()
            ->assertHeader('Content-Type', 'application/json');

        expect(collect($response->json('data'))->contains($eventFirst))->toBeTrue();
        expect(collect($response->json('data'))->contains($eventLast))->toBeFalse();
    }
});

test('events_type_with_organizer', function () {
    $type = EventType::first();
    $event = $this->getEvents(count: 1, type: $type);

    $event->load('organizer');
    $event->loadCount('attendees');

    $response = $this->getJson(route('events.type', [$type->name, 'with' => 'organizer']))
        ->assertValid()
        ->assertHeader('Content-Type', 'application/json');

    expect(collect($response->json('data'))->contains($this->getEventResource($event)))->toBeTrue();
});

test('events_type_not_found', function () {
    $this->getJson(route('events.type', "invalid"))
        ->assertValid()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonFragment([
            'message' => 'There are no events of this type.',
        ])->assertStatus(404);
});

test('events_type_out_of_range_page', function () {
    $type = EventType::first();
    $pages = 2;

    $this->getEvents(count: Constants::EVENTS_PER_PAGE * $pages, type: $type);

    $this->getJson(route('events.type', [$type->name, 'page' => $pages + 1]))
        ->assertValid()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonFragment([
            'message' => "The page " . ($pages + 1) . " does not exist",
        ])->assertStatus(404);

    $this->getJson(route('events.type', [$type->name, 'page' => $pages]))
        ->assertValid()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonCount(Constants::EVENTS_PER_PAGE, 'data');
});

test('events_search', function () {
    $events = $this->getEvents(count: Constants::EVENTS_PER_PAGE, attendees: 'random');
    $events->loadCount('attendees');

    $event = $events->first();
    $eventResource = $this->getEventResource($event);

    // Set the last event to private
    $lastEvent = $events->last();
    $lastEvent->public = false;
    $lastEvent->save();

    $response = $this->getJson(route('events.search', ['name' => $event->name]))
        ->assertValid()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'description',
                    'location',
                    'cost',
                    'start_date',
                    'end_date',
                    'type',
                    'attendees_count',
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
        ]);


    foreach (['name', 'description', 'location'] as $key) {
        $value = substr($event->{$key}, 0, 12);

        // Search by including
        $response = $this->getJson(route('events.search', [$key => $value]))
            ->assertValid()
            ->assertHeader('Content-Type', 'application/json');

        expect(collect($response->json('data'))->contains($eventResource))->toBeTrue();

        // Search by excluding
        $response = $this->getJson(route('events.search', [$key => '-' . $value]))
            ->assertValid()
            ->assertHeader('Content-Type', 'application/json');

        expect(collect($response->json('data'))->contains($eventResource))->toBeFalse();
    }

    // Search by cost
    $this->getJson(route('events.search', ['cost_max' => 7]))
        ->assertValid()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonCount($events->where('cost', '<=', 7)->count(), 'data');

    $this->getJson(route('events.search', ['cost_min' => 3]))
        ->assertValid()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonCount($events->where('cost', '>=', 3)->count(), 'data');

    // Search by attendees count
    $this->getJson(route('events.search', ['attendees_max' => 7]))
        ->assertValid()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonCount($events->where('attendees_count', '<=', 7)->count(), 'data');

    $this->getJson(route('events.search', ['attendees_min' => 3]))
        ->assertValid()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonCount($events->where('attendees_count', '>=', 3)->count(), 'data');

    // Search by start date
    $this->getJson(route('events.search', ['starts_before' => $event->start_date->format('Y-m-d H:i:s')]))
        ->assertValid()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonCount($events->filter(fn($filter) => $filter->start_date <= $event->start_date)->count(), 'data');

    $this->getJson(route('events.search', ['starts_after' => $event->start_date->format('Y-m-d H:i:s')]))
        ->assertValid()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonCount($events->filter(fn($filter) => $filter->start_date >= $event->start_date)->count(), 'data');

    // Search by end date
    $this->getJson(route('events.search', ['ends_before' => $event->end_date->format('Y-m-d H:i:s')]))
        ->assertValid()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonCount($events->filter(fn($filter) => $filter->end_date <= $event->end_date)->count(), 'data');

    $this->getJson(route('events.search', ['ends_after' => $event->end_date->format('Y-m-d H:i:s')]))
        ->assertValid()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonCount($events->filter(fn($filter) => $filter->end_date >= $event->end_date)->count(), 'data');

    // Search by type
    $this->getJson(route('events.search', ['type' => $event->type->name]))
        ->assertValid()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonCount($events->where('type_id', $event->type_id)->count(), 'data');

    // Search by public
    $this->getJson(route('events.search', ['public' => 0]))
        ->assertValid()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonCount(1, 'data');

    // Search by organizer
    $this->getJson(route('events.search', ['organizer' => $event->organizer->id]))
        ->assertValid()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonCount(1, 'data');

    // Search with everthing together
    $response = $this->getJson(route('events.search', [
        'name' => $event->name,
        'description' => substr($event->description, 0, 12),
        'location' => $event->location,
        'cost_max' => $event->cost,
        'cost_min' => $event->cost,
        'attendees_max' => $event->attendees_count,
        'attendees_min' => $event->attendees_count,
        'starts_before' => $event->start_date->format('Y-m-d H:i:s'),
        'starts_after' => $event->start_date->format('Y-m-d H:i:s'),
        'ends_before' => $event->end_date->format('Y-m-d H:i:s'),
        'ends_after' => $event->end_date->format('Y-m-d H:i:s'),
        'type' => $event->type->name,
        'public' => $event->public,
        'organizer' => $event->organizer->id,
    ]))
        ->assertValid()
        ->assertHeader('Content-Type', 'application/json');

    expect(collect($response->json('data'))->contains($eventResource))->toBeTrue();
});

test('events_search_with_organizer', function () {
    $events = $this->getEvents(count: 1, attendees: 3);

    $event = $events->first();
    $event->load('organizer');
    $event->loadCount('attendees');

    $response = $this->getJson(route('events.search', ['name' => $event->name, 'with' => 'organizer']))
        ->assertValid()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonCount(1, 'data');

    expect(collect($response->json('data'))->contains($this->getEventResource($event)))->toBeTrue();
});

test('events_search_sorting', function () {
    $events = $this->getEvents(count: Constants::EVENTS_PER_PAGE * 2, attendees: 'random', overrides: ['description' => 'test description']);

    $events->loadCount('attendees');

    foreach (Constants::EVENT_SORTING_OPTIONS as $name => $column) {
        $events = $events->sortBy([$column, 'asc']);

        $eventFirst = $this->getEventResource($events->first());
        $eventLast = $this->getEventResource($events->last());

        $response = $this->getJson(route('events.search', ['description' => "test description", 'sort' => $name]))
            ->assertValid()
            ->assertHeader('Content-Type', 'application/json');

        expect(collect($response->json('data'))->contains($eventFirst))->toBeTrue();
        expect(collect($response->json('data'))->contains($eventLast))->toBeFalse();
    }
});

test('events_search_validation', function () {
    $event = $this->getEvents(count: 1, attendees: 3);

    $this->checkForm(
        method: 'GET',
        route: route('events.search'),
        defaults: $event->toArray(),
        rules: [
            [['name', 'description', 'location', 'type'], 'string', ''],
            [['name', 'description', 'location'], 'max.string', str_repeat('a', Constants::STRING_MAX_LENGTH + 1), ['max' => Constants::STRING_MAX_LENGTH]],
            [['cost_max', 'cost_min', 'attendees_max', 'attendees_min', 'organizer'], 'integer', 'invalid'],
            [['cost_max', 'cost_min', 'attendees_max', 'attendees_min'], 'min.numeric', -10, ['min' => 0]],
            [['starts_before', 'starts_after', 'ends_before', 'ends_after'], 'date_format', 'invalid-date-format', ['format' => 'Y-m-d H:i:s']],
            ['type', 'exists', 'invalid-type'],
            ['public', 'boolean', 'invalid-boolean'],
        ],
    );
});
            

test('events_search_out_of_range_page', function () {
    $nbrPages = random_int(2, 5);
    $this->getEvents(count: Constants::EVENTS_PER_PAGE * $nbrPages, overrides: ['name' => 'test event']);

    $this->getJson(route('events.search', ['name' => 'test event', 'page' => $nbrPages + 1]))
        ->assertValid()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonFragment(['message' => "The page " . ($nbrPages + 1) . " does not exist"])->assertStatus(404);

    $this->getJson(route('events.search', ['name' => 'test event', 'page' => $nbrPages]))
        ->assertValid()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonCount(Constants::EVENTS_PER_PAGE, 'data');
});