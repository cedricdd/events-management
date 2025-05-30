<?php

namespace App\Http\Controllers;

use App\Constants;
use App\Models\User;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\EventResource;
use App\Http\Resources\UserCollection;
use App\Notifications\EventRegistrationNotification;
use App\Notifications\EventUnRegistrationNotification;

class AttendeeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, Event $event): JsonResponse|UserCollection
    {
        [$order, $direction] = cleanSorting($request->input('sort', ''), 'user');

        $attendees = $event->attendees()->orderBy(Constants::USER_SORTING_OPTIONS[$order], $direction)->paginate(Constants::ATTENDEES_PER_PAGE);

        if ($request->has('page') && $request->input('page') > $attendees->lastPage()) {
            return response()->json([
                'message' => "The page " . $request->input('page') . " does not exist",
            ], 404);
        }

        // Only add the sort parameter to the URL if it is not the default sorting
        if ($order !== Constants::USER_DEFAULT_SORTING || $direction !== 'asc') {
            $attendees->appends(['sort' => $order . ',' . $direction]);
        }

        return (new UserCollection($attendees))->additional(array_merge($this->getAdditionalData($event)));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Event $event, Request $request): JsonResponse|UserResource
    {
        if ($event->attendees()->where('user_id', $request->user()->id)->exists()) {
            return response()->json([
                'message' => 'You are already registered for this event.',
            ], 409);
        }

        //Make sure the user is not the organizer
        if ($event->user_id === $request->user()->id) {
            return response()->json([
                'message' => "You can't register to your own event.",
            ], 409);
        }

        // Make sure the event hasn't already started
        if ($event->start_date < now()) {
            return response()->json([
                'message' => "You can only register to an event before it start.",
            ], 403);
        }

        // Make sure the user has enough tokens to attend the event
        if ($event->cost > $request->user()->tokens) {
            return response()->json([
                'message' => "You don't have enough tokens to register for this event.",
            ], 403);
        }

        $request->user()->decrement('tokens', $event->cost);
        $request->user()->increment('tokens_spend', $event->cost);

        // Attach the user to the event
        $event->attendees()->attach($request->user());

        $request->user()->notify(new EventRegistrationNotification($event));

        $event->load(['organizer', 'type']);
        $event->loadCount('attendees');

        return UserResource::make($request->user(), true)
            ->additional(['event' => EventResource::make($event)])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Event $event, int $userID): UserResource
    {
        $attendee = $event->attendees()->where('user_id', $userID)->firstOrFail();

        return UserResource::make($attendee)
            ->additional($this->getAdditionalData($event));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Event $event, User $attendee): JsonResponse|Response
    {
        // Make the user is actually attending the event
        if (!$event->attendees()->where('user_id', $attendee->id)->exists()) {
            return response()->json([
                'message' => "This user is not registered to the event!",
            ], 403);
        }

        if ($request->user()->id == $attendee->id)
            $source = 'user';
        elseif ($request->user()->id == $event->user_id)
            $source = 'organizer';
        elseif ($request->user()->isAdmin())
            $source = 'admin';
        else {
            return response()->json([
                'message' => "You are not allowed to unregister this user from the event!",
            ], 403);
        }

        // Detach the attendee from the event
        $event->attendees()->detach($attendee->id);

        $attendee->notify(new EventUnRegistrationNotification($event, $source));

        // The attendee gets his tokens back
        $attendee->increment('tokens', $event->cost);
        $attendee->decrement('tokens_spend', $event->cost);

        return response()->noContent();
    }

    private function getAdditionalData(Event $event)
    {
        $additional = [];

        // User wants to get the event data
        if (strtolower(trim(request()->input('with', ''))) === 'event') {
            $event->load(['organizer', 'type']);
            $event->loadCount('attendees');

            $additional['event'] = EventResource::make($event);
        }

        return $additional;
    }
}
