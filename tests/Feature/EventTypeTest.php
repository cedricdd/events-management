<?php

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
