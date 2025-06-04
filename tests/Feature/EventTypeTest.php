<?php

use App\Constants;
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

    Sanctum::actingAs($this->admin, );

    $this->postJson(route('event-types.store'), $data)
        ->assertValid()
        ->assertStatus(201)
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonFragment([
            'name' => $data['name'],
            'description' => $data['description'],
        ]);
});

test('event_types_store_validation', function () {
    $this->checkForm(
        route('events.store'),
        $this->getEventFormData(),
        [
            [['name', 'description'], 'required', ''],
            [['name', 'description'], 'string', 0],
            ['name', 'max.string', str_repeat('a', Constants::STRING_MAX_LENGTH + 1), ['max' => Constants::STRING_MAX_LENGTH]],
            ['description', 'max.string', str_repeat('a', Constants::DESCRIPTION_MAX_LENGTH + 1), ['max' => Constants::DESCRIPTION_MAX_LENGTH]],
        ],
        $this->admin,
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