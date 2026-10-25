<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceEventResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    /**
     * Update the authenticated employee's display name.
     */
    public function updateName(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $user->update(['name' => $validated['name']]);

        return response()->json([
            'message' => 'Profile name updated successfully.',
            'user' => $user->fresh(),
        ]);
    }

    /**
     * Update the authenticated employee's password.
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        return response()->json([
            'message' => 'Password updated successfully.',
        ]);
    }

    /**
     * Reset facial recognition baseline so the next punch re-enrolls the employee.
     */
    public function resetFace(Request $request): JsonResponse
    {
        $user = $request->user();
        $baselinePhotoPath = $user->baseline_photo_path;

        if ($baselinePhotoPath) {
            $normalizedPath = str($baselinePhotoPath)
                ->replace('\\', '/')
                ->replaceStart('storage/', '')
                ->toString();

            if (! str_contains($normalizedPath, '..')) {
                foreach (['private', 'public'] as $diskName) {
                    $disk = Storage::disk($diskName);
                    if ($disk->exists($normalizedPath)) {
                        $disk->delete($normalizedPath);
                    }
                }
            }
        }

        $user->update([
            'baseline_photo_path' => null,
            'cloud_face_id' => null,
        ]);

        // An enrollment response is not an attendance punch. If the client lost
        // that response, it still holds the event ID and will retry it. Remove
        // only completed enrollment responses so a post-reset retry reaches
        // fresh liveness and baseline enrollment instead of replaying stale state.
        AttendanceEventResponse::query()
            ->where('user_id', $user->id)
            ->whereJsonContains('response->face_verification->enrolled', true)
            ->delete();

        return response()->json([
            'message' => 'Facial recognition baseline reset. Your next attendance punch will re-enroll your face.',
        ]);
    }
}
