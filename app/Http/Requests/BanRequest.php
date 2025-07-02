<?php

namespace App\Http\Requests;

use Knuckles\Scribe\Attributes\BodyParam;
use Illuminate\Foundation\Http\FormRequest;

#[BodyParam("users", "integer[]", "The IDs of the users to ban.", true, [1, 2, 3])]
class BanRequest extends FormRequest
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
            'users' => 'bail|required|array',
        ];
    }
}
