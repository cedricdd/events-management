<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
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
            'description' => $this->description,
            'location' => $this->location,
            'cost' => $this->cost,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'type' => $this->whenLoaded('type', fn() => $this->type->name),
            'public' => $this->public ? "yes" : "no",
            'attendees_count' => $this->whenCounted('attendees'),
            'organizer' => UserResource::make($this->whenLoaded('organizer')),
        ];
    }
}
