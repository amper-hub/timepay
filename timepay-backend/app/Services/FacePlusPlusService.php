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

            $baselineImage = $this->prepareFacePlusPlusImage($baselinePhoto);

            $selfieImage = $this->prepareFacePlusPlusImage($selfiePhoto);

            $baselineContents = $baselineImage['contents'];

            $selfieContents = $selfieImage['contents'];

            $response = Http::withOptions([

                'verify' => $this->sslVerifyOption(),

            ])->timeout(20)

                ->retry(2, 250)

                ->attach('image_file1', $baselineContents, $baselineImage['filename'], ['Content-Type' => 'image/jpeg'])

                ->attach('image_file2', $selfieContents, $selfieImage['filename'], ['Content-Type' => 'image/jpeg'])

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



        $selfieImage = $this->prepareFacePlusPlusImage($selfiePhoto);

        $selfieContents = $selfieImage['contents'];



        try {

            $response = Http::withOptions([

                'verify' => $this->sslVerifyOption(),

            ])->timeout(20)

                ->retry(2, 250)

                ->attach('image_file', $selfieContents, $selfieImage['filename'], ['Content-Type' => 'image/jpeg'])

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

            $preparedImage = $this->prepareFacePlusPlusImage($imagePath);

            $imageContents = $preparedImage['contents'];



            $response = Http::withOptions([

                'verify' => $this->sslVerifyOption(),

            ])->timeout(15)

                ->attach('image_file', $imageContents, $preparedImage['filename'], ['Content-Type' => 'image/jpeg'])

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
     * Normalize uploaded images before sending them to Face++.
     *
     * Face++ can reject large camera images with INVALID_IMAGE_SIZE. Re-encode
     * valid images as JPEG and constrain the longest edge to 1600px. If GD is
     * unavailable, accept only already-small images that are valid image data.
     *
     * @return array{contents: string, filename: string}
     */
    private function prepareFacePlusPlusImage(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('Face++ could not read a verification image.');
        }

        $contents = file_get_contents($path);

        if ($contents === false || $contents === '') {
            throw new RuntimeException('Face++ could not read a verification image.');
        }

        $imageInfo = @getimagesizefromstring($contents);

        if ($imageInfo === false) {
            throw new RuntimeException('The verification upload is not a supported image.');
        }

        $maxDimension = 1600;
        $width = (int) $imageInfo[0];
        $height = (int) $imageInfo[1];

        if (function_exists('imagecreatefromstring') && function_exists('imagejpeg')) {
            $source = @imagecreatefromstring($contents);

            if ($source === false) {
                throw new RuntimeException('The verification image could not be decoded.');
            }

            try {
                $scale = min(1, $maxDimension / max($width, $height));
                $targetWidth = max(1, (int) round($width * $scale));
                $targetHeight = max(1, (int) round($height * $scale));
                $target = imagecreatetruecolor($targetWidth, $targetHeight);

                if ($target === false) {
                    throw new RuntimeException('The verification image could not be prepared.');
                }

                try {
                    $white = imagecolorallocate($target, 255, 255, 255);
                    imagefill($target, 0, 0, $white);

                    if (! imagecopyresampled(
                        $target,
                        $source,
                        0,
                        0,
                        0,
                        0,
                        $targetWidth,
                        $targetHeight,
                        $width,
                        $height
                    )) {
                        throw new RuntimeException('The verification image could not be resized.');
                    }

                    ob_start();
                    $written = imagejpeg($target, null, 82);
                    $jpeg = ob_get_clean();

                    if (! $written || ! is_string($jpeg) || $jpeg === '') {
                        throw new RuntimeException('The verification image could not be encoded as JPEG.');
                    }

                    return [
                        'contents' => $jpeg,
                        'filename' => pathinfo($path, PATHINFO_FILENAME) . '.jpg',
                    ];
                } finally {
                    imagedestroy($target);
                }
            } finally {
                imagedestroy($source);
            }
        }

        // Without GD, avoid sending images known to exceed conservative limits.
        if (max($width, $height) > $maxDimension || strlen($contents) > 2_000_000) {
            throw new RuntimeException(
                'The verification image is too large for Face++. Please capture a smaller image or enable PHP GD.'
            );
        }

        return [
            'contents' => $contents,
            'filename' => basename($path),
        ];
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
