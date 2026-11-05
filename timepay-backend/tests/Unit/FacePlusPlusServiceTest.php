<?php

namespace Tests\Unit;

use App\Services\FacePlusPlusService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class FacePlusPlusServiceTest extends TestCase
{
    /** @var list<string> */
    private array $imagePaths = [];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.faceplusplus.key' => 'test-key',
            'services.faceplusplus.secret' => 'test-secret',
            'services.faceplusplus.detect_url' => 'https://face.test/detect',
            'services.faceplusplus.compare_url' => 'https://face.test/compare',
            'services.faceplusplus.confidence_threshold' => 90,
            'services.faceplusplus.eye_confidence_threshold' => 70,
        ]);
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        foreach ($this->imagePaths as $imagePath) {
            if (is_file($imagePath)) {
                unlink($imagePath);
            }
        }

        parent::tearDown();
    }

    public function test_server_verifies_neutral_and_opposite_head_turns_with_face_plus_plus(): void
    {
        Http::fakeSequence()
            ->push($this->headTurnDetectionResponse(0))
            ->push($this->headTurnDetectionResponse(26))
            ->push($this->headTurnDetectionResponse(-24));

        $passed = app(FacePlusPlusService::class)->verifyHeadTurnSequence(
            $this->image('neutral.jpg'),
            $this->image('left.jpg'),
            $this->image('right.jpg')
        );

        $this->assertTrue($passed);
        Http::assertSentCount(3);

        Http::assertSent(
            fn (Request $request): bool =>
                $request->url() === 'https://face.test/detect'
                && str_contains($request->body(), 'return_attributes')
                && str_contains($request->body(), 'headpose')
        );
    }

    public function test_server_rejects_missing_opposite_turns(): void
    {
        Http::fakeSequence()
            ->push($this->headTurnDetectionResponse(0))
            ->push($this->headTurnDetectionResponse(25))
            ->push($this->headTurnDetectionResponse(10));

        $passed = app(FacePlusPlusService::class)->verifyHeadTurnSequence(
            $this->image('neutral.jpg'),
            $this->image('turn.jpg'),
            $this->image('not-turned.jpg')
        );

        $this->assertFalse($passed);
        Http::assertSentCount(3);
    }

    public function test_server_measures_turns_relative_to_the_neutral_baseline(): void
    {
        Http::fakeSequence()
            ->push($this->headTurnDetectionResponse(10))
            ->push($this->headTurnDetectionResponse(22))
            ->push($this->headTurnDetectionResponse(-10));

        $passed = app(FacePlusPlusService::class)->verifyHeadTurnSequence(
            $this->image('neutral.jpg'),
            $this->image('small-turn.jpg'),
            $this->image('return.jpg')
        );

        $this->assertFalse($passed);
        Http::assertSentCount(3);
    }

    public function test_server_checks_open_closed_open_eye_states_with_face_plus_plus(): void
    {
        Http::fakeSequence()
            ->push($this->detectionResponse(open: true))
            ->push($this->detectionResponse(open: false))
            ->push($this->detectionResponse(open: true));

        $passed = app(FacePlusPlusService::class)->verifyBlinkSequence(
            $this->image('open.jpg'),
            $this->image('closed.jpg'),
            $this->image('reopened.jpg')
        );

        $this->assertTrue($passed);
        Http::assertSentCount(3);
        Http::assertSent(fn (Request $request): bool =>
            $request->url() === 'https://face.test/detect'
            && str_contains($request->body(), 'eyestatus')
        );
    }

    public function test_server_rejects_blink_sequence_without_bilateral_closure(): void
    {
        Http::fakeSequence()
            ->push($this->detectionResponse(open: true))
            ->push($this->detectionResponse(open: true))
            ->push($this->detectionResponse(open: true));

        $passed = app(FacePlusPlusService::class)->verifyBlinkSequence(
            $this->image('open.jpg'),
            $this->image('not-closed.jpg'),
            $this->image('reopened.jpg')
        );

        $this->assertFalse($passed);
        Http::assertSentCount(2);
    }

    public function test_compare_uses_the_configured_threshold(): void
    {
        Http::fake([
            'https://face.test/compare' => Http::response([
                'confidence' => 89.5,
            ]),
        ]);

        $result = app(FacePlusPlusService::class)->compare(
            $this->image('reference.jpg'),
            $this->image('selfie.jpg')
        );

        $this->assertSame(90.0, $result['threshold']);
        $this->assertSame(89.5, $result['confidence']);
        $this->assertFalse($result['matched']);
    }

    public function test_malformed_image_is_rejected_before_provider_request(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'timepay-invalid-image-');
        file_put_contents($path, 'not an image');
        $this->imagePaths[] = $path;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not a supported image');

        app(FacePlusPlusService::class)->verifyBlinkSequence($path, $path, $path);
    }

    public function test_truncated_image_fails_as_validation(): void
    {
        $path = $this->image('truncated.jpg');
        $contents = file_get_contents($path);
        file_put_contents($path, substr($contents, 0, 100));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not a supported image');

        app(FacePlusPlusService::class)->verifyBlinkSequence($path, $path, $path);
    }

    public function test_image_over_compressed_size_limit_is_rejected_before_decode(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'timepay-large-image-');
        file_put_contents($path, str_repeat('x', 5 * 1024 * 1024 + 1));
        $this->imagePaths[] = $path;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('exceeds the supported file size');

        app(FacePlusPlusService::class)->verifyBlinkSequence($path, $path, $path);
    }

    public function test_image_exceeding_pixel_limit_is_rejected_before_gd_decode(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD is required to generate a synthetic image header.');
        }

        $path = tempnam(sys_get_temp_dir(), 'timepay-wide-image-');
        $image = imagecreatetruecolor(5000, 4000);
        imagejpeg($image, $path, 1);
        imagedestroy($image);
        $this->imagePaths[] = $path;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('dimensions or format');

        app(FacePlusPlusService::class)->verifyBlinkSequence($path, $path, $path);
    }

    public function test_supported_image_is_reencoded_as_bounded_jpeg(): void
    {
        Http::fake([
            'https://face.test/compare' => Http::response(['confidence' => 95]),
        ]);

        $result = app(FacePlusPlusService::class)->compare(
            $this->image('normal-reference.jpg'),
            $this->image('normal-selfie.jpg')
        );

        $this->assertTrue($result['matched']);
        Http::assertSent(fn (Request $request): bool =>
            $request->url() === 'https://face.test/compare'
            && str_contains($request->body(), 'image_file1')
        );
    }

    public function test_blink_verification_uses_valid_default_detect_url_when_unconfigured(): void
    {
        $facePlusPlusConfig = config('services.faceplusplus');
        unset($facePlusPlusConfig['detect_url']);
        config(['services.faceplusplus' => $facePlusPlusConfig]);
        Http::fake([
            'https://api-us.faceplusplus.com/facepp/v3/detect' => Http::sequence()
                ->push($this->detectionResponse(open: true))
                ->push($this->detectionResponse(open: false))
                ->push($this->detectionResponse(open: true)),
        ]);

        $passed = app(FacePlusPlusService::class)->verifyBlinkSequence(
            $this->image('default-open.jpg'),
            $this->image('default-closed.jpg'),
            $this->image('default-reopened.jpg')
        );

        $this->assertTrue($passed);
        Http::assertSent(fn (Request $request): bool =>
            $request->url() === 'https://api-us.faceplusplus.com/facepp/v3/detect'
        );
    }

    public function test_face_comparison_uses_valid_default_compare_url_when_unconfigured(): void
    {
        $facePlusPlusConfig = config('services.faceplusplus');
        unset($facePlusPlusConfig['compare_url']);
        config(['services.faceplusplus' => $facePlusPlusConfig]);
        Http::fake([
            'https://api-us.faceplusplus.com/facepp/v3/compare' => Http::response([
                'confidence' => 95,
            ]),
        ]);

        $result = app(FacePlusPlusService::class)->compare(
            $this->image('default-reference.jpg'),
            $this->image('default-selfie.jpg')
        );

        $this->assertTrue($result['matched']);
        Http::assertSent(fn (Request $request): bool =>
            $request->url() === 'https://api-us.faceplusplus.com/facepp/v3/compare'
        );
    }

    public function test_provider_errors_fail_as_temporary_unavailability(): void
    {
        Http::fake([
            'https://face.test/detect' => Http::response([
                'error_message' => 'AUTHENTICATION_ERROR',
            ], 401),
        ]);

        try {
            app(FacePlusPlusService::class)->verifyHeadTurnSequence(
                $this->image('neutral.jpg'),
                $this->image('left.jpg'),
                $this->image('right.jpg')
            );

            $this->fail('Expected a temporary provider error.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Face++ detection is temporarily unavailable.',
                $exception->getMessage()
            );
        }
    }

    public function test_timeout_rate_limit_and_server_errors_fail_as_temporary_unavailability(): void
    {
        foreach ([408, 429, 500, 503] as $status) {
            Http::fake([
                'https://face.test/detect' => Http::response([
                    'error_message' => 'TEST_PROVIDER_ERROR',
                    'error_code' => 'TEST_PROVIDER_ERROR',
                ], $status),
            ]);

            try {
                app(FacePlusPlusService::class)->verifyBlinkSequence(
                    $this->image('open-'.$status.'.jpg'),
                    $this->image('closed-'.$status.'.jpg'),
                    $this->image('reopened-'.$status.'.jpg')
                );

                $this->fail("Expected HTTP {$status} to fail closed.");
            } catch (RuntimeException $exception) {
                $this->assertSame(
                    'Face++ detection is temporarily unavailable.',
                    $exception->getMessage()
                );
            }
        }
    }

    public function test_transport_timeout_fails_as_temporary_unavailability(): void
    {
        $pendingRequest = \Mockery::mock(PendingRequest::class);
        Http::shouldReceive('withOptions')->once()->andReturn($pendingRequest);
        $pendingRequest->shouldReceive('timeout')->once()->with(15)->andReturnSelf();
        $pendingRequest->shouldReceive('attach')->once()->andReturnSelf();
        $pendingRequest->shouldReceive('post')
            ->once()
            ->andThrow(new ConnectionException('Test transport timeout.'));

        try {
            app(FacePlusPlusService::class)->verifyBlinkSequence(
                $this->image('transport-open.jpg'),
                $this->image('transport-closed.jpg'),
                $this->image('transport-reopened.jpg')
            );

            $this->fail('Expected a transport timeout to fail closed.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Face++ detection is temporarily unavailable.',
                $exception->getMessage()
            );
            $this->assertInstanceOf(ConnectionException::class, $exception->getPrevious());
        }
    }

    public function test_malformed_detection_response_is_not_treated_as_a_blink_rejection(): void
    {
        Http::fake([
            'https://face.test/detect' => Http::response(['unexpected' => true]),
        ]);

        try {
            app(FacePlusPlusService::class)->verifyBlinkSequence(
                $this->image('malformed-open.jpg'),
                $this->image('malformed-closed.jpg'),
                $this->image('malformed-reopened.jpg')
            );

            $this->fail('Expected a malformed provider response to fail closed.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Face++ detection is temporarily unavailable.',
                $exception->getMessage()
            );
            $this->assertInstanceOf(RuntimeException::class, $exception->getPrevious());
        }
    }

    private function image(string $name): string
    {
        if (! function_exists('imagecreatetruecolor')) {
            throw new RuntimeException(
                'The GD extension is required to create valid JPEG test fixtures.'
            );
        }

        $image = imagecreatetruecolor(32, 32);

        if ($image === false) {
            throw new RuntimeException(
                'Could not create the test image.'
            );
        }

        $path = tempnam(sys_get_temp_dir(), 'timepay-face-');

        if ($path === false) {
            imagedestroy($image);

            throw new RuntimeException(
                'Could not create a temporary image file.'
            );
        }

        $this->imagePaths[] = $path;

        try {
            if (! imagejpeg($image, $path, 90)) {
                throw new RuntimeException(
                    'Could not write the test JPEG.'
                );
            }
        } finally {
            imagedestroy($image);
        }

        return $path;
    }

    /** @return array<string, mixed> */
    private function detectionResponse(bool $open): array
    {
        $openValue = $open ? 95 : 2;
        $closedValue = $open ? 2 : 95;

        return [
            'faces' => [[
                'attributes' => [
                    'eyestatus' => [
                        'left_eye_status' => [
                            'no_glass_eye_open' => $openValue,
                            'no_glass_eye_close' => $closedValue,
                            'normal_glass_eye_open' => 0,
                            'normal_glass_eye_close' => 0,
                        ],
                        'right_eye_status' => [
                            'no_glass_eye_open' => $openValue,
                            'no_glass_eye_close' => $closedValue,
                            'normal_glass_eye_open' => 0,
                            'normal_glass_eye_close' => 0,
                        ],
                    ],
                ],
            ]],
        ];
    }

    /** @return array<string, mixed> */
    private function headTurnDetectionResponse(float $yaw): array
    {
        return [
            'faces' => [[
                'attributes' => [
                    'headpose' => ['yaw_angle' => $yaw],
                ],
            ]],
        ];
    }
}
