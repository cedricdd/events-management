<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Event;
use App\Models\Invite;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\InviteRequest;
use App\Http\Resources\UserResource;
use App\Http\Resources\UserCollection;
use App\Jobs\SendEventInviteDeletionEmail;
use App\Jobs\SendEventInviteEmail;
use App\Notifications\EventInviteDeletionNotification;
use App\Notifications\EventUnRegistrationNotification;

class InviteController extends Controller
{
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

        return response()->json([
            'message' => 'Invites created successfully.',
            'invites' => $invites,
        ], 201);
    }

    public function destroy(Request $request, Event $event, User $attendee): Response|JsonResponse
    {
        // The user had already registered for the event
        if ($event->attendees()->where('user_id', $attendee->id)->exists()) {
            // Detach the attendee from the event
            $event->attendees()->detach($attendee->id);

            $attendee->notify(new EventUnRegistrationNotification($event, 'organizer'));

            // The attendee gets his tokens back
            $attendee->increment('tokens', $event->cost);
            $attendee->decrement('tokens_spend', $event->cost);
        } else SendEventInviteDeletionEmail::dispatch($event->id, $attendee->id)->delay(now()->addMinutes(value: 10));

        // Remove the invite
        $event->invitedUsers()->detach($attendee->id);

        return response()->noContent();
    }
}
