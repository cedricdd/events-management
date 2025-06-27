<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Resources\UserResource;
use Knuckles\Scribe\Attributes\Response;

/**
 * @group User Management
 * 
 * Handles user-related operations, including viewing user profiles.
 * 
 * @authenticated
 */
class UserController extends Controller
{
    /**
     * Show User Information
     * 
     * Displays the profile of a user. If no user is specified, it shows the authenticated user's profile.<br/>
     * (Only admins can see other users' profiles.)
     * 
     * @urlParam user int The ID of the user whose profile to retrieve. If not specified, retrieves the authenticated user's profile.
     */
    #[Response('{"message": "Unauthenticated."}', 401)]
        #[Response('{"data": {
        "id": 1,
        "name": "John Doe",
        "email": "john@doe.com",
        "country": "USA",
        "profession": "Programmer",
        "phone": "123-456-789",
        "organization": "World Incorporated",
        "tokens": 100,
        "tokens_spend": 100
    }}', 200)]
    public function show(Request $request, ?User $user = null): UserResource
    {
        // If no user is provided, use the authenticated user
        return new UserResource(
            $user ?? $request->user(), 
            $request->user()->isAdmin() || $request->user()->is($user) || $user === null // Show extra information only to admins or the user themselves
        );
    }
}
