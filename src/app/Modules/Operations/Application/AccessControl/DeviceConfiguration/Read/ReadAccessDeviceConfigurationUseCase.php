<?php

namespace App\Modules\Operations\Application\AccessControl\DeviceConfiguration\Read;

use App\Modules\Operations\Application\AccessControl\AccessControlRuntime;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceConfigurationReadStatus;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceStatus;
use InvalidArgumentException;

final readonly class ReadAccessDeviceConfigurationUseCase
{
    public function __construct(
        private AccessDeviceConfigurationReaderResolver $readerResolver,
        private AccessDeviceConfigurationReadRepository $repository,
        private AccessDeviceConfigurationReadGuard $guard,
        private AccessControlRuntime $runtime,
        private AccessDeviceNetworkAddressPolicy $networkAddressPolicy,
    ) {}

    public function execute(
        ReadAccessDeviceConfigurationCommand $command
    ): ReadAccessDeviceConfigurationResult {
        $target = $this->repository->findTarget(
            $command->deviceId
        );

        if ($target === null) {
            throw new ReadAccessDeviceConfigurationException(
                'O dispositivo de acesso não foi encontrado.'
            );
        }

        $provider = strtolower(
            trim($target->provider)
        );

        $this->assertProviderEnabled(
            $provider
        );

        try {
            $reader = $this->readerResolver->resolve(
                $provider
            );
        } catch (InvalidArgumentException $exception) {
            throw new ReadAccessDeviceConfigurationException(
                message: $exception->getMessage(),
                previous: $exception,
            );
        }

        try {
            $lease = $this->guard->acquire(
                $target->deviceId
            );
        } catch (
            AccessDeviceConfigurationReadGuardException $exception
        ) {
            throw new ReadAccessDeviceConfigurationException(
                message: $exception->getMessage(),
                previous: $exception,
            );
        }

        $startedAt = hrtime(true);

        try {
            try {
                $connection = $this->connectionData(
                    $target,
                    $provider
                );

                $lease->markReaderCalled();

                $readResult = $reader->read(
                    $connection
                );
            } catch (
                AccessDeviceConfigurationReadException
                |InvalidArgumentException $exception
            ) {
                $snapshotId = $this->repository->persist(
                    new AccessDeviceConfigurationReadPersistenceData(
                        deviceId: $target->deviceId,
                        requestedByUserId: $command->requestedByUserId,
                        source: $command->source,
                        status: AccessDeviceConfigurationReadStatus::Failed,
                        configuration: [],
                        capabilities: [],
                        sanitizedResponse: [],
                        firmwareVersion: null,
                        durationMs: $this->durationMs(
                            $startedAt
                        ),
                        message: $exception->getMessage(),
                        warnings: [],
                    )
                );

                throw new ReadAccessDeviceConfigurationException(
                    $exception->getMessage(),
                    $snapshotId,
                    $exception
                );
            }

            $snapshotId = $this->repository->persist(
                new AccessDeviceConfigurationReadPersistenceData(
                    deviceId: $target->deviceId,
                    requestedByUserId: $command->requestedByUserId,
                    source: $command->source,
                    status: $readResult->status,
                    configuration: $readResult->configuration,
                    capabilities: $readResult->capabilities,
                    sanitizedResponse: $readResult->sanitizedResponse,
                    firmwareVersion: $readResult->firmwareVersion,
                    durationMs: $readResult->durationMs,
                    message: $readResult->message,
                    warnings: $readResult->warnings,
                )
            );

            return new ReadAccessDeviceConfigurationResult(
                snapshotId: $snapshotId,
                status: $readResult->status,
                message: $readResult->message,
                warnings: $readResult->warnings,
            );
        } finally {
            $lease->release();
        }
    }

    private function assertProviderEnabled(
        string $provider
    ): void {
        if (
            $provider === 'simulator'
            && ! (bool) config(
                'access_control.simulator_enabled',
                false
            )
        ) {
            throw new ReadAccessDeviceConfigurationException(
                'O simulador de dispositivos está desativado neste ambiente.'
            );
        }

        if (
            $provider === 'intelbras'
            && ! $this->runtime->allowsReads()
        ) {
            throw new ReadAccessDeviceConfigurationException(
                'A comunicação de leitura com os dispositivos está desativada neste ambiente.'
            );
        }
    }

    private function connectionData(
        AccessDeviceConfigurationTarget $target,
        string $provider
    ): AccessDeviceConnectionData {
        if (
            $target->status
            !== AccessDeviceStatus::Active
        ) {
            throw new InvalidArgumentException(
                'O dispositivo precisa estar ativo para realizar a leitura.'
            );
        }

        if ($provider === 'simulator') {
            return new AccessDeviceConnectionData(
                deviceId: $target->deviceId,
                protocol: 'http',
                ipAddress: '127.0.0.1',
                port: 1,
                username: 'simulator',
                password: 'synthetic-only',
                verifyTls: false,
                metadata: [
                    'scenario' => data_get(
                        $target->settings,
                        'simulator_scenario',
                        config(
                            'access_control.simulator_default_scenario',
                            'success'
                        )
                    ),
                ],
            );
        }

        if ($provider !== 'intelbras') {
            throw new InvalidArgumentException(
                'O provider do dispositivo não é suportado.'
            );
        }

        if (
            strtolower(
                (string) $target->authType
            ) !== 'digest'
        ) {
            throw new InvalidArgumentException(
                'O dispositivo precisa utilizar autenticação HTTP Digest.'
            );
        }

        $protocol = strtolower(
            (string) ($target->protocol ?: 'http')
        );

        $port = $target->port
            ?: ($protocol === 'https' ? 443 : 80);

        $ipAddress = (string) $target->ipAddress;

        $this->networkAddressPolicy
            ->assertAllowed($ipAddress);

        return new AccessDeviceConnectionData(
            deviceId: $target->deviceId,
            protocol: $protocol,
            ipAddress: $ipAddress,
            port: $port,
            username: (string) $target->username,
            password: (string) $target->password,
            verifyTls: $target->verifyTls,
        );
    }

    private function durationMs(
        int $startedAt
    ): int {
        return max(
            0,
            (int) round(
                (hrtime(true) - $startedAt)
                / 1_000_000
            )
        );
    }
}
