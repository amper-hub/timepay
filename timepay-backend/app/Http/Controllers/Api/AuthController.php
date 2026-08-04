<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
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

        if ($user->requires_password_change) {
            Auth::logout();

            return response()->json([
                'error' => 'password_change_required',
                'message' => 'You must change your temporary password to continue.',
                'user_id' => $user->id,
            ], 403);
        }

        $user->load('company');

        $token = $user->createToken('mobile-app', ['role:'.$user->role])->plainTextToken;

        return response()->json([
            'token' => $token,
            'role' => $user->role,
            'user' => [
                'id' => $user->id,
                'company_id' => $user->company_id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'baseline_photo_path' => $user->baseline_photo_path,
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
     * Replace a temporary password and issue a fresh API token.
     */
    public function updateTemporaryPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'current_password' => 'required|string',
            'new_password' => ['required', 'string', 'confirmed', Password::defaults()],
        ]);

        $user = User::findOrFail($validated['user_id']);

        if (! $user->requires_password_change) {
            throw ValidationException::withMessages([
                'user_id' => ['This account does not require a password change.'],
            ]);
        }

        if (! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The provided password is incorrect.'],
            ]);
        }

        $user->password = $validated['new_password'];
        $user->requires_password_change = false;
        $user->save();

        $user->load('company');

        $token = $user->createToken('mobile-app', ['role:'.$user->role])->plainTextToken;

        return response()->json([
            'token' => $token,
            'role' => $user->role,
            'user' => [
                'id' => $user->id,
                'company_id' => $user->company_id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'baseline_photo_path' => $user->baseline_photo_path,
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
