<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class EventCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection->map(fn($event) => (new EventResource($event, false))),
        ];
    }

    public function paginationInformation($request, $paginated, $default)
    {
        unset($default['meta']['links']);
        unset($default['meta']['from']);
        unset($default['meta']['to']);
        
        return $default;
    }
}
