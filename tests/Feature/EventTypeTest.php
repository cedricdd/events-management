<?php

use App\Constants;
use App\Models\EventType;
use Laravel\Sanctum\Sanctum;

test('event_types_index', function () {
    $response = $this->getJson(route('event-types.index'))
        ->assertValid()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonCount(count($this->types), 'data')
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'description',
                ],
            ],
        ]);

    expect(collect($response->json('data'))->contains($this->getEventTypeResource($this->types->first())))->toBeTrue();
    expect(collect($response->json('data'))->contains($this->getEventTypeResource($this->types->last())))->toBeTrue();
});

test('event_types_store', function () {
    $data = $this->getEventTypeFormData();
    $count = $this->types->count();

    Sanctum::actingAs($this->admin, );

    $this->postJson(route('event-types.store'), $data)
        ->assertValid()
        ->assertStatus(201)
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonFragment([
            'name' => $data['name'],
            'description' => $data['description'],
        ]);
    
    expect(EventType::count())->toBe($count + 1);
});

test('event_types_store_validation', function () {
    $this->checkForm(
        route: route('events.store'),
        defaults: $this->getEventFormData(),
        rules: [
            [['name', 'description'], 'required', ''],
            [['name', 'description'], 'string', 0],
            ['name', 'max.string', str_repeat('a', Constants::STRING_MAX_LENGTH + 1), ['max' => Constants::STRING_MAX_LENGTH]],
            ['description', 'max.string', str_repeat('a', Constants::DESCRIPTION_MAX_LENGTH + 1), ['max' => Constants::DESCRIPTION_MAX_LENGTH]],
        ],
        user: $this->admin,
    );
});

test('event_types_store_only_admin', function () {
    $data = $this->getEventTypeFormData();

    $this->postJson(route('event-types.store'), $data)
        ->assertUnauthorized()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonFragment([
            'message' => 'Unauthenticated.',
        ]);

    Sanctum::actingAs($this->user);

    $this->postJson(route('event-types.store'), $data)
        ->assertForbidden()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonFragment([
            'message' => 'This action is unauthorized.',
        ]);
});

test('event_types_store_duplicate', function () {
    Sanctum::actingAs($this->admin);

    $this->postJson(route('event-types.store'), $this->getEventTypeFormData(['name' => $this->types->first()->name]))
        ->assertUnprocessable()
        ->assertHeader('Content-Type', 'application/json')
        ->assertExactJson(['error' => 'Event type already exists']);
});

test('event_types_update', function () {
    $data = $this->getEventTypeFormData();
    $type = $this->types->first();
    $count = $this->types->count();

    Sanctum::actingAs($this->admin);

    $this->putJson(route('event-types.update',  $type), $data)
        ->assertValid()
        ->assertHeader('Content-Type', 'application/json')
        ->assertExactJson([
            'data' => [
                'id' => $type->id,
                'name' => $data['name'],
                'description' => $data['description'],
            ],
        ]);

    expect(EventType::count())->toBe($count);

    $type->refresh();

    expect($type->name)->toBe($data['name']);
    expect($type->description)->toBe($data['description']);
});

test('event_types_update_not_found', function () {
    Sanctum::actingAs($this->admin);

    $this->putJson(route('event-types.update', ['type' => EventType::count() + 1]), $this->getEventTypeFormData())
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/json')
        ->assertExactJson(['message' => 'EventType not found']);
});

test('event_types_update_only_admin', function () {
    $data = $this->getEventTypeFormData();
    $type = $this->types->first();

    $this->putJson(route('event-types.update',  $type), $data)
        ->assertUnauthorized()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonFragment([
            'message' => 'Unauthenticated.',
        ]);

    Sanctum::actingAs($this->user);

    $this->putJson(route('event-types.update',  $type), $data)
        ->assertForbidden()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonFragment([
            'message' => 'This action is unauthorized.',
        ]);
});

test('event_types_update_duplicate', function () {
    Sanctum::actingAs($this->admin);

    $type = $this->types->first();
    $data = $this->getEventTypeFormData(['name' => $this->types->last()->name]);

    $this->putJson(route('event-types.update',  $type), $data)
        ->assertUnprocessable()
        ->assertHeader('Content-Type', 'application/json')
        ->assertExactJson(['error' => 'Event type already exists']);
});

test('event_types_destroy', function () {
    $count = $this->types->count();

    Sanctum::actingAs($this->admin);

    $this->deleteJson(route('event-types.destroy', ['type' => $this->types->first()]))
        ->assertValid()
        ->assertNoContent();

    expect(EventType::find($this->types->first()->id))->toBeNull();
    expect(EventType::count())->toBe($count - 1);
});

test('event_types_destroy_only_admin', function () {
    $this->deleteJson(route('event-types.destroy', ['type' => $this->types->first()]))
        ->assertUnauthorized()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonFragment([
            'message' => 'Unauthenticated.',
        ]);

    Sanctum::actingAs($this->user);

    $this->deleteJson(route('event-types.destroy', ['type' => $this->types->first()]))
        ->assertForbidden()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonFragment([
            'message' => 'This action is unauthorized.',
        ]);
});

test('event_types_destroy_in_use', function () {
    Sanctum::actingAs($this->admin);

    $type = $this->types->first();
    $this->getEvents(count: Constants::EVENTS_PER_PAGE, type: $type);

    $this->deleteJson(route('event-types.destroy', ['type' => $type]))
        ->assertUnprocessable()
        ->assertHeader('Content-Type', 'application/json')
        ->assertExactJson(['error' => 'Cannot delete event type that is in use!']);

    expect(EventType::find($type->id))->toEqual($type);
});

test('event_types_destroy_not_found', function () {
    Sanctum::actingAs($this->admin);

    $this->deleteJson(route('event-types.destroy', ['type' => 9999]))
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/json')
        ->assertExactJson(['message' => 'EventType not found']);
});