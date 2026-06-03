<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Handle user login and issue an API token.
     *
     * @param Request $request
     * @return JsonResponse
     * @throws ValidationException
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are invalid.'],
            ]);
        }

        // Load the company relationship to include it in the response
        $user->load('company');

        // Create a Sanctum token for the mobile app
        $token = $user->createToken('mobile-auth-token')->plainTextToken;

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'hourly_rate' => $user->hourly_rate,
                'daily_rate' => $user->daily_rate,
            ],
            'company' => [
                'id' => $user->company->id,
                'name' => $user->company->name,
                'latitude' => $user->company->latitude,
                'longitude' => $user->company->longitude,
                'geofence_radius_meters' => $user->company->geofence_radius_meters,
                'pay_metric' => $user->company->pay_metric,
            ],
            'token' => $token,
        ], 200);
    }

    /**
     * Handle user logout by revoking all API tokens.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function logout(Request $request): JsonResponse
    {
        // Revoke the current token being used
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Successfully logged out.',
        ], 200);
    }
}
