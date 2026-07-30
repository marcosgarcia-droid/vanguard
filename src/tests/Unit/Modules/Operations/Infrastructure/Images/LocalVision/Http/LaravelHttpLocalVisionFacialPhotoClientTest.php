<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Infrastructure\Images\LocalVision\Http;

use App\Modules\Operations\Application\FacialPhotos\Validation\LocalVision\LocalVisionFacialPhotoClient;
use App\Modules\Operations\Application\FacialPhotos\Validation\LocalVision\LocalVisionFacialPhotoClientException;
use App\Modules\Operations\Application\FacialPhotos\Validation\LocalVision\LocalVisionFacialPhotoClientFailure;
use App\Modules\Operations\Infrastructure\Images\LocalVision\Http\LaravelHttpLocalVisionFacialPhotoClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class LaravelHttpLocalVisionFacialPhotoClientTest extends TestCase
{
    private string $imagePath;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        $temporaryPath = tempnam(
            sys_get_temp_dir(),
            'vanguard-facial-vision-'
        );

        self::assertIsString($temporaryPath);

        $this->imagePath = $temporaryPath;

        file_put_contents(
            $this->imagePath,
            'synthetic-facial-photo-bytes'
        );
    }

    protected function tearDown(): void
    {
        if (
            isset($this->imagePath)
            && is_file($this->imagePath)
        ) {
            unlink($this->imagePath);
        }

        parent::tearDown();
    }

    public function test_it_sends_the_image_and_sanitizes_the_response(): void
    {
        Http::fake([
            'http://facial-vision.test/v1/facial-photo/analyze' => Http::response(
                [
                    'schema_version' => '1.0',
                    'service' => 'vanguard-facial-vision',
                    'service_version' => '0.1.0',
                    'engine' => 'mediapipe-opencv',
                    'engine_version' => 'foundation',
                    'face_count' => 1,
                    'metrics' => [
                        'detection_confidence' => 0.98,
                        'face_ratio' => 0.47,
                        'centered' => true,
                        'unknown_sensitive_field' => 'discarded',
                    ],
                    'unexpected_root_field' => 'discarded',
                ],
                200,
                [
                    'Content-Type' => 'application/json',
                ],
            ),
        ]);

        $analysis = $this->client()->analyze(
            $this->imagePath
        );

        $this->assertSame('0.1.0', $analysis->serviceVersion);
        $this->assertSame(
            'mediapipe-opencv',
            $analysis->engine
        );
        $this->assertSame(1, $analysis->faceCount);
        $this->assertSame(
            [
                'detection_confidence' => 0.98,
                'face_ratio' => 0.47,
                'centered' => true,
            ],
            $analysis->metrics
        );

        Http::assertSentCount(1);

        Http::assertSent(
            static fn (Request $request): bool => $request->url()
                === 'http://facial-vision.test/v1/facial-photo/analyze'
                && $request->method() === 'POST'
                && $request->hasHeader(
                    'X-Vanguard-Vision-Token',
                    'synthetic-secret'
                )
                && $request->hasHeader(
                    'X-Vanguard-Request-Id'
                )
        );
    }

    public function test_it_reports_connection_failures_as_unavailable(): void
    {
        Http::fake(
            static fn () => throw new ConnectionException(
                'Synthetic connection failure.'
            )
        );

        $exception = $this->captureFailure(
            fn () => $this->client()->analyze(
                $this->imagePath
            )
        );

        $this->assertSame(
            LocalVisionFacialPhotoClientFailure::ServiceUnavailable,
            $exception->failure
        );
    }

    public function test_it_reports_server_errors_as_unavailable(): void
    {
        Http::fake([
            '*' => Http::response(
                ['message' => 'unavailable'],
                503
            ),
        ]);

        $exception = $this->captureFailure(
            fn () => $this->client()->analyze(
                $this->imagePath
            )
        );

        $this->assertSame(
            LocalVisionFacialPhotoClientFailure::ServiceUnavailable,
            $exception->failure
        );
    }

    public function test_it_distinguishes_a_rejected_request(): void
    {
        Http::fake([
            '*' => Http::response(
                ['message' => 'invalid request'],
                422
            ),
        ]);

        $exception = $this->captureFailure(
            fn () => $this->client()->analyze(
                $this->imagePath
            )
        );

        $this->assertSame(
            LocalVisionFacialPhotoClientFailure::RequestRejected,
            $exception->failure
        );
    }

    public function test_it_rejects_an_invalid_service_response(): void
    {
        Http::fake([
            '*' => Http::response(
                [
                    'schema_version' => '1.0',
                    'service' => 'wrong-service',
                    'service_version' => '0.1.0',
                    'engine' => 'mediapipe-opencv',
                    'engine_version' => 'foundation',
                    'face_count' => 1,
                    'metrics' => [],
                ],
                200
            ),
        ]);

        $exception = $this->captureFailure(
            fn () => $this->client()->analyze(
                $this->imagePath
            )
        );

        $this->assertSame(
            LocalVisionFacialPhotoClientFailure::InvalidResponse,
            $exception->failure
        );
    }

    public function test_it_does_not_send_an_unavailable_image(): void
    {
        Http::fake();

        $exception = $this->captureFailure(
            fn () => $this->client()->analyze(
                '/tmp/nonexistent-vanguard-facial-photo.jpg'
            )
        );

        $this->assertSame(
            LocalVisionFacialPhotoClientFailure::ImageUnavailable,
            $exception->failure
        );

        Http::assertNothingSent();
    }

    public function test_it_requires_a_non_empty_internal_token(): void
    {
        $exception = $this->captureFailure(
            fn () => new LaravelHttpLocalVisionFacialPhotoClient(
                baseUrl: 'http://facial-vision.test',
                endpoint: '/v1/facial-photo/analyze',
                token: '',
                connectTimeoutSeconds: 1,
                requestTimeoutSeconds: 5,
                maximumRequestBytes: 5 * 1024 * 1024,
                maximumResponseBytes: 64 * 1024,
            )
        );

        $this->assertSame(
            LocalVisionFacialPhotoClientFailure::InvalidConfiguration,
            $exception->failure
        );
    }

    public function test_it_rejects_a_base_url_with_embedded_credentials(): void
    {
        $exception = $this->captureFailure(
            fn () => new LaravelHttpLocalVisionFacialPhotoClient(
                baseUrl: 'http://internal-user@facial-vision.test',
                endpoint: '/v1/facial-photo/analyze',
                token: 'synthetic-secret',
                connectTimeoutSeconds: 1,
                requestTimeoutSeconds: 5,
                maximumRequestBytes: 5 * 1024 * 1024,
                maximumResponseBytes: 64 * 1024,
            )
        );

        $this->assertSame(
            LocalVisionFacialPhotoClientFailure::InvalidConfiguration,
            $exception->failure
        );

        Http::assertNothingSent();
    }

    public function test_the_container_binds_the_http_client(): void
    {
        config()->set(
            'facial_photos.validation.local_vision.base_url',
            'http://facial-vision.test'
        );

        config()->set(
            'facial_photos.validation.local_vision.token',
            'synthetic-secret'
        );

        $client = app(
            LocalVisionFacialPhotoClient::class
        );

        $this->assertInstanceOf(
            LaravelHttpLocalVisionFacialPhotoClient::class,
            $client
        );

        Http::assertNothingSent();
    }

    private function client(): LaravelHttpLocalVisionFacialPhotoClient
    {
        return new LaravelHttpLocalVisionFacialPhotoClient(
            baseUrl: 'http://facial-vision.test',
            endpoint: '/v1/facial-photo/analyze',
            token: 'synthetic-secret',
            connectTimeoutSeconds: 1,
            requestTimeoutSeconds: 5,
            maximumRequestBytes: 5 * 1024 * 1024,
            maximumResponseBytes: 64 * 1024,
        );
    }

    /**
     * @param  callable(): mixed  $operation
     */
    private function captureFailure(
        callable $operation
    ): LocalVisionFacialPhotoClientException {
        try {
            $operation();
        } catch (
            LocalVisionFacialPhotoClientException $exception
        ) {
            return $exception;
        }

        $this->fail(
            'Era esperada uma falha tipada do cliente facial local.'
        );
    }
}
