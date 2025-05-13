<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Requests\AuthRequest;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(AuthRequest $request) {
        $user = User::where('email', $request->input('email'))->first();

        if(!$user) {
            throw ValidationException::withMessages([
                'email' => 'The provided credentials are incorrect.',
            ]);
        }

        if(!Hash::check($request->input('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'The provided credentials are incorrect.', //Don't tell the user if the email or password is incorrect
            ]);
        }

        $token = $user->createToken(name: 'auth_token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user),
        ]);
    }

    public function logout(Request $request) {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }
}
