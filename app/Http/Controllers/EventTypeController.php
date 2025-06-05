<?php

namespace App\Http\Controllers;

use App\Models\EventType;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\EventTypeRequest;
use App\Http\Resources\EventTypeResource;
use App\Http\Resources\EventTypeCollection;

class EventTypeController extends Controller
{
    public function index(): EventTypeCollection
    {
        $types = EventType::orderby('name', 'asc')->get();

        return new EventTypeCollection($types);
    }
    public function store(EventTypeRequest $request): JsonResponse
    {
        $name = ucwords(trim($request->name));

        // Check if the event type already exists
        if (EventType::where('name', $name)->exists())
            return response()->json(['error' => 'Event type already exists'], 422);

        $type = new EventType();
        $type->name = $name;
        $type->description = $request->description;
        $type->save();

        return response()->json(new EventTypeResource($type), 201);

    }

    public function update(EventTypeRequest $request, EventType $type)
    {
        $name = ucwords(trim($request->name));

        // Check if the event type already exists
        if (EventType::where('name', $name)->where('id', '!=', $type->id)->exists())
            return response()->json(['error' => 'Event type already exists'], 422);

        $type->name = $name;
        $type->description = $request->description;
        $type->save();

        return new EventTypeResource($type);
    }

    public function destroy(EventType $type): JsonResponse|Response
    {
        // A type cannot be deleted if it is used by any event
        if ($type->events()->exists()) {
            return response()->json(['error' => 'Cannot delete event type that is in use!'], 422);
        }

        $type->delete();

        return response()->noContent();
    }
}
