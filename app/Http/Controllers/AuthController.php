<?php

namespace App\Http\Controllers;

use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    // Create a new user account and return a first bearer token.
    public function register(Request $request): JsonResponse
    {
        $data = $this->validate($request, [
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|max:200',
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'password' => Hash::make($data['password']),
        ]);

        return response()->json([
            'token' => ApiToken::issue($user),
            'user' => $user,
        ], 201);
    }

    // Verify email + password and issue a new bearer token.
    public function login(Request $request): JsonResponse
    {
        $data = $this->validate($request, [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', strtolower($data['email']))->first();

        // Same error for unknown email and wrong password so the endpoint
        // does not reveal which addresses are registered.
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        return response()->json([
            'token' => ApiToken::issue($user),
            'user' => $user,
        ]);
    }

    // Revoke only the bearer token used for this request.
    public function logout(Request $request): JsonResponse
    {
        $token = $request->attributes->get('api_token');

        if ($token instanceof ApiToken) {
            $token->delete();
        }

        return response()->json(['message' => 'Logged out.']);
    }

    // Return the currently authenticated user.
    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }
}
