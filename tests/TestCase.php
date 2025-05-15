<?php

namespace Tests;

use App\Models\User;
use App\Models\Event;
use Illuminate\Http\Request;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\Lang;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Resources\Json\JsonResource;
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
            'cost' => 10,
            'is_public' => true,
        ];
    }

    protected function getEvents(int $count = 10, ?User $organizer = null, int|string $attendees = 0): Event|Collection {
        $count = max(1, $count); // Ensure at least 1 event is created

        $events = Event::factory()->count($count)
            ->when($organizer, function ($query) use ($organizer) {
                return $query->for($organizer, 'organizer');
            })
            ->create();

        // Attach attendees to the events
        if ($attendees) {
            if(!is_int($attendees) && $attendees != "random") {
                throw new \InvalidArgumentException("Attendees must be an integer or 'random'.");
            }

            $events->each(function ($event) use ($attendees) {
                if($attendees == "random") $event->attendees()->attach(User::factory()->count(random_int(1, 10))->create());
                else $event->attendees()->attach(User::factory()->count($attendees)->create());
            });
        }

        if($count > 1) return $events;
        else return $events->first();
    }

    protected function getEventResource(Event $event, bool $withOrganizer = false, bool $withAttendees = false): array {
        $data = [
            'id' => $event->id,
            'name' => $event->name,
            'description' => $event->description,
            'location' => $event->location,
            'cost' => $event->cost,
            'start_date' => $event->start_date->format('Y-m-d H:i:s'),
            'end_date' => $event->end_date->format('Y-m-d H:i:s'),
            'is_public' => $event->is_public ? 1 : 0,
        ];

        if ($withOrganizer) {
            $data['organizer'] = $this->getUserResource($event->organizer);
        }

        if($withAttendees) {
            $data['attendees'] = $event->attendees->map(function ($user) {
                return $this->getUserResource($user);
            })->toArray();
        }

        return $data;
    }

    protected function getUserResource(User $user): array {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'country' => $user->country,
            'profession' => $user->profession,
            'phone' => $user->phone,
            'organization' => $user->organization,
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

                if($user !== null) {
                    Sanctum::actingAs($user);
                }

                $this->postJson($route, [$field => $infos[2]] + $defaults)
                    ->assertUnprocessable()
                    ->assertInvalid([$field => $error]); // Assert validation errors
            }
        }
    }
}
