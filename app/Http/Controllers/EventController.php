<?php

namespace App\Http\Controllers;

use App\Constants;
use App\Models\User;
use App\Models\Event;
use Illuminate\Http\Request;
use App\Http\Requests\EventRequest;
use App\Http\Resources\EventResource;
use App\Http\Resources\EventCollection;
use Symfony\Component\HttpFoundation\JsonResponse;

class EventController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): EventCollection
    {
        return new EventCollection(
                Event::with('user', 'attendees')
                ->where('end_date', '>=', now())
                ->orderBy('start_date', 'asc')
                ->paginate(Constants::EVENTS_PER_PAGE)
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(EventRequest $request): JsonResponse
    {
        $user = User::latest()->first(); //Temporary fix to associate the first user with the event
        $event = new Event();
        $event->name = $request->name;
        $event->description = $request->description;
        $event->start_date = $request->start_date;
        $event->end_date = $request->end_date;
        $event->price = $request->price;
        $event->location = $request->location;
        $event->is_public = $request->is_public;
        $event->user()->associate($user)->save(); 

        $event->setRelation('user', $user);

        return EventResource::make($event)
            ->additional(["message" => "Event created successfully"])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Event $event): EventResource
    {
        $event->load('user', 'attendees');

        return EventResource::make($event);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Event $event): JsonResponse
    {
        //TODO - Add validation for the request
        $event->name = $request->input('name', $event->name);
        $event->description = $request->input('description', $event->description);
        $event->start_date = $request->input('start_date', $event->start_date);
        $event->end_date = $request->input('end_date', $event->end_date);
        $event->price = $request->input('price', $event->price);
        $event->location = $request->input('location', $event->location);
        $event->is_public = $request->input('is_public', $event->is_public);
        $event->save();

        $event->load('user', 'attendees');

        return EventResource::make($event)
            ->additional(["message" => "Event updated successfully"])
            ->response();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Event $event): JsonResponse
    {
        //TODO - Add validation for the request
        $event->delete();

        return response()->json([
            'message' => 'Event deleted successfully',
        ]);
    }
}