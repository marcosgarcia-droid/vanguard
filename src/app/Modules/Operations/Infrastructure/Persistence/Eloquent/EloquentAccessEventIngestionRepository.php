<?php

namespace App\Modules\Operations\Infrastructure\Persistence\Eloquent;

use App\Modules\Operations\Application\AccessControl\Events\Ingest\AccessEventIngestionData;
use App\Modules\Operations\Application\AccessControl\Events\Ingest\AccessEventIngestionRepository;
use App\Modules\Operations\Application\AccessControl\Events\Ingest\AccessEventIngestionTarget;
use App\Modules\Operations\Application\AccessControl\Events\Ingest\IngestAccessEventResult;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceDirection;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceStatus;
use App\Modules\Operations\Domain\AccessControl\AccessEventStatus;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class EloquentAccessEventIngestionRepository implements AccessEventIngestionRepository
{
    public function findTarget(
        string $deviceId
    ): ?AccessEventIngestionTarget {
        $device = AccessDeviceRecord::query()
            ->find($deviceId);

        if (! $device instanceof AccessDeviceRecord) {
            return null;
        }

        $direction = $device->direction;

        if (! $direction instanceof AccessDeviceDirection) {
            $direction = AccessDeviceDirection::tryFrom(
                (string) $direction
            );
        }

        $status = $device->status;

        if (! $status instanceof AccessDeviceStatus) {
            $status = AccessDeviceStatus::tryFrom(
                (string) $status
            );
        }

        if (
            ! $direction instanceof AccessDeviceDirection
            || ! $status instanceof AccessDeviceStatus
        ) {
            return null;
        }

        return new AccessEventIngestionTarget(
            deviceId: $device->id,
            tenantId: $device->tenant_id,
            organizationId: $device->organization_id,
            provider: (string) $device->provider,
            direction: $direction,
            status: $status,
        );
    }

    public function persist(
        AccessEventIngestionData $data
    ): IngestAccessEventResult {
        return DB::transaction(
            function () use ($data): IngestAccessEventResult {
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

                $event = AccessEventRecord::query()
                    ->createOrFirst(
                        [
                            'access_device_id' => $device->id,
                            'external_event_id' => $data->externalEventId,
                        ],
                        [
                            'tenant_id' => $device->tenant_id,
                            'organization_id' => $device->organization_id,
                            'visitor_id' => null,
                            'visit_id' => null,
                            'external_person_id' => $data->externalPersonId,
                            'event_type' => $data->eventType,
                            'direction' => $data->direction,
                            'occurred_at' => $data->occurredAt,
                            'status' => $data->status,
                            'result_code' => $data->resultCode,
                            'result_message' => $data->resultMessage,
                            'raw_payload' => $data->payload !== []
                                ? $data->payload
                                : null,
                        ]
                    );

                if (
                    $event->wasRecentlyCreated
                    && (
                        $device->last_event_at === null
                        || $device->last_event_at->getTimestamp()
                            < $data->occurredAt->getTimestamp()
                    )
                ) {
                    $device
                        ->forceFill([
                            'last_event_at' => $data->occurredAt,
                        ])
                        ->saveQuietly();
                }

                $status = $event->status;

                if (! $status instanceof AccessEventStatus) {
                    $status = AccessEventStatus::tryFrom(
                        (string) $status
                    );
                }

                if (! $status instanceof AccessEventStatus) {
                    throw new RuntimeException(
                        'O evento foi persistido com um status inválido.'
                    );
                }

                return new IngestAccessEventResult(
                    eventId: $event->id,
                    status: $status,
                    duplicate: ! $event->wasRecentlyCreated,
                );
            }
        );
    }
}
