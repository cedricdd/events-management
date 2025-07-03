<?php

namespace App\Http\Requests;

use App\Constants;
use Knuckles\Scribe\Attributes\BodyParam;
use Illuminate\Foundation\Http\FormRequest;

#[BodyParam("name", "string", "Find all events where the name contains this expression. (Max " . Constants::STRING_MAX_LENGTH . " characters)<br/>", false, )]
#[BodyParam("description", "string", "Find all events where the descritption contains this expression. (Max " . Constants::STRING_MAX_LENGTH . " characters)<br/>", false)]
#[BodyParam("location", "string", "Find all events where the location contains this expression. (Max " . Constants::STRING_MAX_LENGTH . " characters)<br/>", false)]
#[BodyParam("cost_min", "integer", "Find all events where the cost to join is at least the given value. [value, inf[", false)]
#[BodyParam("cost_max", "integer", "Find all events where the cost to join is at max the given value. [0, value]", false)]
#[BodyParam("starts_before", "date", "Find all events where the start date is before or equal to this date. Date format: Y-m-d H:i:s<br/>", false, "2025-12-25 12:30:00")]
#[BodyParam("starts_after", "date", "Find all events where the start date is after or equal to this date. Date format: Y-m-d H:i:s<br/>", false, "2025-12-25 12:30:00")]
#[BodyParam("ends_before", "date", "Find all events where the end date is before or equal to this date. Date format: Y-m-d H:i:s<br/>", false, "2025-12-25 12:30:00")]
#[BodyParam("ends_after", "date", "Find all events where the end date is after or equal to this date. Date format: Y-m-d H:i:s<br/>", false, "2025-12-25 12:30:00")]
#[BodyParam("type", "string", "Find all events with the given type.", false, "Conference")]
#[BodyParam("attendees_min", "integer", "Find all events who have at least the given value of attendees. [value, inf[", false)]
#[BodyParam("attendees_max", "integer", "Find all events who have at max the given value of attendees. [0, value]", false)]
#[BodyParam("public", "boolean", "Find all events who are either public or private.", false)]
#[BodyParam("organizer", "integer", "Find all events who are associated with this organizer.", false)]
class SearchEventRequest extends FormRequest
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
        return [
            'name' => 'bail|sometimes|string|max:' . Constants::STRING_MAX_LENGTH,
            'description' => 'bail|sometimes|string|max:' . Constants::STRING_MAX_LENGTH,
            'location' => 'bail|sometimes|string|max:' . Constants::STRING_MAX_LENGTH,
            'cost_min' => 'bail|sometimes|integer|min:0',
            'cost_max' => 'bail|sometimes|integer|min:0',
            'starts_before' => 'bail|sometimes|date_format:Y-m-d H:i:s',
            'starts_after' => 'bail|sometimes|date_format:Y-m-d H:i:s',
            'ends_before' => 'bail|sometimes|date_format:Y-m-d H:i:s',
            'ends_after' => 'bail|sometimes|date_format:Y-m-d H:i:s',
            'type' => 'bail|sometimes|string|exists:event_types,name',
            'attendees_min' => 'bail|sometimes|integer|min:0',
            'attendees_max' => 'bail|sometimes|integer|min:0',
            'public' => 'bail|sometimes|boolean',
            'organizer' => 'bail|sometimes|integer',
        ];
    }
}
