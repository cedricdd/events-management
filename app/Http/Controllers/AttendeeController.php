<?php

namespace App\Http\Controllers;

use App\Constants;
use App\Models\User;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Resources\UserResource;
use App\Http\Resources\EventResource;
use App\Http\Resources\UserCollection;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\UrlParam;
use Knuckles\Scribe\Attributes\QueryParam;
use App\Notifications\EventRegistrationNotification;
use App\Notifications\EventUnRegistrationNotification;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;

/**
 * 
 * @group Attendee
 * 
 * Handles event attendees, allowing users to register, view, and unregister from events.
 */
class AttendeeController extends Controller
{
    /**
     * Attendees From Event
     * 
     * Shows a paginated list of attendees for a specific event, with optional sorting and additional event data.
     */
    #[UrlParam("event_id", "int", "The ID of the event to retrieve attendees for.", true, "1")]
    
    #[QueryParam("page", "int", "The results are paginated, you will get " . Constants::ATTENDEES_PER_PAGE . " results per page.", false, 2)]
    #[QueryParam("sort", "string", "The sorting criteria for the attendees. Default is 'user,asc'.<br/>Consisting of two parts, the sorting criteria and the sorting order (asc or desc).", false, "registration,desc", enum: ["name,*order*", "country,*order*", "registration,*order*"])]
    #[QueryParam("with", "string", "The additional data to include in the response.", false, enum: ["event"])]
    
    #[Response('{"message": "Event not found."}', 404)]
    #[Response('{"message": "The page 10 does not exist."}', 404)]
    #[ResponseFromApiResource(UserCollection::class, User::class, 200, paginate: Constants::ATTENDEES_PER_PAGE)]
    public function index(Request $request, Event $event): JsonResponse|UserCollection
    {
        [$order, $direction] = cleanSorting($request->input('sort', ''), 'user');

        $attendees = $event->attendees()->orderBy(Constants::USER_SORTING_OPTIONS[$order], $direction)->paginate(Constants::ATTENDEES_PER_PAGE);

        if ($request->has('page') && $request->input('page') > $attendees->lastPage()) {
            return response()->json([
                'message' => "The page " . $request->input('page') . " does not exist.",
            ], 404);
        }

        // Only add the sort parameter to the URL if it is not the default sorting
        if ($order !== Constants::USER_DEFAULT_SORTING || $direction !== 'asc') {
            $attendees->appends(['sort' => $order . ',' . $direction]);
        }

        $additional = [];

        // User wants to get the event data
        if (strtolower(trim(request()->input('with', ''))) === 'event') {
            $event->load(['organizer', 'type']);
            $event->loadCount('attendees');

            $additional['event'] = EventResource::make($event);

            $attendees->appends(['with' => 'event']);
        }

        return (new UserCollection($attendees))->additional($additional);
    }

    /**
     * Register To Event
     * 
     * Allows a user to register for an event, provided they meet the necessary conditions such as not being already registered, not being the organizer, having enough tokens, and being invited if the event is private.
     */
    #[UrlParam("event_id", "int", "The ID of the event to register for.", true, "1")]
    
    #[Response('{"message": "Unauthenticated."}', 401)]
    #[Response('{"message": "You can only register to an event before it start."}', 403)]
    #[Response('{"message": "You are not invited to this event."}', 403)]
    #[Response('{"message": "You don\'t have enough tokens to register for this event."}', 403)]
    #[Response('{"message": "The organizer of this event does not allow you to join the event."}', 403)]
    #[Response('{"message": "Event not found."}', 404)]
    #[Response('{"message": "You are already registered for this event."}', 409)]
    #[Response('{"message": "You can\'t register to your own event."}', 409)]
    #[ResponseFromApiResource(EventResource::class, Event::class, 201, with: ['organizer', 'type'], withCount: ['attendees'], additional: ['message' => 'You have successfully registered for the event.'])]
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

        // If the event is private, the user must be invited
        if (!$event->public && !$event->invitedUsers()->where('user_id', $request->user()->id)->exists()) {
            return response()->json([
                'message' => "You are not invited to this event.",
            ], 403);
        }

        // The organizer of the event has banned the user
        if($event->organizer->bannedUsers()->where('attendee_id', $request->user()->id)->exists()) {
            return response()->json([
                'message' => "The organizer of this event does not allow you to join the event.",
            ], 403);
        }

        $request->user()->decrement('tokens', $event->cost);
        $request->user()->increment('tokens_spend', $event->cost);

        // Attach the user to the event
        $event->attendees()->attach($request->user());

        $request->user()->notify(new EventRegistrationNotification($event->id));

        $event->load(['organizer', 'type']);
        $event->loadCount('attendees');

        return EventResource::make($event)
            ->additional(['message' => "You have successfully registered for the event."])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Unregister From Event
     * 
     * Allows a user to unregister from an event, provided they are registered and the request is made by the user themselves, the event organizer, or an admin.
     */
    #[UrlParam("event_id", "int", "The ID of the event to unregister from.", true, "1")]
    #[UrlParam("user", "int", "The ID of the user to unregister. If not provided, the authenticated user will be unregistered.", false, "2")]
    
    #[Response('{"message": "Unauthenticated."}', 401)]
    #[Response('{"message": "You are not registered to this event!"}', 403)]
    #[Response('{"message": "You are not allowed to unregister this user from the event!"}', 403)]
    #[Response('{"message": "Event not found."}', 404)]
    #[Response('{"message": "User not found."}', 404)]
    #[Response(status: 204)]
    public function destroy(Request $request, Event $event, User|null $user = null): JsonResponse|\Illuminate\Http\Response
    {
        // If the attendee is not provided, use the authenticated user
        if ($user === null) {
            $user = $request->user();
        }

        // Make the user is actually attending the event
        if (!$event->attendees()->where('user_id', $user->id)->exists()) {
            return response()->json([
                'message' => $user->is($request->user())
                    ? "You are not registered to this event!"
                    : "This user is not registered to this event!",
            ], 403);
        }

        if ($request->user()->id == $user->id)
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
        $event->attendees()->detach($user->id);

        $user->notify(new EventUnRegistrationNotification($event->id, $source));

        // The attendee gets his tokens back
        $user->increment('tokens', $event->cost);
        $user->decrement('tokens_spend', $event->cost);

        return response()->noContent();
    }
}
