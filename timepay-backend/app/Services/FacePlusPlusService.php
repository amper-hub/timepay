<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class FacePlusPlusService
{
    private const DEFAULT_DETECT_URL = 'https://api-us.faceplusplus.com/facepp/v3/detect';

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

    /**
     * Analyze a saved selfie for Face++ quality and liveness/anti-spoofing signals.
     *
     * @return array{suspicious: bool, reason: string|null, metrics: array<string, mixed>, raw: array<string, mixed>}
     */
    public function analyzeSelfieProof(string $selfiePhoto): array
    {
        if (! is_file($selfiePhoto) || ! is_readable($selfiePhoto)) {
            Log::warning('Face++ selfie audit photo is missing or unreadable.', [
                'selfie_photo' => $selfiePhoto,
            ]);

            return $this->auditResult(false, null);
        }

        $apiKey = config('services.faceplusplus.key');
        $apiSecret = config('services.faceplusplus.secret');
        $detectUrl = config('services.faceplusplus.detect_url', self::DEFAULT_DETECT_URL);

        if (! $apiKey || ! $apiSecret) {
            Log::error('Face++ credentials are not configured for selfie audit.');

            return $this->auditResult(false, null);
        }

        $selfieContents = file_get_contents($selfiePhoto);

        if ($selfieContents === false) {
            Log::warning('Face++ could not read selfie audit image file.', [
                'selfie_photo' => $selfiePhoto,
            ]);

            return $this->auditResult(false, null);
        }

        try {
            $response = Http::timeout(20)
                ->retry(2, 250)
                ->withoutVerifying()
                ->attach('image_file', $selfieContents, basename($selfiePhoto))
                ->post($detectUrl, [
                    'api_key' => $apiKey,
                    'api_secret' => $apiSecret,
                    'return_attributes' => config(
                        'services.faceplusplus.audit_return_attributes',
                        'facequality,blur'
                    ),
                ])
                ->throw();

            return $this->evaluateAuditResponse($response->json());
        } catch (ConnectionException|RequestException|RuntimeException $exception) {
            Log::error('Face++ selfie audit request failed.', [
                'message' => $exception->getMessage(),
                'selfie_photo' => $selfiePhoto,
            ]);

            return $this->auditResult(false, null);
        }
    }

    /**
     * Evaluate Face++ response metrics without requiring a second external API.
     */
    private function evaluateAuditResponse(array $payload): array
    {
        $faces = data_get($payload, 'faces', []);

        if (! is_array($faces) || count($faces) === 0) {
            return $this->auditResult(true, 'Face++ did not detect a face in the selfie', [], $payload);
        }

        if (count($faces) > 1) {
            return $this->auditResult(true, 'Face++ detected multiple faces in the selfie', [
                'face_count' => count($faces),
            ], $payload);
        }

        $face = $faces[0];
        $attributes = data_get($face, 'attributes', []);

        $metrics = [
            'facequality_value' => data_get($attributes, 'facequality.value'),
            'facequality_threshold' => data_get($attributes, 'facequality.threshold'),
            'blur_value' => data_get($attributes, 'blur.blurness.value'),
            'blur_threshold' => data_get($attributes, 'blur.blurness.threshold'),
            'lighting_value' => data_get($attributes, 'lighting.value'),
            'lighting_threshold' => data_get($attributes, 'lighting.threshold'),
            'liveness_value' => data_get($attributes, 'liveness.value')
                ?? data_get($attributes, 'liveness.score')
                ?? data_get($attributes, 'antispoofing.value')
                ?? data_get($attributes, 'anti_spoofing.value'),
            'liveness_threshold' => data_get($attributes, 'liveness.threshold')
                ?? data_get($attributes, 'antispoofing.threshold')
                ?? data_get($attributes, 'anti_spoofing.threshold'),
        ];

        $minimumFaceQuality = (float) config('services.faceplusplus.audit_min_facequality', 50);
        $minimumLighting = (float) config('services.faceplusplus.audit_min_lighting', 35);
        $minimumLiveness = (float) config('services.faceplusplus.audit_min_liveness', 0.65);

        $faceQuality = $this->nullableFloat($metrics['facequality_value']);
        $faceQualityThreshold = $this->nullableFloat($metrics['facequality_threshold']);

        if ($faceQuality !== null && $faceQuality < max($minimumFaceQuality, $faceQualityThreshold ?? 0)) {
            return $this->auditResult(true, 'Face++ flagged low quality/potential spoof', $metrics, $payload);
        }

        $blur = $this->nullableFloat($metrics['blur_value']);
        $blurThreshold = $this->nullableFloat($metrics['blur_threshold']);

        if ($blur !== null && $blurThreshold !== null && $blur > $blurThreshold) {
            return $this->auditResult(true, 'Face++ flagged excessive blur/potential spoof', $metrics, $payload);
        }

        $lighting = $this->nullableFloat($metrics['lighting_value']);
        $lightingThreshold = $this->nullableFloat($metrics['lighting_threshold']);

        if ($lighting !== null && $lighting < max($minimumLighting, $lightingThreshold ?? 0)) {
            return $this->auditResult(true, 'Face++ flagged poor lighting/potential spoof', $metrics, $payload);
        }

        $liveness = $this->nullableFloat($metrics['liveness_value']);
        $livenessThreshold = $this->nullableFloat($metrics['liveness_threshold']);

        if ($liveness !== null && $liveness < max($minimumLiveness, $livenessThreshold ?? 0)) {
            return $this->auditResult(true, 'Face++ flagged low liveness/potential spoof', $metrics, $payload);
        }

        return $this->auditResult(false, null, $metrics, $payload);
    }

    /**
     * @param array<string, mixed> $metrics
     * @param array<string, mixed> $raw
     * @return array{suspicious: bool, reason: string|null, metrics: array<string, mixed>, raw: array<string, mixed>}
     */
    private function auditResult(bool $suspicious, ?string $reason, array $metrics = [], array $raw = []): array
    {
        return [
            'suspicious' => $suspicious,
            'reason' => $reason,
            'metrics' => $metrics,
            'raw' => $raw,
        ];
    }

    private function nullableFloat(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}
