<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\Event;
use Illuminate\Http\Request;
use App\Http\Requests\InviteRequest;
use App\Models\Invite;

class InviteController extends Controller
{
    public function store(InviteRequest $request)
    {
        $event = Event::with('organizer')->findOrFail($request->input('event_id'));

        // First make sure the user is the organizer of the event
        if ($request->user()->is($event->organizer) == false) {
            return response()->json(['message' => 'You are not authorized to invite users to this event.'], 403);
        }

        // Make sure the event is not in the past
        if ($event->start_date < now()) {
            return response()->json(['message' => 'You cannot invite users to an event that has already started.'], 403);
        }

        // Make sure the event is private
        if ($event->is_public) {
            return response()->json(['message' => 'You can only invite users to private events.'], 403);
        }

        // Create invites for each user
        $invites = [];
        foreach ($request->input('users') as $userId) {
            $userInvited = User::find($userId);

            // That user doesn't exist or it's the organizer of the event
            if ($userInvited == false || $userInvited->is($request->user())) continue;

            // Make sure the user is not already invited to the event
            if (!Invite::where('user_id', $userId)->where('event_id', $event->id)->exists()) {
                $event->invites()->attach($userInvited->id);
            }

            $invites[] = new UserResource($userInvited);
        }

        return response()->json([
            'message' => 'Invites created successfully.',
            'invites' => $invites,
        ], 201);
    }
}
