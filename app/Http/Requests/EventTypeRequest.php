<?php

namespace App\Http\Requests;

use App\Constants;
use Knuckles\Scribe\Attributes\BodyParam;
use Illuminate\Foundation\Http\FormRequest;

#[BodyParam("name", "string", "The name of the event type. (Max " . Constants::STRING_MAX_LENGTH . " characters)", true, "Concert")]
#[BodyParam("description", "string", "The description of the event type. (Max " . Constants::DESCRIPTION_MAX_LENGTH . " characters)", true, "Concert")]
class EventTypeRequest extends FormRequest
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
            'name' => 'required|string|max:' . Constants::STRING_MAX_LENGTH,
            'description' => 'required|string|max:' . Constants::DESCRIPTION_MAX_LENGTH,
        ];
    }
}
