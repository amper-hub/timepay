<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Handle user login and issue an API token.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if (! Auth::attempt($validated)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials do not match our records.'],
            ]);
        }

        $user = Auth::user();
        $user->load('company');

        $token = $user->createToken('mobile-app')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'company_id' => $user->company_id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'hourly_rate' => $user->hourly_rate,
                'daily_rate' => $user->daily_rate,
            ],
            'company' => [
                'id' => $user->company?->id,
                'name' => $user->company?->name,
                'latitude' => $user->company?->latitude,
                'longitude' => $user->company?->longitude,
                'geofence_radius_meters' => $user->company?->geofence_radius_meters,
                'pay_metric' => $user->company?->pay_metric,
            ],
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
