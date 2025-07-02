<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Requests\BanRequest;
use Illuminate\Http\JsonResponse;
use App\Http\Resources\UserResource;
use App\Http\Resources\UserCollection;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;

/**
 * @group Bans
 * @authenticated
 */
class BanController extends Controller
{
    /**
     * List User Banned
     * 
     * List all the users banned by the authenticated user or a specified user. 
     * * Only administrators can view other users' banned lists.
     * 
     * @urlParam user int The ID of the user whose banned list to retrieve. If not specified, retrieves the authenticated user's banned list.
     */
    #[Response('{"message": "Unauthenticated."}', 401, )]
    #[Response('{"message": "You are not authorized to view this user\'s banned list."}', 403, )]
    #[ResponseFromApiResource(UserCollection::class, User::class, 200)]
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

    /**
     * Add Bans
     * 
     * Add users to the authenticated user's banned list.
     */
    #[Response('{"message": "Unauthenticated."}', 401, )]
    #[ResponseFromApiResource(UserCollection::class, User::class, 201, additional: ['message' => 'Bans added successfully.'])]
    public function store(BanRequest $request)
    {
        $usersBanned = [];

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

            $usersBanned[] = $user;
        }

        return new UserCollection($usersBanned)->additional([
            'message' => 'Bans added successfully.',
        ])->response()->setStatusCode(201);
    }

    /**
     * Remove Bans
     * 
     * Remove users from the authenticated user's banned list.
     */
    #[Response('{"message": "Unauthenticated."}', 401, )]
    #[ResponseFromApiResource(UserCollection::class, User::class, 200, additional: ['message' => 'Bans removed successfully.'])]
    public function destroy(BanRequest $request): JsonResponse
    {
        $usersUnBanned = [];

        foreach ($request->input('users') as $userID) {
            if (!is_integer($userID) || $userID <= 0)
                continue;

            $user = User::find($userID);

            // That user doesn't exist or it's the same user as the one making the request
            if ($user == false || $user->is($request->user()))
                continue;

            // Check if the user is banned
            if ($request->user()->bannedUsers()->where('attendee_id', $userID)->exists()) {
                $request->user()->bannedUsers()->detach($userID);
            }

            $usersUnBanned[] = $user;
        }

        return new UserCollection($usersUnBanned)->additional([
            'message' => 'Bans removed successfully.',
        ])->response();
    }
}
