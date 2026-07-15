<?php

namespace App\Modules\Operations\Infrastructure\Persistence\Eloquent;

use App\Models\User;
use App\Modules\Operations\Application\AccessControl\DeviceConfiguration\Read\AccessDeviceConfigurationReadPersistenceData;
use App\Modules\Operations\Application\AccessControl\DeviceConfiguration\Read\AccessDeviceConfigurationReadRepository;
use App\Modules\Operations\Application\AccessControl\DeviceConfiguration\Read\AccessDeviceConfigurationStateMerger;
use App\Modules\Operations\Application\AccessControl\DeviceConfiguration\Read\AccessDeviceConfigurationTarget;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceConfigurationReadStatus;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceStatus;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class EloquentAccessDeviceConfigurationReadRepository implements AccessDeviceConfigurationReadRepository
{
    public function __construct(
        private readonly AccessDeviceConfigurationStateMerger $stateMerger,
    ) {}

    public function findTarget(
        string $deviceId
    ): ?AccessDeviceConfigurationTarget {
        $device = AccessDeviceRecord::query()
            ->find($deviceId);

        if (! $device instanceof AccessDeviceRecord) {
            return null;
        }

        $status = $device->status;

        if (! $status instanceof AccessDeviceStatus) {
            $status = AccessDeviceStatus::tryFrom(
                (string) $status
            );
        }

        if (! $status instanceof AccessDeviceStatus) {
            return null;
        }

        return new AccessDeviceConfigurationTarget(
            deviceId: $device->id,
            provider: (string) $device->provider,
            status: $status,
            model: $device->model,
            protocol: $device->protocol,
            ipAddress: $device->ip_address,
            port: $device->port,
            authType: $device->auth_type,
            username: $device->credential_username,
            password: $device->credential_password,
            verifyTls: (bool) data_get(
                $device->settings,
                'verify_tls',
                false
            ),
        );
    }

    public function persist(
        AccessDeviceConfigurationReadPersistenceData $data
    ): string {
        return DB::transaction(
            function () use ($data): string {
                $device = AccessDeviceRecord::query()
                    ->lockForUpdate()
                    ->find($data->deviceId);

                if (
                    ! $device
                    instanceof AccessDeviceRecord
                ) {
                    throw new RuntimeException(
                        'O dispositivo deixou de estar disponível durante a persistência.'
                    );
                }

                $readAt = now();

                $sanitizedResponse =
                    $data->sanitizedResponse;

                if ($data->warnings !== []) {
                    $sanitizedResponse[
                        '_warnings'
                    ] = $data->warnings;
                }

                $snapshot =
                    AccessDeviceConfigurationSnapshotRecord::query()
                        ->create([
                            'access_device_id' => $device->id,
                            'tenant_id' => $device->tenant_id,
                            'organization_id' => $device->organization_id,
                            'requested_by' => $data->requestedByUserId,
                            'source' => $data->source,
                            'status' => $data->status,
                            'device_model' => $device->model,
                            'firmware_version' => $data->firmwareVersion,
                            'configuration' => $data->configuration !== []
                                    ? $data->configuration
                                    : null,
                            'capabilities' => $data->capabilities !== []
                                    ? $data->capabilities
                                    : null,
                            'sanitized_response' => $sanitizedResponse !== []
                                    ? $sanitizedResponse
                                    : null,
                            'configuration_hash' => $this->configurationHash(
                                $data->configuration
                            ),
                            'read_at' => $readAt,
                            'duration_ms' => $data->durationMs,
                            'message' => $data->message,
                        ]);

                $updates = [
                    'configuration_read_at' => $readAt,
                    'configuration_read_status' => $data->status,
                    'configuration_read_message' => $data->message,
                    'last_communication_at' => $readAt,
                    'last_communication_status' => $data->status->value,
                    'last_communication_message' => $data->message,
                ];

                if (
                    $data->status
                    === AccessDeviceConfigurationReadStatus::Success
                ) {
                    $updates[
                        'current_configuration'
                    ] = $data->configuration;

                    $updates['capabilities'] =
                        $data->capabilities;
                }

                if (
                    $data->status
                    === AccessDeviceConfigurationReadStatus::Partial
                ) {
                    $currentConfiguration =
                        is_array(
                            $device->current_configuration
                        )
                            ? $device->current_configuration
                            : [];

                    $currentCapabilities =
                        is_array(
                            $device->capabilities
                        )
                            ? $device->capabilities
                            : [];

                    $updates[
                        'current_configuration'
                    ] = $this->stateMerger->merge(
                        $currentConfiguration,
                        $data->configuration
                    );

                    $updates['capabilities'] =
                        $this->stateMerger->merge(
                            $currentCapabilities,
                            $data->capabilities
                        );
                }

                $device
                    ->forceFill($updates)
                    ->saveQuietly();

                $activity = activity(
                    'access_control'
                )
                    ->performedOn($device)
                    ->event('configuration_read')
                    ->withProperties([
                        'snapshot_id' => $snapshot->id,
                        'status' => $data->status->value,
                        'source' => $data->source->value,
                        'duration_ms' => $data->durationMs,
                        'message' => $data->message,
                        'warnings' => $data->warnings,
                    ]);

                if (
                    $data->requestedByUserId
                    !== null
                ) {
                    $user = User::query()->find(
                        $data->requestedByUserId
                    );

                    if ($user instanceof User) {
                        $activity->causedBy($user);
                    }
                }

                $activity->log(
                    'Leitura de configurações do dispositivo'
                );

                return $snapshot->id;
            }
        );
    }

    /**
     * @param  array<string, mixed>  $configuration
     */
    private function configurationHash(
        array $configuration
    ): ?string {
        if ($configuration === []) {
            return null;
        }

        $encoded = json_encode(
            $configuration,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
        );

        if ($encoded === false) {
            return null;
        }

        return hash('sha256', $encoded);
    }
}
