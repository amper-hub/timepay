<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class StorageHealthController extends Controller
{
    /**
     * Confirm that the private disk can write, read, and remove a temporary probe.
     */
    public function __invoke(): JsonResponse
    {
        $probePath = '.health-checks/'.Str::uuid().'.tmp';
        $disk = null;

        try {
            $disk = Storage::disk('private');
            $expectedContents = 'timepay-storage-health-check';
            $written = $disk->put($probePath, $expectedContents);
            $readable = $written && $disk->get($probePath) === $expectedContents;
            $removed = $disk->delete($probePath);

            if (! $written || ! $readable || ! $removed) {
                throw new \RuntimeException('Private storage health check failed.');
            }

            return response()->json(['status' => 'ok']);
        } catch (Throwable) {
            if ($disk !== null) {
                try {
                    $disk->delete($probePath);
                } catch (Throwable) {
                    // Keep storage paths and adapter errors out of the response.
                }
            }

            return response()->json(['status' => 'unavailable'], 503);
        }
    }
}
