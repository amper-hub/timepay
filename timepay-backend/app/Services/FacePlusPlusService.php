<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class FacePlusPlusService
{
    /**
     * Compare a user's baseline photo with a newly captured selfie.
     */
    public function compare(string $baselinePhoto, string $selfiePhoto): bool
    {
        if (! is_file($baselinePhoto) || ! is_readable($baselinePhoto)) {
            Log::warning('Face++ baseline photo is missing or unreadable.', [
                'baseline_photo' => $baselinePhoto,
            ]);

            return false;
        }

        if (! is_file($selfiePhoto) || ! is_readable($selfiePhoto)) {
            Log::warning('Face++ selfie photo is missing or unreadable.', [
                'selfie_photo' => $selfiePhoto,
            ]);

            return false;
        }

        // 💡 Read directly from your .env file keys
        $apiKey = env('FACEPP_API_KEY');
        $apiSecret = env('FACEPP_API_SECRET');
        $compareUrl = 'https://api-us.faceplusplus.com/facepp/v3/compare'; 
        $threshold = (float) env('FACEPP_CONFIDENCE_THRESHOLD', 80);

        if (! $apiKey || ! $apiSecret) {
            Log::error('Face++ credentials are not configured.');

            return false;
        }

        try {
            $baselineContents = file_get_contents($baselinePhoto);
            $selfieContents = file_get_contents($selfiePhoto);

            if ($baselineContents === false || $selfieContents === false) {
                Log::warning('Face++ could not read one or both image files.', [
                    'baseline_photo' => $baselinePhoto,
                    'selfie_photo' => $selfiePhoto,
                ]);

                return false;
            }

            // The HTTP request sequence with the Windows SSL fix applied
            $response = Http::timeout(20)
                ->retry(2, 250)
                ->withoutVerifying() // 👈 Added to resolve cURL error 60 on local environment
                ->attach('image_file1', $baselineContents, basename($baselinePhoto))
                ->attach('image_file2', $selfieContents, basename($selfiePhoto))
                ->post($compareUrl, [
                    'api_key' => $apiKey,
                    'api_secret' => $apiSecret,
                ])
                ->throw();

            $confidence = (float) data_get($response->json(), 'confidence', 0);

            return $confidence >= $threshold;
        } catch (ConnectionException|RequestException|RuntimeException $exception) {
            Log::error('Face++ comparison request failed.', [
                'message' => $exception->getMessage(),
                'baseline_photo' => $baselinePhoto,
                'selfie_photo' => $selfiePhoto,
            ]);

            return false;
        }
    }
}