<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Resources\UserResource;

class UserController extends Controller
{
    public function show(Request $request, ?User $user = null): UserResource
    {
        // If no user is provided, use the authenticated user
        return new UserResource(
            $user ?? $request->user(), 
            $request->user()->isAdmin() || $request->user()->is($user) || $user === null // Show extra information only to admins or the user themselves
        );
    }
}
