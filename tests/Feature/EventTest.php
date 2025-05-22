<?php

use App\Constants;
use App\Models\Event;
use Illuminate\Support\Arr;
use Laravel\Sanctum\Sanctum;
use PHPUnit\TextUI\Configuration\Constant;

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
                    'start_date',
                    'end_date',
                    'cost',
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
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'cost' => $data['cost'],
                'location' => $data['location'],
                'is_public' => $data['is_public'],
                'organizer' => $this->getUserResource($this->organizer),
            ],
            "message" => 'Event created successfully',
        ]);

    $this->assertDatabaseHas('events', $data);
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
        route('events.store'),
        $this->getEventFormData(),
        [
            [['name', 'description', 'start_date', 'end_date', 'location', 'cost', 'is_public'], 'required', ''],
            [['name', 'description', 'location'], 'string', 0],
            [['name', 'location'], 'max.string', str_repeat('a', Constants::STRING_MAX_LENGTH + 1), ['max' => Constants::STRING_MAX_LENGTH]],
            ['description', 'max.string', str_repeat('a', Constants::DESCRIPTION_MAX_LENGTH + 1), ['max' => Constants::DESCRIPTION_MAX_LENGTH]],
            [['start_date', 'end_date'], 'date', 'invalid-date'],
            ['start_date', 'after', now()->format('Y-m-d H:i:s'), ['date' => '+' . Constants::MIN_HOURS_BEFORE_START_EVENT . ' hours']],
            ['end_date', 'after', now()->format('Y-m-d H:i:s'), ['date' => 'start date']],
            ['cost', 'integer', 'invalide-cost'],
            ['cost', 'min.numeric', -10, ['min' => 0]],
            ['cost', 'max.numeric', 1000, ['max' => 100]],
            ['is_public', 'boolean', 'invalid-boolean'],
        ],
        $this->organizer,
    );
});

test('events_update_successful', function () {
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
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'cost' => $data['cost'],
                'location' => $data['location'],
                'is_public' => $data['is_public'],
                'attendees_count' => 0,
                'organizer' => $this->getUserResource($this->organizer),
            ],
            "message" => 'Event updated successfully',
        ]);

    //Make sure the event is updated in the database
    $this->assertDatabaseHas('events', $data + ['id' => $event->id]);

    //Make sure the organizer didn't change
    expect($event->organizer->toArray())->toBe(Event::find($event->id)->organizer->toArray());
});

test('events_update_by_admin', function () {
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
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'cost' => $data['cost'],
                'location' => $data['location'],
                'is_public' => $data['is_public'],
                'attendees_count' => 0,
                'organizer' => $this->getUserResource($event->organizer),
            ],
            "message" => 'Event updated successfully',
        ]);

    //Make sure the event is updated in the database
    $this->assertDatabaseHas('events', $data + ['id' => $event->id]);
});

test("events_update_fields_optional", function () {
    $event = $this->getEvents(count: 1, organizer: $this->organizer);

    $data = $this->getEventFormData(['is_public' => 0]);

    Sanctum::actingAs($this->organizer);

    foreach ($data as $key => $value) {
        $this->putJson(route('events.update', $event), [$key => $value])
            ->assertValid()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonFragment([$key => $value]);
    }
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
    $attendeesCount = 5;
    $event = $this->getEvents(count: 1, attendees: $attendeesCount, organizer: $this->organizer);

    $data = $this->getEventFormData();

    Sanctum::actingAs($this->organizer);

    // When an event has attendees, we don't allow any changes other than name & description
    $this->putJson(route('events.update', $event), $data)
        ->assertValid()
        ->assertHeader('Content-Type', 'application/json')
        ->assertExactJson([
            'data' => [
                'id' => $event->id,
                'name' => $data['name'],
                'description' => $data['description'],
                'start_date' => $event->start_date->format('Y-m-d H:i:s'),
                'end_date' => $event->end_date->format('Y-m-d H:i:s'),
                'cost' => $event->cost,
                'location' => $event->location,
                'is_public' => $event->is_public ? 1 : 0,
                'attendees_count' => $attendeesCount,
                'organizer' => $this->getUserResource($event->organizer),
            ],
            "message" => 'Event updated successfully',
        ]);
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

test("events_destroy_by_owner", function () {
    $event = $this->getEvents(count: 1, organizer: $this->organizer);

    Sanctum::actingAs($this->organizer);

    $this->deleteJson(route('events.destroy', $event))
        ->assertValid()
        ->assertStatus(204);

    //Make sure the event is deleted from the database
    $this->assertDatabaseMissing('events', ['id' => $event->id]);
});

test("events_destroy_by_admin", function () {
    $event = $this->getEvents(count: 1);

    Sanctum::actingAs($this->admin);

    $this->deleteJson(route('events.destroy', $event))
        ->assertValid()
        ->assertStatus(204);

    //Make sure the event is deleted from the database
    $this->assertDatabaseMissing('events', ['id' => $event->id]);
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
                    'start_date',
                    'end_date',
                    'cost',
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
        ->assertJsonFragment([
            'current_page' => 1,
            'last_page' => 1,
            'per_page' => Constants::EVENTS_PER_PAGE,
            'total' => Constants::EVENTS_PER_PAGE,
        ]);

    expect(collect($response->json('data'))->contains($eventFirst))->toBeTrue();
    expect(collect($response->json('data'))->contains($eventLast))->toBeTrue();
});