<?php

declare(strict_types=1);

namespace App\Modules\Operations\Infrastructure\Images\LocalVision\Http;

use App\Modules\Operations\Application\FacialPhotos\Validation\LocalVision\LocalVisionFacialPhotoAnalysis;
use App\Modules\Operations\Application\FacialPhotos\Validation\LocalVision\LocalVisionFacialPhotoClient;
use App\Modules\Operations\Application\FacialPhotos\Validation\LocalVision\LocalVisionFacialPhotoClientException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonException;

final readonly class LaravelHttpLocalVisionFacialPhotoClient implements LocalVisionFacialPhotoClient
{
    private string $baseUrl;

    private string $endpoint;

    private string $token;

    public function __construct(
        string $baseUrl,
        string $endpoint,
        string $token,
        private float $connectTimeoutSeconds,
        private float $requestTimeoutSeconds,
        private int $maximumRequestBytes,
        private int $maximumResponseBytes,
    ) {
        $this->baseUrl = rtrim(
            trim($baseUrl),
            '/'
        );

        $this->endpoint = trim($endpoint);
        $this->token = trim($token);

        $this->assertConfiguration();
    }

    public function analyze(
        string $absolutePath
    ): LocalVisionFacialPhotoAnalysis {
        [$contents, $filename] = $this->readImage(
            $absolutePath
        );

        try {
            $response = Http::baseUrl($this->baseUrl)
                ->acceptJson()
                ->withHeaders([
                    'X-Vanguard-Vision-Token' => $this->token,
                    'X-Vanguard-Request-Id' => Str::uuid()->toString(),
                ])
                ->withOptions([
                    'connect_timeout' => $this->connectTimeoutSeconds,
                    'timeout' => $this->requestTimeoutSeconds,
                ])
                ->attach(
                    'image',
                    $contents,
                    $filename
                )
                ->post($this->endpoint);
        } catch (ConnectionException $exception) {
            throw LocalVisionFacialPhotoClientException::serviceUnavailable(
                $exception
            );
        }

        if (
            $response->serverError()
            || in_array(
                $response->status(),
                [408, 429],
                true
            )
        ) {
            throw LocalVisionFacialPhotoClientException::serviceUnavailable();
        }

        if (! $response->successful()) {
            throw LocalVisionFacialPhotoClientException::requestRejected(
                $response->status()
            );
        }

        $body = $response->body();

        if (
            $body === ''
            || strlen($body) > $this->maximumResponseBytes
        ) {
            throw LocalVisionFacialPhotoClientException::invalidResponse();
        }

        try {
            $payload = json_decode(
                $body,
                true,
                32,
                JSON_THROW_ON_ERROR
            );

            if (
                ! is_array($payload)
                || array_is_list($payload)
            ) {
                throw new InvalidArgumentException(
                    'A resposta facial deve ser um objeto JSON.'
                );
            }

            return LocalVisionFacialPhotoAnalysis::fromPayload(
                $payload
            );
        } catch (
            JsonException|InvalidArgumentException $exception
        ) {
            throw LocalVisionFacialPhotoClientException::invalidResponse(
                $exception
            );
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function readImage(
        string $absolutePath
    ): array {
        if (
            trim($absolutePath) === ''
            || ! str_starts_with(
                $absolutePath,
                DIRECTORY_SEPARATOR
            )
            || ! is_file($absolutePath)
            || ! is_readable($absolutePath)
        ) {
            throw LocalVisionFacialPhotoClientException::imageUnavailable();
        }

        $size = filesize($absolutePath);

        if (
            ! is_int($size)
            || $size <= 0
            || $size > $this->maximumRequestBytes
        ) {
            throw LocalVisionFacialPhotoClientException::imageUnavailable();
        }

        $contents = file_get_contents($absolutePath);

        if (! is_string($contents) || $contents === '') {
            throw LocalVisionFacialPhotoClientException::imageUnavailable();
        }

        $filename = basename($absolutePath);

        if ($filename === '' || $filename === DIRECTORY_SEPARATOR) {
            $filename = 'facial-photo.bin';
        }

        return [$contents, $filename];
    }

    private function assertConfiguration(): void
    {
        $parts = parse_url($this->baseUrl);

        if (
            ! is_array($parts)
            || ! isset($parts['scheme'], $parts['host'])
            || ! in_array(
                strtolower((string) $parts['scheme']),
                ['http', 'https'],
                true
            )
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset(
                $parts['query'],
                $parts['fragment']
            )
            || $this->token === ''
            || ! str_starts_with(
                $this->endpoint,
                '/'
            )
            || str_contains(
                $this->endpoint,
                '://'
            )
            || $this->connectTimeoutSeconds <= 0
            || $this->requestTimeoutSeconds <= 0
            || $this->maximumRequestBytes <= 0
            || $this->maximumResponseBytes < 1024
        ) {
            throw LocalVisionFacialPhotoClientException::invalidConfiguration();
        }
    }
}
