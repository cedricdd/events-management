<?php

namespace App\Http\Requests;

use App\Constants;
use Knuckles\Scribe\Attributes\BodyParam;
use Illuminate\Foundation\Http\FormRequest;

#[BodyParam("name", "string", "The name of the event. (Max " . Constants::STRING_MAX_LENGTH . " characters)", true)]
#[BodyParam("description", "string", "The description of the event. (Max " . Constants::DESCRIPTION_MAX_LENGTH . " characters)", true)]
#[BodyParam("start_date", "date", "The start date & time of the event, it needs to be at least " . Constants::MIN_HOURS_BEFORE_START_EVENT . " hours in the future.<br/>", true, example: "2025-01-31 08:00:00")]
#[BodyParam("end_date", "date", "The end date & time of the event, it needs to be set after the start date.<br/>", true, example: "2025-02-10 20:30:00")]
#[BodyParam("location", "string", "The location at which the event will take place.", true, "Online")]
#[BodyParam("cost", "integer", "The amount of tokens each attendees will have to pay to join the event. [0;100]", true)]
#[BodyParam("public", "boolean", "Is this event a public event, in which case any users will have the ability to join.", true)]
#[BodyParam("type", "string", "The type of the event, it needs to be one of our existing event types.<br/>", true, example: "Conference")]
class EventRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $ruleRequired = $this->routeIs('events.store') ? 'required' : 'sometimes|required';

        return [
            'name' => 'bail|' . $ruleRequired . '|string|max:' . Constants::STRING_MAX_LENGTH,
            'description' => 'bail|' . $ruleRequired . '|string|max:' . Constants::DESCRIPTION_MAX_LENGTH,
            'start_date' => 'bail|' . $ruleRequired . '|date_format:Y-m-d H:i:s|after:+' . Constants::MIN_HOURS_BEFORE_START_EVENT . ' hours',
            'end_date' => 'bail|' . $ruleRequired . '|date_format:Y-m-d H:i:s|after:start_date',
            'location' => 'bail|' . $ruleRequired . '|string|max:' . Constants::STRING_MAX_LENGTH,
            'cost' => 'bail|' . $ruleRequired . '|integer|min:0|max:100',
            'public' => 'bail|' . $ruleRequired . '|boolean',
            'type' => 'bail|' . $ruleRequired . '|string|exists:event_types,name',
        ];
    }
}
