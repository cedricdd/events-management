<?php

use App\Constants;

test('events_store_successful', function () {
    $data = $this->getEventFormData();

    //TODO add user once it's managed
    $response = $this->post(route('events.store'), $data)
        ->assertValid()
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
            [['name', 'description', 'start_date', 'end_date', 'location', 'price'], 'required', ''],
            [['name', 'description', 'location'], 'string', 0],
            [['name', 'location'], 'max.string', str_repeat('a', Constants::STRING_MAX_LENGTH + 1), ['max' => Constants::STRING_MAX_LENGTH]],
            ['description', 'max.string', str_repeat('a', Constants::DESCRIPTION_MAX_LENGTH + 1), ['max' => Constants::DESCRIPTION_MAX_LENGTH]],
            [['start_date', 'end_date'], 'date', 'invalid-date'],
            ['start_date', 'after_or_equal', now()->subDay()->format('Y-m-d H:i:s'), ['date' => 'today']],
            ['end_date', 'after_or_equal', now()->format('Y-m-d H:i:s'), ['date' => 'start date']],
            ['price', 'numeric', 'invalide-price'],
            ['price', 'min.numeric', -10, ['min' => 0]],
        ]);
        //TODO add user once it's managed
});
