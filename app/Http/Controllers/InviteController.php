<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Event;
use App\Models\Invite;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Jobs\SendEventInviteEmail;
use App\Http\Requests\InviteRequest;
use App\Http\Resources\UserResource;
use App\Http\Resources\UserCollection;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\UrlParam;
use App\Jobs\SendEventInviteDeletionEmail;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;

/**
 * @group Event Invites
 * 
 * Handles event invitations, allowing organizers to invite users to private events.
 * 
 * @authenticated
 */
class InviteController extends Controller
{

    /**
     * Get Invites for an Event
     * 
     * Lists all users invited to a private event. You need to be the organizer of the event or an administrator to view the invites. 
     */
    #[UrlParam("event_id", "int", "The ID of the event for which to retrieve invites.", true, "1")]
    #[Response('{"message": "Unauthenticated."}', 401)]
    #[Response('{"message": "This event is a public event, there are no invites."}', 403)]
    #[Response('{"message": "This action is unauthorized."}', 403)]
    #[Response('{"message": "Event not found."}', 404)]
    #[ResponseFromApiResource(UserCollection::class, User::class, 200)]
    public function index(Request $request, Event $event): JsonResponse|UserCollection
    {
        // Make sure the event is private
        if ($event->public) {
            return response()->json(['message' => "This event is a public event, there are no invites."], 403);
        }

        // Get all invites for the event
        $invites = $event->invitedUsers()->orderBy('name', 'asc')->get();

        return new UserCollection($invites);
    }

    /**
     * Create Invites for an Event
     * 
     * Allows the organizer of a private event to invite users to the event.
     */
    #[UrlParam("event_id", "int", "The ID of the event to which users will be invited.", true, "1")]
    #[Response('{"message": "Unauthenticated."}', 401)]
    #[Response('{"message": "This action is unauthorized."}', 403)]
    #[Response('{"message": "You are not authorized to invite users to this event."}', 403)]
    #[Response('{"message": "You cannot invite users to an event that has already started."}', 403)]
    #[Response('{"message": "You can only invite users to private events."}', 403)]
    #[Response('{"message": "Event not found."}', 404)]
    #[ResponseFromApiResource(UserCollection::class, User::class, 201, additional: ['message' => 'Invites created successfully.'])]
    public function store(InviteRequest $request, Event $event): JsonResponse
    {
        // First make sure the user is the organizer of the event
        if ($request->user()->is($event->organizer) == false) {
            return response()->json(['message' => 'You are not authorized to invite users to this event.'], 403);
        }

        // Make sure the event is not in the past
        if ($event->start_date < now()) {
            return response()->json(['message' => 'You cannot invite users to an event that has already started.'], 403);
        }

        // Make sure the event is private
        if ($event->public) {
            return response()->json(['message' => 'You can only invite users to private events.'], 403);
        }

        // Create invites for each user
        $invites = [];
        foreach ($request->input('users') as $userId) {
            if (!is_integer($userId) || $userId <= 0)
                continue;

            $userInvited = User::find($userId);

            // That user doesn't exist or it's the organizer of the event
            if ($userInvited == false || $userInvited->is($request->user()))
                continue;

            // Make sure the user is not already invited to the event
            if (!Invite::where('user_id', $userId)->where('event_id', $event->id)->exists()) {
                $event->invitedUsers()->attach($userInvited->id);

                SendEventInviteEmail::dispatch($event->id, $userInvited->id)->delay(now()->addMinutes(value: 10));
            }

            $invites[] = new UserResource($userInvited);
        }

        return new UserCollection($invites)->additional([
            'message' => 'Invites created successfully.',
        ])->response()->setStatusCode(201);
    }

    /**
     * Remove Invites for an Event
     * 
     * Allows the organizer of a private event to remove invites for users.
     */
    #[UrlParam("event_id", "int", "The ID of the event from which users will be removed.", true, "1")]
    #[Response('{"message": "Unauthenticated."}', 401)]
    #[Response('{"message": "This action is unauthorized."}', 403)]
    #[Response('{"message": "Event not found."}', 404)]
    #[ResponseFromApiResource(UserCollection::class, User::class, 200, additional: ['message' => 'Invites removed successfully.'])]
    public function destroy(InviteRequest $request, Event $event): Response|JsonResponse
    {
        $users = [];

        foreach ($request->input('users') as $userID) {
            if (!is_integer($userID) || $userID <= 0)
                continue;

            $user = User::find($userID);

            // That user doesn't exist or it's the same user as the one making the request
            if ($user == false || $user->is($request->user()))

                // The user had already registered for the event
                if ($event->attendees()->where('user_id', $user->id)->exists()) {
                    // Detach the attendee from the event
                    $event->attendees()->detach($user->id);

                    // The attendee gets his tokens back
                    $user->increment('tokens', $event->cost);
                    $user->decrement('tokens_spend', $event->cost);
                }

            SendEventInviteDeletionEmail::dispatch($event->id, $user->id)->delay(now()->addMinutes(value: 10));

            // Remove the invite
            $event->invitedUsers()->detach($user->id);

            $users[] = $user;
        }

        return new UserCollection($users)->additional([
            'message' => 'Invites removed successfully.',
        ])->response()->setStatusCode(200);
    }
}
