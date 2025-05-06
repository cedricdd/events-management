<?php

use App\Constants;
use App\Models\Event;
use Illuminate\Support\Arr;

test('events_show', function () {
    $event = $this->getEvents(count: 1);

    $this->get(route('events.show', $event))
        ->assertValid()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonFragment([
            'id' => $event->id,
            'name' => $event->name,
            'description' => $event->description,
            'start_date' => $event->start_date->format('Y-m-d H:i:s'),
            'end_date' => $event->end_date->format('Y-m-d H:i:s'),
            'price' => $event->price,
            'location' => $event->location,
            'is_public' => $event->is_public ? 1 : 0,
            'user' => [
                'id' => $event->user->id,
                'name' => $event->user->name,
                'email' => $event->user->email,
            ],
        ]);
});

test('events_show_not_found', function () {
    $this->get(route('events.show', 1))
        ->assertValid()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonFragment([
            'message' => 'Event not found',
        ]);
});

test('events_store_successful', function () {
    $data = $this->getEventFormData();

    //TODO add user once it's managed
    $response = $this->post(route('events.store'), $data)
        ->assertValid()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonStructure([
            'message',
            'event' => [
                'id',
                'name',
                'description',
                'start_date',
                'end_date',
                'price',
                'location',
                'is_public',
                'user' => [
                    'id',
                    'name',
                    'email',
                ],
            ],
        ]);

    foreach ($data as $key => $value) {
        $response->assertJsonFragment([$key => $value]);
    }

    $this->assertDatabaseHas('events', $data);
});

test('events_validation', function () {
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
    $event = $this->getEvents(count: 1);

    $data = $this->getEventFormData();

    //TODO add user once it's managed
    $response = $this->put(route('events.update', $event), $data)
        ->assertValid()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonStructure([
            'message',
            'event' => [
                'id',
                'name',
                'description',
                'start_date',
                'end_date',
                'price',
                'location',
                'is_public',
                'user' => [
                    'id',
                    'name',
                    'email',
                ],
            ],
        ]);

    foreach ($data as $key => $value) {
        $response->assertJsonFragment([$key => $value]);
    }

    //Make sure the event is updated in the database
    $this->assertDatabaseHas('events', $data + ['id' => $event->id]);

    //Make sure the user didn't change
    expect($event->user->toArray())->toBe(Event::find($event->id)->user->toArray());
});

test("events_update_fields_optional", function () {
    $data = $this->getEventFormData();

    //TODO add user once it's managed
    foreach ($data as $key => $value) {
        $event = $this->getEvents(count: 1);

        if ($event->{$key} instanceof DateTime)
            $value = $event->{$key}->format('Y-m-d H:i:s');
        elseif (is_bool($event->{$key}))
            $value = $event->{$key} ? 1 : 0;
        else
            $value = $event->{$key};

        $this->put(route('events.update', $event), Arr::except($data, $key))
            ->assertValid()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonFragment([$key => $value]);
    }
});

test("events_destroy", function () {
    $event = $this->getEvents(count: 1);

    //TODO add user once it's managed
    $this->delete(route('events.destroy', $event))
        ->assertValid()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonFragment([
            'message' => "Event deleted successfully"
        ]);

    //Make sure the event is deleted from the database
    $this->assertDatabaseMissing('events', ['id' => $event->id]);
});