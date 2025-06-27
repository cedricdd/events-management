<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Requests\AuthRequest;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Hash;
use Knuckles\Scribe\Attributes\Response;
use Illuminate\Validation\ValidationException;

/**
 * @group Authentication
 * 
 * Handles user authentication, including login and logout.
 */
class AuthController extends Controller
{
    /**
     * Login
     * 
     * Handles user authentication by validating credentials and generating an access token.
     * 
     * @bodyParam email string required The user's email address. Example: "string@email.com"
     * @bodyParam password string required The user's password. Example: "password123"
     */
    #[Response('{"token": "Your Token.", "user": {
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
    #[Response('{"message": "The provided credentials are incorrect."}', 422)]
    public function login(AuthRequest $request)
    {
        $user = User::where('email', $request->input('email'))->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'email' => 'The provided credentials are incorrect.',
            ]);
        }

        if (!Hash::check($request->input('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'The provided credentials are incorrect.', //Don't tell the user if the email or password is incorrect
            ]);
        }

        $token = $user->createToken(name: 'auth_token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user, true),
        ]);
    }
    /**
     * Logout
     * 
     * Logs out the authenticated user by deleting their current access token.
     * 
     * @authenticated
     */
    #[Response('', 204)]
    #[Response('{"message": "Unauthenticated."}', 401)]
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }
}
