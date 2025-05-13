<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('login_successful', function () {
    $user = User::factory()->create();

    $this->postJson(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertValid()
        ->assertJsonStructure([
            'token',
            'user'
        ])->assertStatus(200)
        ->assertJsonFragment([
            'user' => $this->getUserResource($user)
        ]);
});

test('login_need_to_be_guest', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $this->postJson(route('login'))->assertStatus(302);
});

test('login_invalid', function () {
    $user = User::factory()->create();

    $this->postJson(route('login'), ['email' => 'toto@gmail.com', 'password' => 'password'])
        ->assertInvalid(['email' => 'The provided credentials are incorrect.'])
        ->assertStatus(422);

    $this->postJson(route('login'), ['email' => $user->email, 'password' => 'wrongpassword'])
        ->assertInvalid(['email' => 'The provided credentials are incorrect.'])
        ->assertStatus(422);
});

test('login_form', function () {
    $user = User::factory()->create();

    $this->checkForm(route('login'), ['email' => $user->email, 'password' => 'password'], [
        [['email', 'password'], 'required', ''],
        ['email', 'email', 'invalid-email'],
    ]);
});

test('logout_need_to_be_auth', function () {
    $this->deleteJson(route('logout'))->assertUnauthorized();
});

test('logout_successful', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $this->deleteJson(route('logout'))->assertNoContent(); 

    $this->assertDatabaseMissing('personal_access_tokens', [
        'tokenable_id' => $user->id,
        'tokenable_type' => User::class,
    ]);
});