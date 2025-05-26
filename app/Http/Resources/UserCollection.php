<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class UserCollection extends ResourceCollection
{
    public bool|null $extra;

    public function __construct($resource, $extra = false)
    {
        parent::__construct($resource);
        $this->extra = $extra;
    }

    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection->map(fn($user) => (new UserResource($user, $this->extra))),
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
