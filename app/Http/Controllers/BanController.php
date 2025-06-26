<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Requests\BanRequest;
use Illuminate\Http\JsonResponse;
use App\Http\Resources\UserResource;
use App\Http\Resources\UserCollection;

class BanController extends Controller
{
    public function index(Request $request, User|null $user = null): JsonResponse
    {
        if ($user !== null) {
            // If a user is specified, check if the authenticated user is allowed to view their banned list
            if ($request->user()->id !== $user->id && !$request->user()->isAdmin()) {
                return response()->json([
                    'message' => "You are not authorized to view this user's banned list.",
                ], 403);
            }

            $bannedUsers = $user->bannedUsers()->orderBy('name', 'asc')->get();
        } else
            $bannedUsers = $request->user()->bannedUsers()->orderBy('name', 'asc')->get();

        return response()->json(new UserCollection($bannedUsers));
    }

    public function store(BanRequest $request): JsonResponse
    {
        $users = [];

        foreach ($request->users as $userID) {
            if (!is_integer($userID) || $userID <= 0)
                continue;

            $user = User::find($userID);

            // That user doesn't exist or it's the same user as the one making the request
            if ($user == false || $user->is($request->user()))
                continue;

            // Check if the user is already banned
            if (!$request->user()->bannedUsers()->where('attendee_id', $userID)->exists()) {
                $request->user()->bannedUsers()->attach($userID);
            }

            $users[] = new UserResource($user);
        }

        return response()->json([
            'message' => 'Bans added successfully.',
            'users' => $users,
        ], 201);
    }

    public function destroy(BanRequest $request): JsonResponse
    {
        $usersUnBanned = [];

        foreach ($request->input('users') as $userID) {
            if (!is_integer($userID) || $userID <= 0) continue;

            $user = User::find($userID);

            // That user doesn't exist or it's the same user as the one making the request
            if ($user == false || $user->is($request->user()))

            // Check if the user is banned
            if ($request->user()->bannedUsers()->where('attendee_id', $userID)->exists()) {
                $request->user()->bannedUsers()->detach($userID);
            }

            $usersUnBanned[] = new UserResource($user);
        }

        return response()->json([
            'message' => 'Bans removed successfully.',
            'users' => $usersUnBanned,
        ]);
    }
}
