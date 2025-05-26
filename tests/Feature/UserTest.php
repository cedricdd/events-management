<?php

use Laravel\Sanctum\Sanctum;

test('users_show', function () {
    Sanctum::actingAs($this->user);

    $this->getJson(route('user.show'))
        ->assertValid()
        ->assertExactJson([
            'data' => $this->getUserResource($this->user),
        ]);

    $this->getJson(route('user.show', $this->organizer->id))
        ->assertValid()
        ->assertExactJson([
            'data' => $this->getUserResource($this->organizer),
        ]);
});

test('users_show_dont_exist', function () {
    Sanctum::actingAs($this->user);

    $this->getJson(route('user.show', 1000))
        ->assertValid()
        ->assertExactJson(["message" => "User not found"]);
});
