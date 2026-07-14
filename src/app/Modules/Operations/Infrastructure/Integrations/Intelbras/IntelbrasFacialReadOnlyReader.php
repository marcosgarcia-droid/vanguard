<?php

namespace App\Modules\Operations\Infrastructure\Integrations\Intelbras;

use App\Modules\Operations\Application\AccessControl\DeviceConfiguration\Read\AccessDeviceConfigurationReader;
use App\Modules\Operations\Application\AccessControl\DeviceConfiguration\Read\AccessDeviceConfigurationReadException;
use App\Modules\Operations\Application\AccessControl\DeviceConfiguration\Read\AccessDeviceConfigurationReadResult;
use App\Modules\Operations\Application\AccessControl\DeviceConfiguration\Read\AccessDeviceConnectionData;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceConfigurationReadStatus;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

final readonly class IntelbrasFacialReadOnlyReader implements AccessDeviceConfigurationReader
{
    public function __construct(
        private IntelbrasTextResponseParser $parser,
        private IntelbrasConfigurationNormalizer $normalizer,
    ) {}

    public function read(
        AccessDeviceConnectionData $connection
    ): AccessDeviceConfigurationReadResult {
        $startedAt = hrtime(true);

        $responses = [];
        $sanitizedResponse = [];
        $warnings = [];

        foreach (IntelbrasReadOnlyEndpoint::cases() as $endpoint) {
            try {
                $response = $this->request(
                    $connection,
                    $endpoint
                );

                $parsed = $this->parser->parse(
                    $response->body()
                );

                if ($parsed === []) {
                    throw new AccessDeviceConfigurationReadException(
                        'O equipamento retornou uma resposta vazia ou não reconhecida.',
                        $endpoint->value
                    );
                }

                $responses[$endpoint->value] = $parsed;

                $sanitizedResponse[$endpoint->value] = [
                    'endpoint' => $endpoint->relativeUrl(),
                    'values' => $parsed,
                ];
            } catch (AccessDeviceConfigurationReadException $exception) {
                if ($endpoint->isEssential()) {
                    throw $exception;
                }

                $warnings[] = $endpoint->label()
                    .': '
                    .$exception->getMessage();
            }
        }

        $normalized = $this->normalizer->normalize(
            $responses
        );

        $durationMs = $this->durationMs(
            $startedAt
        );

        $status = $warnings === []
            ? AccessDeviceConfigurationReadStatus::Success
            : AccessDeviceConfigurationReadStatus::Partial;

        $message = $warnings === []
            ? 'Leitura somente leitura concluída com sucesso.'
            : 'Leitura concluída parcialmente. '
                .implode(' ', $warnings);

        return new AccessDeviceConfigurationReadResult(
            status: $status,
            configuration: $normalized['configuration'],
            capabilities: $normalized['capabilities'],
            sanitizedResponse: $sanitizedResponse,
            firmwareVersion: $normalized['firmware_version'],
            durationMs: $durationMs,
            message: $message,
            warnings: $warnings,
        );
    }

    private function request(
        AccessDeviceConnectionData $connection,
        IntelbrasReadOnlyEndpoint $endpoint
    ): Response {
        $url = $connection->baseUrl()
            .$endpoint->relativeUrl();

        try {
            $response = Http::accept('text/plain')
                ->withOptions([
                    'auth' => [
                        $connection->username,
                        $connection->password,
                        'digest',
                    ],
                    'connect_timeout' => (float) config(
                        'access_control.connect_timeout_seconds',
                        2
                    ),
                    'timeout' => (float) config(
                        'access_control.request_timeout_seconds',
                        5
                    ),
                    'verify' => $connection->verifyTls,
                    'allow_redirects' => false,
                ])
                ->get($url);
        } catch (ConnectionException $exception) {
            throw new AccessDeviceConfigurationReadException(
                'Não foi possível conectar ao equipamento.',
                $endpoint->value,
                $exception
            );
        } catch (Throwable $exception) {
            throw new AccessDeviceConfigurationReadException(
                'Falha inesperada durante a comunicação.',
                $endpoint->value,
                $exception
            );
        }

        if ($response->status() === 401) {
            throw new AccessDeviceConfigurationReadException(
                'As credenciais foram recusadas pelo equipamento.',
                $endpoint->value
            );
        }

        if (! $response->successful()) {
            throw new AccessDeviceConfigurationReadException(
                'O equipamento respondeu com HTTP '
                    .$response->status()
                    .'.',
                $endpoint->value
            );
        }

        return $response;
    }

    private function durationMs(int $startedAt): int
    {
        return max(
            0,
            (int) round(
                (hrtime(true) - $startedAt)
                / 1_000_000
            )
        );
    }
}
