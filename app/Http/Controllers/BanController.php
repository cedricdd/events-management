<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Http\Requests\BanRequest;
use Illuminate\Http\JsonResponse;
use App\Http\Resources\UserResource;

class BanController extends Controller
{
    public function store(BanRequest $request): JsonResponse
    {
        $users = [];

        foreach ($request->users as $userId) {
            if (!is_integer($userId) || $userId <= 0)
                continue;

            $user = User::find($userId);

            // That user doesn't exist or it's the same user as the one making the request
            if ($user == false || $user->is($request->user()))
                continue;

            // Check if the user is already banned
            if (!$request->user()->bannedUsers()->where('attendee_id', $userId)->exists()) {
                $request->user()->bannedUsers()->attach($userId);
            }

            $users[] = new UserResource($user);
        }

        return response()->json([
            'message' => 'Bans added successfully.',
            'users' => $users,
        ], 201);
    }
}
