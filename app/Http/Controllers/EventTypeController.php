<?php

namespace App\Http\Controllers;

use App\Models\EventType;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\EventTypeRequest;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\UrlParam;
use App\Http\Resources\EventTypeResource;
use App\Http\Resources\EventTypeCollection;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;

/**
 * @group Event Types
 * 
 * Handles event types, allowing administrators to create, update, and delete event types.
 */
class EventTypeController extends Controller
{
    /**
     * List Event Types
     * 
     * Lists all available event types.
     */
    #[ResponseFromApiResource(EventTypeCollection::class, EventType::class, 200)]
    public function index(): EventTypeCollection
    {
        $types = EventType::orderby('name', 'asc')->get();

        return new EventTypeCollection($types);
    }

    /**
     * Create Event Type
     * 
     * Creates a new event type. Only administrators can create event types.
     * 
     * @authenticated
     */
    #[Response('{"message": "Unauthenticated."}', 401)]
    #[Response('{"message": "This action is unauthorized."}', 403)]
    #[Response('{"message": "Event type already exists!"}', 422)]
    #[ResponseFromApiResource(EventTypeResource::class, EventType::class, 201)]
    public function store(EventTypeRequest $request): JsonResponse
    {
        $name = ucwords(trim($request->name));

        // Check if the event type already exists
        if (EventType::where('name', $name)->exists())
            return response()->json(['error' => 'Event type already exists!'], 422);

        $type = new EventType();
        $type->name = $name;
        $type->description = $request->description;
        $type->save();

        return response()->json(new EventTypeResource($type), 201);

    }

    /**
     * Update Event Type
     * 
     * Updates an existing event type. Only administrators can update event types.
     * 
     * @authenticated
     */
    #[UrlParam("type_id", "int", "The ID of the type", true, "1")]

    #[Response('{"message": "Unauthenticated."}', 401)]
    #[Response('{"message": "This action is unauthorized."}', 403)]
    #[Response('{"message": "EventType not found."}', 404)]
    #[Response('{"message": "Event type already exists!"}', 422)]
    #[ResponseFromApiResource(EventTypeResource::class, EventType::class, 200)]
    public function update(EventTypeRequest $request, EventType $type)
    {
        $name = ucwords(trim($request->name));

        // Check if the event type already exists
        if (EventType::where('name', $name)->where('id', '!=', $type->id)->exists())
            return response()->json(['error' => 'Event type already exists!'], 422);

        $type->name = $name;
        $type->description = $request->description;
        $type->save();

        return new EventTypeResource($type);
    }

    /**
     * Delete Event Type
     * 
     * Deletes an event type. A type cannot be deleted if it is used by any event.<br/>Only administrators can delete event types.
     * 
     * @authenticated
     */
    #[UrlParam("type_id", "int", "The ID of the type", true, "1")]

    #[Response('{"message": "Unauthenticated."}', 401)]
    #[Response('{"message": "This action is unauthorized."}', 403)]
    #[Response('{"message": "EventType not found."}', 404)]
    #[Response('{"message": "Cannot delete event type that is in use!"}', 422)]
    #[Response(status: 204)]
    public function destroy(EventType $type): JsonResponse|\Illuminate\Http\Response
    {
        // A type cannot be deleted if it is used by any event
        if ($type->events()->exists()) {
            return response()->json(['error' => 'Cannot delete event type that is in use!'], 422);
        }

        $type->delete();

        return response()->noContent();
    }
}
