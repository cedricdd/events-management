<?php

namespace App\Http\Requests;

use App\Constants;
use Illuminate\Foundation\Http\FormRequest;

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
        ];
    }
}
