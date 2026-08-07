<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Infrastructure\Persistence\Eloquent;

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Application\FacialCredentials\Create\CreateFacialCredentialSynchronizationCommand;
use App\Modules\Operations\Application\FacialCredentials\Create\CreateFacialCredentialSynchronizationReason;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceConfigurationReadStatus;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceConfigurationSource;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceDirection;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceStatus;
use App\Modules\Operations\Domain\FacialCredentials\FacialCredentialSubjectType;
use App\Modules\Operations\Domain\FacialCredentials\FacialCredentialSynchronizationStatus;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoDerivativeStatus;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoSource;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoStatus;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialOperation;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessDeviceConfigurationSnapshotRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessDeviceRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\EloquentCreateFacialCredentialSynchronizationRepository;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialCredentialSynchronizationRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialPhotoDerivativeRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialPhotoRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class EloquentCreateFacialCredentialSynchronizationRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_prepares_a_safe_current_context(): void
    {
        $fixture = $this->createFixture();

        $preparation = $fixture['repository']->prepare(
            $this->command(
                $fixture['visitor'],
                $fixture['device']
            )
        );

        self::assertTrue(
            $preparation->isReady()
        );

        self::assertSame(
            FacialCredentialSubjectType::Visitor,
            $preparation->context?->subjectType
        );

        self::assertSame(
            $fixture['visitor']->id,
            $preparation->context?->subjectId
        );

        self::assertSame(
            $fixture['visitor']->id,
            $preparation->context?->externalUserId
        );

        self::assertSame(
            $fixture['derivative']->id,
            $preparation->context
                ?->facialPhotoDerivativeId
        );

        self::assertSame(
            'SYNTHETIC-DEVICE',
            $preparation->context?->deviceModel
        );

        self::assertSame(
            '20991231',
            $preparation->context?->firmwareVersion
        );
    }

    public function test_same_context_is_created_once_and_then_reused(): void
    {
        $fixture = $this->createFixture();

        $preparation = $fixture['repository']->prepare(
            $this->command(
                $fixture['visitor'],
                $fixture['device']
            )
        );

        $context = $preparation->context;

        self::assertNotNull($context);

        $planFingerprint = hash(
            'sha256',
            'plan-1'
        );

        $contextFingerprint = hash(
            'sha256',
            'context-1'
        );

        $first = $fixture['repository']->persist(
            context: $context,
            operation: IntelbrasFacialCredentialOperation::Register,
            planFingerprint: $planFingerprint,
            contextFingerprint: $contextFingerprint,
        );

        $second = $fixture['repository']->persist(
            context: $context,
            operation: IntelbrasFacialCredentialOperation::Register,
            planFingerprint: $planFingerprint,
            contextFingerprint: $contextFingerprint,
        );

        self::assertTrue($first->wasCreated());
        self::assertTrue($second->wasReused());

        self::assertSame(
            $first->synchronizationId,
            $second->synchronizationId
        );

        self::assertSame(1, $first->version);
        self::assertSame(1, $second->version);

        self::assertDatabaseCount(
            'facial_credential_syncs',
            1
        );

        self::assertDatabaseCount(
            'facial_credential_sync_attempts',
            0
        );
    }

    public function test_new_derivative_creates_a_new_version(): void
    {
        $fixture = $this->createFixture();

        $firstPreparation =
            $fixture['repository']->prepare(
                $this->command(
                    $fixture['visitor'],
                    $fixture['device']
                )
            );

        $firstContext =
            $firstPreparation->context;

        self::assertNotNull($firstContext);

        $first = $fixture['repository']->persist(
            context: $firstContext,
            operation: IntelbrasFacialCredentialOperation::Register,
            planFingerprint: hash('sha256', 'plan-1'),
            contextFingerprint: hash('sha256', 'context-1'),
        );

        $newDerivative =
            $fixture['photo']
                ->derivatives()
                ->create([
                    'tenant_id' => $fixture['tenant']->id,
                    'organization_id' => $fixture['organization']->id,
                    'profile' => 'intelbras_facial_credential',
                    'policy_version' => 'intelbras-facial-credential-v2',
                    'status' => FacialPhotoDerivativeStatus::Ready,
                    'source_sha256' => $fixture['photo']->sha256,
                    'width' => 500,
                    'height' => 800,
                    'mime_type' => 'image/jpeg',
                    'size_bytes' => 49_000,
                    'sha256' => str_repeat('b', 64),
                    'generated_at' => now()->addSecond(),
                ]);

        self::assertInstanceOf(
            FacialPhotoDerivativeRecord::class,
            $newDerivative
        );

        config()->set(

            'facial_photos.intelbras_derivative.policy_version',

            'intelbras-facial-credential-v2'

        );

        $secondPreparation =
            $fixture['repository']->prepare(
                $this->command(
                    $fixture['visitor'],
                    $fixture['device']
                )
            );

        $secondContext =
            $secondPreparation->context;

        self::assertNotNull($secondContext);

        self::assertSame(
            $newDerivative->id,
            $secondContext->facialPhotoDerivativeId
        );

        $second = $fixture['repository']->persist(
            context: $secondContext,
            operation: IntelbrasFacialCredentialOperation::Register,
            planFingerprint: hash('sha256', 'plan-2'),
            contextFingerprint: hash('sha256', 'context-2'),
        );

        self::assertTrue($first->wasCreated());
        self::assertTrue($second->wasCreated());
        self::assertSame(1, $first->version);
        self::assertSame(2, $second->version);

        $firstRecord =
            FacialCredentialSynchronizationRecord::query()
                ->findOrFail(
                    $first->synchronizationId
                );

        self::assertSame(
            FacialCredentialSynchronizationStatus::Superseded,
            $firstRecord->status
        );

        self::assertDatabaseCount(
            'facial_credential_syncs',
            2
        );

        self::assertDatabaseCount(
            'facial_credential_sync_attempts',
            0
        );
    }

    public function test_scope_mismatch_is_blocked(): void
    {
        $fixture = $this->createFixture();

        $otherOrganization =
            OrganizationRecord::query()->create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $fixture['tenant']->id,
                'status' => 'active',
                'legal_name' => 'OUTRA UNIDADE SINTÉTICA LTDA',
                'display_name' => 'OUTRA UNIDADE SINTÉTICA',
            ]);

        $fixture['device']->forceFill([
            'organization_id' => $otherOrganization->id,
        ])->saveQuietly();

        $preparation = $fixture['repository']->prepare(
            $this->command(
                $fixture['visitor'],
                $fixture['device']
            )
        );

        self::assertSame(
            CreateFacialCredentialSynchronizationReason::ScopeMismatch,
            $preparation->reason
        );
    }

    public function test_stale_snapshot_context_is_not_persisted(): void
    {
        $fixture = $this->createFixture();

        $preparation = $fixture['repository']->prepare(
            $this->command(
                $fixture['visitor'],
                $fixture['device']
            )
        );

        $context = $preparation->context;

        self::assertNotNull($context);

        AccessDeviceConfigurationSnapshotRecord::query()
            ->create([
                'access_device_id' => $fixture['device']->id,
                'tenant_id' => $fixture['tenant']->id,
                'organization_id' => $fixture['organization']->id,
                'source' => AccessDeviceConfigurationSource::cases()[0],
                'status' => AccessDeviceConfigurationReadStatus::Success,
                'device_model' => 'SYNTHETIC-DEVICE',
                'firmware_version' => '20991232',
                'configuration' => [],
                'capabilities' => [],
                'sanitized_response' => [],
                'configuration_hash' => hash('sha256', 'configuration-2'),
                'read_at' => now()->addSecond(),
                'duration_ms' => 10,
                'message' => 'Leitura sintética mais recente.',
            ]);

        $result = $fixture['repository']->persist(
            context: $context,
            operation: IntelbrasFacialCredentialOperation::Register,
            planFingerprint: hash('sha256', 'plan-stale'),
            contextFingerprint: hash('sha256', 'context-stale'),
        );

        self::assertSame(
            CreateFacialCredentialSynchronizationReason::ContextChanged,
            $result->reason
        );

        self::assertDatabaseCount(
            'facial_credential_syncs',
            0
        );
    }

    /**
     * @return array{
     *     tenant: TenantRecord,
     *     organization: OrganizationRecord,
     *     visitor: VisitorRecord,
     *     photo: FacialPhotoRecord,
     *     derivative: FacialPhotoDerivativeRecord,
     *     device: AccessDeviceRecord,
     *     snapshot: AccessDeviceConfigurationSnapshotRecord,
     *     repository: EloquentCreateFacialCredentialSynchronizationRepository
     * }
     */
    private function createFixture(): array
    {
        $tenant = TenantRecord::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'GRUPO SINTÉTICO',
            'status' => 'active',
        ]);

        $organization =
            OrganizationRecord::query()->create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'status' => 'active',
                'legal_name' => 'UNIDADE SINTÉTICA LTDA',
                'display_name' => 'UNIDADE SINTÉTICA',
            ]);

        $visitor =
            VisitorRecord::query()->create([
                'tenant_id' => $tenant->id,
                'organization_id' => $organization->id,
                'visitor_code' => 'SYN-001',
                'full_name' => 'VISITANTE SINTÉTICO',
                'status' => 'active',
            ]);

        $photo =
            $visitor
                ->facialPhotos()
                ->create([
                    'tenant_id' => $tenant->id,
                    'organization_id' => $organization->id,
                    'source' => FacialPhotoSource::cases()[0],
                    'status' => FacialPhotoStatus::Approved,
                    'captured_at' => now()->subMinute(),
                    'analyzed_at' => now()->subMinute(),
                    'approved_at' => now()->subMinute(),
                    'width' => 600,
                    'height' => 900,
                    'mime_type' => 'image/jpeg',
                    'size_bytes' => 80_000,
                    'sha256' => str_repeat('1', 64),
                    'validation_version' => 'synthetic-v1',
                ]);

        $derivative =
            $photo
                ->derivatives()
                ->create([
                    'tenant_id' => $tenant->id,
                    'organization_id' => $organization->id,
                    'profile' => 'intelbras_facial_credential',
                    'policy_version' => 'intelbras-facial-credential-v1',
                    'status' => FacialPhotoDerivativeStatus::Ready,
                    'source_sha256' => $photo->sha256,
                    'width' => 500,
                    'height' => 800,
                    'mime_type' => 'image/jpeg',
                    'size_bytes' => 50_000,
                    'sha256' => str_repeat('a', 64),
                    'generated_at' => now(),
                ]);

        $device =
            AccessDeviceRecord::query()->create([
                'tenant_id' => $tenant->id,
                'organization_id' => $organization->id,
                'code' => 'FAC-SYN-001',
                'name' => 'Facial sintético 001',
                'device_type' => 'facial_reader',
                'provider' => 'intelbras',
                'model' => 'SYNTHETIC-DEVICE',
                'direction' => AccessDeviceDirection::cases()[0],
                'status' => AccessDeviceStatus::Active,
            ]);

        $snapshot =
            AccessDeviceConfigurationSnapshotRecord::query()
                ->create([
                    'access_device_id' => $device->id,
                    'tenant_id' => $tenant->id,
                    'organization_id' => $organization->id,
                    'source' => AccessDeviceConfigurationSource::cases()[0],
                    'status' => AccessDeviceConfigurationReadStatus::Success,
                    'device_model' => 'SYNTHETIC-DEVICE',
                    'firmware_version' => '20991231',
                    'configuration' => [],
                    'capabilities' => [],
                    'sanitized_response' => [],
                    'configuration_hash' => hash('sha256', 'configuration-1'),
                    'read_at' => now(),
                    'duration_ms' => 10,
                    'message' => 'Leitura sintética válida.',
                ]);

        return [
            'tenant' => $tenant,
            'organization' => $organization,
            'visitor' => $visitor,
            'photo' => $photo,
            'derivative' => $derivative,
            'device' => $device,
            'snapshot' => $snapshot,
            'repository' => new EloquentCreateFacialCredentialSynchronizationRepository,
        ];
    }

    private function command(
        VisitorRecord $visitor,
        AccessDeviceRecord $device,
    ): CreateFacialCredentialSynchronizationCommand {
        return new CreateFacialCredentialSynchronizationCommand(
            subjectType: FacialCredentialSubjectType::Visitor,
            subjectId: $visitor->id,
            accessDeviceId: $device->id,
            operation: IntelbrasFacialCredentialOperation::Register,
        );
    }
}
