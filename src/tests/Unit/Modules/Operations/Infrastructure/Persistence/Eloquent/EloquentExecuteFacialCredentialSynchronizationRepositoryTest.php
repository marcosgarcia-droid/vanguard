<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Infrastructure\Persistence\Eloquent;

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Application\FacialCredentials\Execute\ExecuteFacialCredentialSynchronizationCommand;
use App\Modules\Operations\Application\FacialCredentials\Execute\ExecuteFacialCredentialSynchronizationReason;
use App\Modules\Operations\Application\FacialCredentials\Plan\PlanFacialCredentialSynchronizationCommand;
use App\Modules\Operations\Application\FacialCredentials\Plan\PlanFacialCredentialSynchronizationUseCase;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceConfigurationReadStatus;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceConfigurationSource;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceDirection;
use App\Modules\Operations\Domain\AccessControl\AccessDeviceStatus;
use App\Modules\Operations\Domain\FacialCredentials\FacialCredentialSynchronizationAttemptStatus;
use App\Modules\Operations\Domain\FacialCredentials\FacialCredentialSynchronizationStatus;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoDerivativeStatus;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoSource;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoStatus;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\ConfiguredIntelbrasFacialCredentialSynchronizerResolver;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\DisabledIntelbrasFacialCredentialSynchronizer;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasDeviceModel;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialCompatibilityCatalog;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialCompatibilityProfile;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialCompatibilityResolution;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialDeviceFamily;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialOperation;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialSynchronizer;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFacialCredentialSynchronizerResolver;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\IntelbrasFirmwareVersion;
use App\Modules\Operations\Infrastructure\Integrations\Intelbras\Faces\SimulatedIntelbrasFacialCredentialSynchronizationScenario;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessDeviceConfigurationSnapshotRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessDeviceRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\EloquentExecuteFacialCredentialSynchronizationRepository;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialCredentialSynchronizationRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialPhotoDerivativeRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialPhotoRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class EloquentExecuteFacialCredentialSynchronizationRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_disabled_provider_records_one_blocked_attempt_and_reuses_it(): void
    {
        $fixture = $this->createFixture(
            $this->configuredResolver(
                provider: 'disabled'
            )
        );

        $first = $fixture['repository']->execute(
            $this->command(
                $fixture['synchronization']
            )
        );

        $second = $fixture['repository']->execute(
            $this->command(
                $fixture['synchronization']
            )
        );

        self::assertTrue(
            $first->wasExecuted()
        );

        self::assertTrue(
            $second->wasReused()
        );

        self::assertSame(
            FacialCredentialSynchronizationAttemptStatus::Blocked,
            $first->status
        );

        self::assertSame(
            'provider_disabled',
            $first->failureCode
        );

        self::assertDatabaseCount(
            'facial_credential_sync_attempts',
            1
        );

        self::assertSame(
            FacialCredentialSynchronizationStatus::Blocked,
            $fixture['synchronization']
                ->fresh()
                ?->status
        );
    }

    public function test_successful_simulation_records_succeeded(): void
    {
        $fixture = $this->createFixture(
            $this->configuredResolver(
                provider: 'simulator',
                scenario: SimulatedIntelbrasFacialCredentialSynchronizationScenario::Succeeded
            )
        );

        $result = $fixture['repository']->execute(
            $this->command(
                $fixture['synchronization']
            )
        );

        self::assertTrue(
            $result->wasExecuted()
        );

        self::assertSame(
            FacialCredentialSynchronizationAttemptStatus::Succeeded,
            $result->status
        );

        self::assertSame(
            'simulator',
            $result->provider
        );

        self::assertSame(
            'succeeded',
            $result->scenario
        );

        self::assertNull(
            $result->failureCode
        );

        self::assertSame(
            FacialCredentialSynchronizationStatus::Succeeded,
            $fixture['synchronization']
                ->fresh()
                ?->status
        );
    }

    public function test_duplicate_photo_requires_attention(): void
    {
        $fixture = $this->createFixture(
            $this->configuredResolver(
                provider: 'simulator',
                scenario: SimulatedIntelbrasFacialCredentialSynchronizationScenario::DuplicatePhoto
            )
        );

        $result = $fixture['repository']->execute(
            $this->command(
                $fixture['synchronization']
            )
        );

        self::assertSame(
            FacialCredentialSynchronizationAttemptStatus::RequiresAttention,
            $result->status
        );

        self::assertSame(
            'duplicate_photo',
            $result->failureCode
        );

        self::assertSame(
            FacialCredentialSynchronizationStatus::RequiresAttention,
            $fixture['synchronization']
                ->fresh()
                ?->status
        );
    }

    public function test_failed_simulation_records_failed(): void
    {
        $fixture = $this->createFixture(
            $this->configuredResolver(
                provider: 'simulator',
                scenario: SimulatedIntelbrasFacialCredentialSynchronizationScenario::Failed
            )
        );

        $result = $fixture['repository']->execute(
            $this->command(
                $fixture['synchronization']
            )
        );

        self::assertSame(
            FacialCredentialSynchronizationAttemptStatus::Failed,
            $result->status
        );

        self::assertSame(
            'simulation_failed',
            $result->failureCode
        );

        self::assertSame(
            FacialCredentialSynchronizationStatus::Failed,
            $fixture['synchronization']
                ->fresh()
                ?->status
        );
    }

    public function test_changed_derivative_is_blocked_before_resolving_provider(): void
    {
        $resolver =
            new CountingFacialCredentialSynchronizerResolver;

        $fixture = $this->createFixture(
            $resolver
        );

        $newDerivative =
            $fixture['photo']
                ->derivatives()
                ->create([
                    'tenant_id' => $fixture['tenant']->id,
                    'organization_id' => $fixture['organization']->id,
                    'profile' => 'vanguard_normalized',
                    'policy_version' => 'vanguard-normalization-v2',
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

        $result = $fixture['repository']->execute(
            $this->command(
                $fixture['synchronization']
            )
        );

        self::assertSame(
            FacialCredentialSynchronizationAttemptStatus::Blocked,
            $result->status
        );

        self::assertSame(
            'derivative_changed',
            $result->failureCode
        );

        self::assertSame(
            0,
            $resolver->resolveCalls
        );

        self::assertDatabaseCount(
            'facial_credential_sync_attempts',
            1
        );
    }

    public function test_superseded_intention_creates_no_attempt(): void
    {
        $fixture = $this->createFixture(
            $this->configuredResolver(
                provider: 'disabled'
            )
        );

        $fixture['synchronization']->status =
            FacialCredentialSynchronizationStatus::Superseded;

        $fixture['synchronization']->save();

        $result = $fixture['repository']->execute(
            $this->command(
                $fixture['synchronization']
            )
        );

        self::assertSame(
            ExecuteFacialCredentialSynchronizationReason::SynchronizationSuperseded,
            $result->reason
        );

        self::assertNull(
            $result->attemptNumber
        );

        self::assertDatabaseCount(
            'facial_credential_sync_attempts',
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
     *     synchronization: FacialCredentialSynchronizationRecord,
     *     repository: EloquentExecuteFacialCredentialSynchronizationRepository
     * }
     */
    private function createFixture(
        IntelbrasFacialCredentialSynchronizerResolver $resolver
    ): array {
        $tenant =
            TenantRecord::query()->create([
                'id' => (string) Str::uuid(),
                'name' => 'GRUPO SINTÉTICO B3',
                'status' => 'active',
            ]);

        $organization =
            OrganizationRecord::query()->create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'status' => 'active',
                'legal_name' => 'UNIDADE SINTÉTICA B3 LTDA',
                'display_name' => 'UNIDADE SINTÉTICA B3',
            ]);

        $visitor =
            VisitorRecord::query()->create([
                'tenant_id' => $tenant->id,
                'organization_id' => $organization->id,
                'visitor_code' => 'SYN-B3-001',
                'full_name' => 'VISITANTE SINTÉTICO B3',
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
                    'validation_version' => 'synthetic-b3-v1',
                ]);

        $derivative =
            $photo
                ->derivatives()
                ->create([
                    'tenant_id' => $tenant->id,
                    'organization_id' => $organization->id,
                    'profile' => 'vanguard_normalized',
                    'policy_version' => 'vanguard-normalization-v1',
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
                'code' => 'FAC-SYN-B3-001',
                'name' => 'Facial sintético B3',
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
                    'configuration_hash' => hash(
                        'sha256',
                        'configuration-b3'
                    ),
                    'read_at' => now(),
                    'duration_ms' => 10,
                    'message' => 'Leitura sintética válida para B3.',
                ]);

        $planner =
            new PlanFacialCredentialSynchronizationUseCase(
                $this->compatibleCatalog()
            );

        $planning = $planner->execute(
            new PlanFacialCredentialSynchronizationCommand(
                deviceModel: 'SYNTHETIC-DEVICE',
                firmwareVersion: '20991231',
                operation: IntelbrasFacialCredentialOperation::Register,
                externalUserId: $visitor->id,
                displayName: $visitor->display_name,
                photoSha256: $derivative->sha256,
                photoSizeBytes: $derivative->size_bytes,
                photoWidth: $derivative->width,
                photoHeight: $derivative->height,
                photoMimeType: $derivative->mime_type,
            )
        );

        self::assertTrue(
            $planning->isReady()
        );

        $planFingerprint =
            $planning->planFingerprint();

        self::assertIsString(
            $planFingerprint
        );

        $contextFingerprint =
            $this->contextFingerprint(
                tenantId: $tenant->id,
                organizationId: $organization->id,
                visitorId: $visitor->id,
                photoId: $photo->id,
                derivativeId: $derivative->id,
                deviceId: $device->id,
                operation: IntelbrasFacialCredentialOperation::Register,
                planFingerprint: $planFingerprint,
            );

        $synchronization =
            FacialCredentialSynchronizationRecord::query()
                ->create([
                    'tenant_id' => $tenant->id,
                    'organization_id' => $organization->id,
                    'visitor_id' => $visitor->id,
                    'facial_photo_id' => $photo->id,
                    'facial_photo_derivative_id' => $derivative->id,
                    'access_device_id' => $device->id,
                    'operation' => IntelbrasFacialCredentialOperation::Register
                        ->value,
                    'status' => FacialCredentialSynchronizationStatus::Pending,
                    'version' => 1,
                    'plan_fingerprint' => $planFingerprint,
                    'context_fingerprint' => $contextFingerprint,
                ]);

        return [
            'tenant' => $tenant,
            'organization' => $organization,
            'visitor' => $visitor,
            'photo' => $photo,
            'derivative' => $derivative,
            'device' => $device,
            'snapshot' => $snapshot,
            'synchronization' => $synchronization,
            'repository' => new EloquentExecuteFacialCredentialSynchronizationRepository(
                planner: $planner,
                resolver: $resolver,
            ),
        ];
    }

    private function configuredResolver(
        string $provider,
        ?SimulatedIntelbrasFacialCredentialSynchronizationScenario $scenario = null,
    ): IntelbrasFacialCredentialSynchronizerResolver {
        if ($scenario !== null) {
            config()->set(
                'intelbras_facial_synchronization.simulator.scenario',
                $scenario->value
            );
        }

        return new ConfiguredIntelbrasFacialCredentialSynchronizerResolver(
            environment: 'testing',
            provider: $provider,
            simulatorEnabled: $provider === 'simulator',
            simulatorAllowedEnvironments: [
                'testing',
            ],
            simulatorScenario: $scenario?->value,
        );
    }

    private function compatibleCatalog(): IntelbrasFacialCredentialCompatibilityCatalog
    {
        return new class implements IntelbrasFacialCredentialCompatibilityCatalog
        {
            public function resolve(
                ?string $model,
                ?string $firmware,
            ): IntelbrasFacialCredentialCompatibilityResolution {
                $profile =
                    new IntelbrasFacialCredentialCompatibilityProfile(
                        family: IntelbrasFacialCredentialDeviceFamily::SinglePerson,
                        model: 'SYNTHETIC-DEVICE',
                        firmware: '20991231',
                        maxItems: 1,
                        supportsReplacement: true,
                        requiresDisplayName: true,
                    );

                return IntelbrasFacialCredentialCompatibilityResolution::compatible(
                    model: new IntelbrasDeviceModel(
                        $profile->model
                    ),
                    firmware: new IntelbrasFirmwareVersion(
                        $profile->firmware
                    ),
                    profile: $profile,
                );
            }
        };
    }

    private function contextFingerprint(
        string $tenantId,
        string $organizationId,
        string $visitorId,
        string $photoId,
        string $derivativeId,
        string $deviceId,
        IntelbrasFacialCredentialOperation $operation,
        string $planFingerprint,
    ): string {
        return hash(
            'sha256',
            json_encode(
                [
                    'version' => 1,
                    'tenant_id' => $tenantId,
                    'organization_id' => $organizationId,
                    'visitor_id' => $visitorId,
                    'facial_photo_id' => $photoId,
                    'facial_photo_derivative_id' => $derivativeId,
                    'access_device_id' => $deviceId,
                    'operation' => $operation->value,
                    'plan_fingerprint' => $planFingerprint,
                ],
                JSON_THROW_ON_ERROR
                    | JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
            )
        );
    }

    private function command(
        FacialCredentialSynchronizationRecord $synchronization
    ): ExecuteFacialCredentialSynchronizationCommand {
        return new ExecuteFacialCredentialSynchronizationCommand(
            synchronizationId: $synchronization->id
        );
    }
}

final class CountingFacialCredentialSynchronizerResolver implements IntelbrasFacialCredentialSynchronizerResolver
{
    public int $resolveCalls = 0;

    public function resolve(): IntelbrasFacialCredentialSynchronizer
    {
        $this->resolveCalls++;

        return new DisabledIntelbrasFacialCredentialSynchronizer;
    }
}
