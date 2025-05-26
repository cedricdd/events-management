<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public bool|null $extra;

    public function __construct($resource, $extra = false)
    {
        parent::__construct($resource);
        $this->extra = $extra;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'country' => $this->country,
            'profession' => $this->profession,
            'phone' => $this->phone,
            'organization' => $this->organization,
            'tokens' => $this->when($this->extra, $this->tokens),
            'tokens_spend' => $this->when($this->extra, $this->tokens_spend),
        ];
    }
}
