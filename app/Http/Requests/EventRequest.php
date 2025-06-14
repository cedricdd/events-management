<?php

namespace App\Http\Requests;

use App\Constants;
use Illuminate\Foundation\Http\FormRequest;

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
            'start_date' => 'bail|' . $ruleRequired . '|date|after:+' . Constants::MIN_HOURS_BEFORE_START_EVENT . ' hours',
            'end_date' => 'bail|' . $ruleRequired . '|date|after:start_date',
            'location' => 'bail|' . $ruleRequired . '|string|max:' . Constants::STRING_MAX_LENGTH,
            'cost' => 'bail|' . $ruleRequired . '|integer|min:0|max:100',
            'public' => 'bail|' . $ruleRequired . '|boolean',
            'type' => 'bail|' . $ruleRequired . '|string|exists:event_types,name',
        ];
    }
}
