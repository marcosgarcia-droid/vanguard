<?php

namespace Tests\Unit\Modules\Operations\Application\AccessControl\Events\Ingest;

use App\Modules\Operations\Application\AccessControl\Events\Ingest\IngestAccessEventCommand;
use App\Modules\Operations\Application\AccessControl\Events\Ingest\IngestAccessEventException;
use App\Modules\Operations\Application\AccessControl\Events\Ingest\IngestAccessEventUseCase;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceStatus;
use App\Modules\Operations\Domain\AccessControl\AccessEventDirection;
use App\Modules\Operations\Domain\AccessControl\AccessEventStatus;
use App\Modules\Operations\Infrastructure\Integrations\Simulator\SimulatedAccessEventGenerator;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessDeviceRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessEventRecord;
use Database\Seeders\VanguardAccessDeviceDemoSeeder;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IngestAccessEventUseCaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set(
            'access_control.simulator_enabled',
            true
        );

        Http::preventStrayRequests();
    }

    public function test_it_ingests_a_safe_synthetic_event_without_processing_a_visit(): void
    {
        $device = $this->device(
            'FAC-SIM-ENT-01'
        );

        $occurredAt = new DateTimeImmutable(
            '2026-07-15 10:15:00'
        );

        $command = app(
            SimulatedAccessEventGenerator::class
        )->generate(
            deviceId: $device->id,
            direction: AccessEventDirection::Entry,
            sequence: 1,
            occurredAt: $occurredAt,
            externalPersonId: 'synthetic-person-001',
        );

        $result = app(
            IngestAccessEventUseCase::class
        )->execute($command);

        $this->assertFalse(
            $result->duplicate
        );

        $this->assertSame(
            AccessEventStatus::PendingAssociation,
            $result->status
        );

        $event = AccessEventRecord::query()
            ->findOrFail($result->eventId);

        $this->assertSame(
            $device->id,
            $event->access_device_id
        );

        $this->assertSame(
            $device->tenant_id,
            $event->tenant_id
        );

        $this->assertSame(
            $device->organization_id,
            $event->organization_id
        );

        $this->assertNull(
            $event->visitor_id
        );

        $this->assertNull(
            $event->visit_id
        );

        $this->assertSame(
            'synthetic-person-001',
            $event->external_person_id
        );

        $this->assertSame(
            [
                'source' => 'simulator',
                'synthetic' => true,
                'sequence' => 1,
                'event_kind' => 'face_recognition',
                'result' => 'recognized',
            ],
            $event->raw_payload
        );

        $device->refresh();

        $this->assertSame(
            $occurredAt->getTimestamp(),
            $device->last_event_at?->getTimestamp()
        );

        Http::assertSentCount(0);
    }

    public function test_it_is_idempotent_by_device_and_external_event_id(): void
    {
        $device = $this->device(
            'FAC-SIM-ENT-01'
        );

        $command = app(
            SimulatedAccessEventGenerator::class
        )->generate(
            deviceId: $device->id,
            direction: AccessEventDirection::Entry,
            sequence: 2,
            occurredAt: new DateTimeImmutable(
                '2026-07-15 10:20:00'
            ),
            externalPersonId: 'synthetic-person-002',
        );

        $first = app(
            IngestAccessEventUseCase::class
        )->execute($command);

        $second = app(
            IngestAccessEventUseCase::class
        )->execute($command);

        $this->assertFalse(
            $first->duplicate
        );

        $this->assertTrue(
            $second->duplicate
        );

        $this->assertSame(
            $first->eventId,
            $second->eventId
        );

        $this->assertSame(
            1,
            AccessEventRecord::query()
                ->where(
                    'access_device_id',
                    $device->id
                )
                ->where(
                    'external_event_id',
                    $command->externalEventId
                )
                ->count()
        );

        Http::assertSentCount(0);
    }

    public function test_it_rejects_a_direction_incompatible_with_the_device(): void
    {
        $device = $this->device(
            'FAC-SIM-ENT-01'
        );

        $command = app(
            SimulatedAccessEventGenerator::class
        )->generate(
            deviceId: $device->id,
            direction: AccessEventDirection::Exit,
            sequence: 3,
            occurredAt: new DateTimeImmutable(
                '2026-07-15 10:25:00'
            ),
        );

        try {
            app(
                IngestAccessEventUseCase::class
            )->execute($command);

            $this->fail(
                'Era esperado o bloqueio da direção incompatível.'
            );
        } catch (
            IngestAccessEventException $exception
        ) {
            $this->assertStringContainsString(
                'direção',
                mb_strtolower(
                    $exception->getMessage()
                )
            );
        }

        $this->assertSame(
            0,
            AccessEventRecord::query()->count()
        );

        Http::assertSentCount(0);
    }

    public function test_it_keeps_an_event_without_person_reference_as_received(): void
    {
        $device = $this->device(
            'FAC-SIM-SAI-01'
        );

        $command = app(
            SimulatedAccessEventGenerator::class
        )->generate(
            deviceId: $device->id,
            direction: AccessEventDirection::Exit,
            sequence: 4,
            occurredAt: new DateTimeImmutable(
                '2026-07-15 10:30:00'
            ),
        );

        $result = app(
            IngestAccessEventUseCase::class
        )->execute($command);

        $this->assertSame(
            AccessEventStatus::Received,
            $result->status
        );

        $event = AccessEventRecord::query()
            ->findOrFail($result->eventId);

        $this->assertNull(
            $event->external_person_id
        );

        $this->assertSame(
            'received',
            $event->result_code
        );

        $this->assertSame(
            'unmatched',
            data_get(
                $event->raw_payload,
                'result'
            )
        );

        Http::assertSentCount(0);
    }

    public function test_it_rejects_an_inactive_device_without_creating_an_event(): void
    {
        $device = $this->device(
            'FAC-SIM-ENT-01'
        );

        $device
            ->forceFill([
                'status' => AccessDeviceStatus::Inactive,
            ])
            ->saveQuietly();

        $command = app(
            SimulatedAccessEventGenerator::class
        )->generate(
            deviceId: $device->id,
            direction: AccessEventDirection::Entry,
            sequence: 5,
            occurredAt: new DateTimeImmutable(
                '2026-07-15 10:35:00'
            ),
        );

        try {
            app(
                IngestAccessEventUseCase::class
            )->execute($command);

            $this->fail(
                'Era esperado o bloqueio do dispositivo inativo.'
            );
        } catch (
            IngestAccessEventException $exception
        ) {
            $this->assertStringContainsString(
                'ativo',
                mb_strtolower(
                    $exception->getMessage()
                )
            );
        }

        $this->assertSame(
            0,
            AccessEventRecord::query()->count()
        );

        Http::assertSentCount(0);
    }

    public function test_it_preserves_the_latest_device_event_time_when_events_arrive_out_of_order(): void
    {
        $device = $this->device(
            'FAC-SIM-ENT-01'
        );

        $generator = app(
            SimulatedAccessEventGenerator::class
        );

        $useCase = app(
            IngestAccessEventUseCase::class
        );

        $newerAt = new DateTimeImmutable(
            '2026-07-15 10:40:00'
        );

        $olderAt = new DateTimeImmutable(
            '2026-07-15 10:38:00'
        );

        $useCase->execute(
            $generator->generate(
                deviceId: $device->id,
                direction: AccessEventDirection::Entry,
                sequence: 6,
                occurredAt: $newerAt,
            )
        );

        $useCase->execute(
            $generator->generate(
                deviceId: $device->id,
                direction: AccessEventDirection::Entry,
                sequence: 7,
                occurredAt: $olderAt,
            )
        );

        $device->refresh();

        $this->assertSame(
            $newerAt->getTimestamp(),
            $device->last_event_at?->getTimestamp()
        );

        $this->assertSame(
            2,
            AccessEventRecord::query()
                ->where(
                    'access_device_id',
                    $device->id
                )
                ->count()
        );

        Http::assertSentCount(0);
    }

    public function test_it_blocks_direct_ingestion_when_the_simulator_is_disabled(): void
    {
        $device = $this->device(
            'FAC-SIM-ENT-01'
        );

        config()->set(
            'access_control.simulator_enabled',
            false
        );

        $command = new IngestAccessEventCommand(
            accessDeviceId: $device->id,
            externalEventId: 'simulator-direct-disabled-001',
            externalPersonId: null,
            direction: AccessEventDirection::Entry,
            occurredAt: new DateTimeImmutable(
                '2026-07-15 10:45:00'
            ),
            payload: [
                'source' => 'simulator',
                'synthetic' => true,
                'sequence' => 8,
                'event_kind' => 'face_recognition',
                'result' => 'unmatched',
            ],
        );

        try {
            app(
                IngestAccessEventUseCase::class
            )->execute($command);

            $this->fail(
                'Era esperado o bloqueio do simulador desativado.'
            );
        } catch (
            IngestAccessEventException $exception
        ) {
            $this->assertStringContainsString(
                'desativado',
                mb_strtolower(
                    $exception->getMessage()
                )
            );
        }

        $this->assertSame(
            0,
            AccessEventRecord::query()->count()
        );

        Http::assertSentCount(0);
    }

    public function test_it_rejects_a_synthetic_event_for_a_non_simulator_device(): void
    {
        $device = $this->device(
            'FAC-SIM-ENT-01'
        );

        $device
            ->forceFill([
                'provider' => 'intelbras',
            ])
            ->saveQuietly();

        $command = app(
            SimulatedAccessEventGenerator::class
        )->generate(
            deviceId: $device->id,
            direction: AccessEventDirection::Entry,
            sequence: 9,
            occurredAt: new DateTimeImmutable(
                '2026-07-15 10:50:00'
            ),
        );

        try {
            app(
                IngestAccessEventUseCase::class
            )->execute($command);

            $this->fail(
                'Era esperado o bloqueio do evento sintético no dispositivo não simulado.'
            );
        } catch (
            IngestAccessEventException $exception
        ) {
            $this->assertStringContainsString(
                'dispositivos simulados',
                mb_strtolower(
                    $exception->getMessage()
                )
            );
        }

        $this->assertSame(
            0,
            AccessEventRecord::query()->count()
        );

        Http::assertSentCount(0);
    }

    public function test_it_requires_a_simulator_source_for_simulated_devices(): void
    {
        $device = $this->device(
            'FAC-SIM-ENT-01'
        );

        $command = new IngestAccessEventCommand(
            accessDeviceId: $device->id,
            externalEventId: 'simulator-missing-source-001',
            externalPersonId: null,
            direction: AccessEventDirection::Entry,
            occurredAt: new DateTimeImmutable(
                '2026-07-15 10:55:00'
            ),
            payload: [
                'synthetic' => true,
                'sequence' => 10,
                'event_kind' => 'face_recognition',
                'result' => 'unmatched',
            ],
        );

        try {
            app(
                IngestAccessEventUseCase::class
            )->execute($command);

            $this->fail(
                'Era esperada a exigência da origem do simulador.'
            );
        } catch (
            IngestAccessEventException $exception
        ) {
            $this->assertStringContainsString(
                'origem',
                mb_strtolower(
                    $exception->getMessage()
                )
            );
        }

        $this->assertSame(
            0,
            AccessEventRecord::query()->count()
        );

        Http::assertSentCount(0);
    }

    private function device(
        string $code
    ): AccessDeviceRecord {
        $this->seed(
            VanguardAccessDeviceDemoSeeder::class
        );

        return AccessDeviceRecord::query()
            ->where('provider', 'simulator')
            ->where('code', $code)
            ->firstOrFail();
    }
}
