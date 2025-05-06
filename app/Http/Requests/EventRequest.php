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
        return [
            'name' => 'bail|required|string|max:' . Constants::STRING_MAX_LENGTH,
            'description' => 'bail|required|string|max:' . Constants::DESCRIPTION_MAX_LENGTH,
            'start_date' => 'bail|required|date|after_or_equal:today',
            'end_date' => 'bail|required|date|after_or_equal:start_date',
            'location' => 'bail|required|string|max:' . Constants::STRING_MAX_LENGTH,
            'price' => 'bail|required|numeric|min:0',
            'is_public' => 'bail|boolean',
        ];
    }
}
