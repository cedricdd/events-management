<?php

namespace App\Http\Controllers;

use App\Constants;
use App\Models\EventType;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Resources\EventTypeResource;
use App\Http\Resources\EventTypeCollection;

class EventTypeController extends Controller
{
    public function index(): EventTypeCollection
    {
        $types = EventType::orderby('name', 'asc')->get();

        return new EventTypeCollection($types);
    }
    public function store(Request $request): JsonResponse {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string|max:' . Constants::DESCRIPTION_MAX_LENGTH,
        ]);

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

    public function update($id) {

    }

    public function destroy($id) {
    }
}
