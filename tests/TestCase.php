<?php

namespace Tests;

use App\Models\User;
use Illuminate\Support\Facades\Lang;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    protected function getEventFormData(array $overrides = []): array {
        return $overrides + [
            'name' => 'Test Event',
            'description' => 'Phasellus ac nisl vitae metus blandit suscipit. Fusce rutrum faucibus accumsan. Integer ultrices aliquet nulla, vitae posuere turpis gravida ut. Ut nec suscipit nulla, vel varius velit. Nullam porttitor pharetra augue eget condimentum. Cras ut vulputate mauris. Fusce fringilla ultricies elit ut consequat. Vivamus nec diam a diam lobortis faucibus. Sed porttitor interdum odio, vitae lacinia dui eleifend a. Mauris dui libero, egestas ut vestibulum in, blandit non nibh. Cras egestas iaculis pharetra. Integer lacus ipsum, gravida ut massa eleifend, imperdiet volutpat mauris. Ut vehicula elementum rhoncus.',
            'start_date' => now()->addDay()->setTime(12, 0)->format('Y-m-d H:i:s'),
            'end_date' => now()->addDay()->setTime(18, 0)->format('Y-m-d H:i:s'),
            'location' => 'Online',
            'price' => 10.00,
        ];
    }

    /**
     * Checks form validation by submitting POST requests to a given route with various field values and asserting validation errors.
     *
     * @param string $route The route to which the form is submitted.
     * @param array $defaults Default form data to include in every request.
     * @param array $rules An array of validation rules, where each rule is an array containing:
     *                     - string|array $field: The field name(s) to test.
     *                     - string $rule: The validation rule key (e.g., 'required', 'email').
     *                     - mixed $value: The value to test for the field.
     *                     - array|null $params: (Optional) Additional parameters for the validation message.
     * @param User|null $user (Optional) The user to authenticate as when making the request.
     *
     * @return void
     */
    protected function checkForm(string $route, array $defaults, array $rules, ?User $user = null): void
    {

        foreach ($rules as $infos) {
            // Allow $infos[0] to be a string or an array of strings
            $fields = is_array($infos[0]) ? $infos[0] : [$infos[0]];

            foreach ($fields as $field) {
                //Get the error message based on the rule used
                $attribute = Lang::has("validation.attributes.{$field}") ? Lang::get("validation.attributes.{$field}") : str_replace('_', ' ', $field);
                $error = Lang::get("validation.{$infos[1]}", compact('attribute') + ($infos[3] ?? []));

                // dump("Checking field: {$field} with value: {$infos[2]} and error: {$error}");

                $request = $user ? $this->actingAs($user) : $this;

                $request->post($route, [$field => $infos[2]] + $defaults)
                    ->assertStatus(302)
                    ->assertInvalid([$field => $error]); // Assert validation errors
            }
        }
    }
}
