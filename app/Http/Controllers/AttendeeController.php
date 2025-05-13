<?php

namespace App\Http\Controllers;

use App\Constants;
use App\Models\Event;
use Illuminate\Http\Request;
use App\Http\Resources\UserResource;
use App\Http\Resources\EventResource;
use App\Http\Resources\UserCollection;

class AttendeeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Event $event)
    {
        // Return the list of attendees
        return new UserCollection($event->attendees()
            ->orderBy('name')
            ->paginate(Constants::ATTENDEES_PER_PAGE))
            ->additional($this->getAdditionalData($event));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Event $event, Request $request)
    {
        if($event->attendees()->where('user_id', $request->user()->id)->exists()) {
            return response()->json([
                'message' => 'User is already attending this event.',
            ], 422);
        }

        // Attach the user to the event
        $event->attendees()->attach($request->user());

        // Load the user and event data
        $event->load('organizer');

        return UserResource::make($request->user())
            ->additional(['event' => EventResource::make($event)])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Event $event, int $userID)
    {
        $attendee = $event->attendees()->where('user_id', $userID)->firstOrFail();

        $event->load('organizer');

        return UserResource::make($attendee)
            ->additional($this->getAdditionalData($event));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Event $event, int $attendeeID)
    {
        // Detach the attendee from the event
        $event->attendees()->detach($attendeeID);

        return response()->noContent();
    }

    private function getAdditionalData(Event $event)
    {
        $additional = [];

        // User wants to get the event data
        if(strtolower(trim(request()->input('with', ''))) === 'event') {
            $event->load('organizer');
            
            $additional['event'] = EventResource::make($event);
        }

        return $additional;
    }
}
