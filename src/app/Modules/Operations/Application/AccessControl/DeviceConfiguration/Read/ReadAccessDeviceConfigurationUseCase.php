<?php

namespace App\Modules\Operations\Application\AccessControl\DeviceConfiguration\Read;

use App\Modules\Operations\Application\AccessControl\AccessControlRuntime;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceConfigurationReadStatus;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceStatus;
use InvalidArgumentException;

final readonly class ReadAccessDeviceConfigurationUseCase
{
    public function __construct(
        private AccessDeviceConfigurationReader $reader,
        private AccessDeviceConfigurationReadRepository $repository,
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

        if (! $this->runtime->allowsReads()) {
            throw new ReadAccessDeviceConfigurationException(
                'A comunicação de leitura com os dispositivos está desativada neste ambiente.'
            );
        }

        $startedAt = hrtime(true);

        try {
            $connection = $this->connectionData(
                $target
            );

            $readResult = $this->reader->read(
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
    }

    private function connectionData(
        AccessDeviceConfigurationTarget $target
    ): AccessDeviceConnectionData {
        if (
            strtolower($target->provider)
            !== 'intelbras'
        ) {
            throw new InvalidArgumentException(
                'O dispositivo não utiliza o provider Intelbras.'
            );
        }

        if (
            $target->status
            !== AccessDeviceStatus::Active
        ) {
            throw new InvalidArgumentException(
                'O dispositivo precisa estar ativo para realizar a leitura.'
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
