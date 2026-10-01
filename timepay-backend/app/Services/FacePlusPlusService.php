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
    private const DEFAULT_COMPARE_URL = 'https://api-us.faceplusplus.com/facepp/v3/compare';
    private const MINIMUM_CONFIDENCE = 80.0;

    /**
     * Compare a user's baseline photo with a newly captured selfie.
     *
     * @return array{confidence: float, threshold: float, matched: bool}
     */
    public function compare(string $baselinePhoto, string $selfiePhoto): array
    {
        if (! is_file($baselinePhoto) || ! is_readable($baselinePhoto)) {
            throw new RuntimeException('Face++ reference photo is missing or unreadable.');
        }

        if (! is_file($selfiePhoto) || ! is_readable($selfiePhoto)) {
            throw new RuntimeException('Face++ selfie photo is missing or unreadable.');
        }
        $apiKey = config('services.faceplusplus.key');
        $apiSecret = config('services.faceplusplus.secret');
        $compareUrl = config('services.faceplusplus.compare_url', self::DEFAULT_COMPARE_URL);
        $threshold = self::MINIMUM_CONFIDENCE;

        if (! $apiKey || ! $apiSecret) {
            Log::error('Face++ credentials are not configured.');

            throw new RuntimeException('Face++ comparison service is not configured.');
        }

        try {
            $baselineContents = file_get_contents($baselinePhoto);
            $selfieContents = file_get_contents($selfiePhoto);

            if ($baselineContents === false || $selfieContents === false) {
                throw new RuntimeException('Face++ could not read the reference or selfie image.');
            }
            $response = Http::withOptions([
                'verify' => $this->sslVerifyOption(),
            ])->timeout(20)
                ->retry(2, 250)
                ->attach('image_file1', $baselineContents, basename($baselinePhoto))
                ->attach('image_file2', $selfieContents, basename($selfiePhoto))
                ->post($compareUrl, [
                    'api_key' => $apiKey,
                    'api_secret' => $apiSecret,
                ])
                ->throw();

            $confidenceValue = data_get($response->json(), 'confidence');

            if (! is_numeric($confidenceValue)) {
                throw new RuntimeException('Face++ returned no comparison confidence score.');
            }

            $confidence = (float) $confidenceValue;

            return [
                'confidence' => $confidence,
                'threshold' => $threshold,
                'matched' => $confidence >= $threshold,
            ];
        } catch (ConnectionException|RequestException|RuntimeException $exception) {
            Log::error('Face++ comparison request failed.', [
                'message' => $exception->getMessage(),
                'baseline_photo' => $baselinePhoto,
                'selfie_photo' => $selfiePhoto,
            ]);

            throw new RuntimeException('Face++ comparison is temporarily unavailable.', previous: $exception);
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
            $response = Http::withOptions([
                'verify' => $this->sslVerifyOption(),
            ])->timeout(20)
                ->retry(2, 250)
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

    /**
     * Use the bundled CA certificates when available. Only local development
     * may disable verification, and only when no readable bundle is installed.
     */
    private function sslVerifyOption(): string|bool
    {
        $caBundle = storage_path('app/cacert.pem');

        if (is_file($caBundle) && is_readable($caBundle)) {
            return $caBundle;
        }

        return app()->environment('local') ? false : true;
    }
}
