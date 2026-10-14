<?php



namespace App\Services;



use Illuminate\Http\Client\ConnectionException;

use Illuminate\Http\Client\RequestException;

use Illuminate\Support\Facades\Http;

use Illuminate\Support\Facades\Log;

use RuntimeException;



class FacePlusPlusService

{

    private const DEFAULT_DETECT_URL = 'https\://api-us.faceplusplus.com/facepp/v3/detect';

    private const DEFAULT_COMPARE_URL = 'https\://api-us.faceplusplus.com/facepp/v3/compare';

    private const DEFAULT_CONFIDENCE_THRESHOLD = 80.0;



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

        $threshold = (float) config(

            'services.faceplusplus.confidence_threshold',

            self::DEFAULT_CONFIDENCE_THRESHOLD

        );



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



            $payload = $response->json();

            $this->throwIfFacePlusPlusError($payload);

            $confidenceValue = is_array($payload) ? data_get($payload, 'confidence') : null;



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

            $this->logFacePlusPlusFailure('Face++ comparison request failed.', $exception);



            throw new RuntimeException('Face++ comparison is temporarily unavailable.', previous: $exception);

        }

    }



    /**

     * Verify that the uploaded samples show one face opening, closing, and

     * reopening both eyes. The mobile ML Kit result is never accepted as proof.

     */

    public function verifyBlinkSequence(string $openPhoto, string $closedPhoto, string $reopenedPhoto): bool

    {

        $minimumEyeConfidence = (float) config(

            'services.faceplusplus.eye_confidence_threshold',

            70

        );



        foreach ([

            ['path' => $openPhoto, 'state' => 'open'],

            ['path' => $closedPhoto, 'state' => 'closed'],

            ['path' => $reopenedPhoto, 'state' => 'open'],

        ] as $sample) {

            $payload = $this->detectImage($sample['path'], 'eyestatus');

            $faces = data_get($payload, 'faces');



            if (! is_array($faces) || count($faces) !== 1) {

                return false;

            }



            $eyeStatus = data_get($faces, '0.attributes.eyestatus');



            if (! is_array($eyeStatus)) {

                return false;

            }



            foreach (['left_eye_status', 'right_eye_status'] as $eye) {

                $eyeMetrics = data_get($eyeStatus, $eye);

                if (! is_array($eyeMetrics)) {

                    return false;

                }



                $openConfidence = max(

                    $this->nullableFloat($eyeMetrics['no_glass_eye_open'] ?? null) ?? 0,

                    $this->nullableFloat($eyeMetrics['normal_glass_eye_open'] ?? null) ?? 0

                );

                $closedConfidence = max(

                    $this->nullableFloat($eyeMetrics['no_glass_eye_close'] ?? null) ?? 0,

                    $this->nullableFloat($eyeMetrics['normal_glass_eye_close'] ?? null) ?? 0

                );



                $stateVerified = $sample['state'] === 'open'

                    ? $openConfidence >= $minimumEyeConfidence && $openConfidence > $closedConfidence

                    : $closedConfidence >= $minimumEyeConfidence && $closedConfidence > $openConfidence;



                if (! $stateVerified) {

                    return false;

                }

            }

        }



        return true;

    }



    /**

     * Analyze a saved selfie for Face++ face-count, face-quality, and blur signals.

     *

     * @return array{suspicious: bool, reason: string|null, metrics: array<string, mixed>, raw: array<string, mixed>}

     */

    public function analyzeSelfieProof(string $selfiePhoto): array

    {

        if (! is_file($selfiePhoto) || ! is_readable($selfiePhoto)) {

            Log::warning('Face++ selfie audit photo is missing or unreadable.');



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

            Log::warning('Face++ could not read selfie audit image file.');



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



            $payload = $response->json();
            $this->throwIfFacePlusPlusError($payload);

            if (! is_array($payload)) {
                throw new RuntimeException('Face++ returned an invalid selfie audit response.');
            }

            return $this->evaluateAuditResponse($payload);

        } catch (ConnectionException|RequestException|RuntimeException $exception) {

            $this->logFacePlusPlusFailure('Face++ selfie audit request failed.', $exception);



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

        ];



        $minimumFaceQuality = (float) config('services.faceplusplus.audit_min_facequality', 50);



        $faceQuality = $this->nullableFloat($metrics['facequality_value']);

        $faceQualityThreshold = $this->nullableFloat($metrics['facequality_threshold']);



        if ($faceQuality !== null && $faceQuality < max($minimumFaceQuality, $faceQualityThreshold ?? 0)) {

            return $this->auditResult(true, 'Face++ flagged low face quality', $metrics, $payload);

        }



        $blur = $this->nullableFloat($metrics['blur_value']);

        $blurThreshold = $this->nullableFloat($metrics['blur_threshold']);



        if ($blur !== null && $blurThreshold !== null && $blur > $blurThreshold) {

            return $this->auditResult(true, 'Face++ flagged excessive blur', $metrics, $payload);

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



    /** @return array<string, mixed> */

    private function detectImage(string $imagePath, string $returnAttributes): array

    {

        $apiKey = config('services.faceplusplus.key');

        $apiSecret = config('services.faceplusplus.secret');

        $detectUrl = config('services.faceplusplus.detect_url', self::DEFAULT_DETECT_URL);



        if (! $apiKey || ! $apiSecret) {

            Log::error('Face++ credentials are not configured.');

            throw new RuntimeException('Face++ detection service is not configured.');

        }



        if (! is_file($imagePath) || ! is_readable($imagePath)) {

            throw new RuntimeException('Face++ could not read a verification image.');

        }



        try {

            $imageContents = file_get_contents($imagePath);

            if ($imageContents === false) {

                throw new RuntimeException('Face++ could not read a verification image.');

            }



            $response = Http::withOptions([

                'verify' => $this->sslVerifyOption(),

            ])->timeout(15)

                ->attach('image_file', $imageContents, basename($imagePath))

                ->post($detectUrl, [

                    'api_key' => $apiKey,

                    'api_secret' => $apiSecret,

                    'return_attributes' => $returnAttributes,

                ])

                ->throw();



            $payload = $response->json();

            $this->throwIfFacePlusPlusError($payload);

            if (! is_array($payload)) {

                throw new RuntimeException('Face++ returned an invalid detection response.');

            }



            return $payload;

        } catch (ConnectionException|RequestException|RuntimeException $exception) {

            $this->logFacePlusPlusFailure('Face++ detection request failed.', $exception);



            throw new RuntimeException('Face++ detection is temporarily unavailable.', previous: $exception);

        }

    }



    /**

     * Use the bundled CA certificates when available. Only local development

     * may disable verification, and only when no readable bundle is installed.

     */

    /** Log sanitized Face++ failures without logging credentials or image data. */
    private function logFacePlusPlusFailure(string $message, \Throwable $exception): void
    {
        $context = [
            'exception_type' => $exception::class,
            'error_message' => mb_substr($exception->getMessage(), 0, 300),
        ];

        if ($exception instanceof RequestException) {
            $response = $exception->response;
            $context['http_status'] = $response->status();
            $payload = $response->json();

            if (is_array($payload)) {
                foreach (['error_message', 'error_code'] as $field) {
                    if (isset($payload[$field]) && is_scalar($payload[$field])) {
                        $context['faceplusplus_' . $field] = mb_substr((string) $payload[$field], 0, 300);
                    }
                }
            }
        }

        Log::error($message, $context);
    }

    /** Turn Face++ JSON error payloads into useful sanitized exceptions. */
    private function throwIfFacePlusPlusError(mixed $payload): void
    {
        if (is_array($payload) && isset($payload['error_message'])) {
            $message = is_scalar($payload['error_message'])
                ? mb_substr((string) $payload['error_message'], 0, 300)
                : 'Unknown Face++ API error';
            $code = isset($payload['error_code']) && is_scalar($payload['error_code'])
                ? (string) $payload['error_code']
                : null;

            throw new RuntimeException('Face++ API error' . ($code ? ' (' . $code . ')' : '') . ': ' . $message);
        }
    }

    private function sslVerifyOption(): string|bool

    {

        $caBundle = storage_path('app/cacert.pem');



        if (is_file($caBundle) && is_readable($caBundle)) {

            return $caBundle;

        }



        return app()->environment('local') ? false : true;

    }

}
