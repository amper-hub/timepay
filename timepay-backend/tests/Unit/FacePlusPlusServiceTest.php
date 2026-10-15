<?php

namespace Tests\Unit;

use App\Services\FacePlusPlusService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
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

    public function test_server_verifies_open_closed_open_eye_states_with_face_plus_plus(): void
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
            && str_contains($request->body(), 'return_attributes')
            && str_contains($request->body(), 'eyestatus')
        );
    }

    public function test_server_rejects_a_sequence_without_a_closed_eye_sample(): void
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
            'https://face.test/compare' => Http::response(['confidence' => 89.5]),
        ]);

        $result = app(FacePlusPlusService::class)->compare(
            $this->image('reference.jpg'),
            $this->image('selfie.jpg')
        );

        $this->assertSame(90.0, $result['threshold']);
        $this->assertSame(89.5, $result['confidence']);
        $this->assertFalse($result['matched']);
    }

    public function test_provider_errors_fail_as_temporary_unavailability(): void
    {
        Http::fake([
            'https://face.test/detect' => Http::response(['error_message' => 'AUTHENTICATION_ERROR'], 401),
        ]);

        try {
            app(FacePlusPlusService::class)->verifyBlinkSequence(
                $this->image('open.jpg'),
                $this->image('closed.jpg'),
                $this->image('reopened.jpg')
            );
            $this->fail('Expected a temporary provider error.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Face++ detection is temporarily unavailable.', $exception->getMessage());
        }
    }

    private function image(string $name): string
    {
        $path = tempnam(sys_get_temp_dir(), 'timepay-face-');
        // FacePlusPlusService validates and decodes image bytes before making
        // HTTP requests, so use a real 1x1 PNG fixture instead of arbitrary text.
        $image = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAC0lEQVR4nGP4DwQACfsD/fteaysAAAAASUVORK5CYII=',
            true
        );

        if ($image === false) {
            throw new RuntimeException('Could not decode the test image fixture.');
        }

        file_put_contents($path, $image);
        $this->imagePaths[] = $path;

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
}
