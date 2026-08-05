<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\UI\Filament\Resources\VisitorRecords;

use App\Modules\Operations\Domain\FacialCredentials\FacialCredentialSynchronizationAttemptStatus;
use App\Modules\Operations\Domain\FacialCredentials\FacialCredentialSynchronizationStatus;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoDerivativeStatus;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessDeviceRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialCredentialSynchronizationAttemptRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialCredentialSynchronizationRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialPhotoDerivativeRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialPhotoRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use App\Modules\Operations\UI\Filament\Resources\VisitorRecords\Schemas\VisitorFacialCredentialSynchronizationPresentation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class VisitorFacialCredentialSynchronizationPresentationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set(
            'facial_photos.normalization.default_profile',
            'vanguard_normalized'
        );

        config()->set(
            'facial_photos.normalization.policy_version',
            'vanguard-normalization-v1'
        );
    }

    public function test_it_reports_not_started_without_a_photo(): void
    {
        $visitor = new VisitorRecord([
            'tenant_id' => '33333333-3333-4333-8333-333333333333',
            'organization_id' => '44444444-4444-4444-8444-444444444444',
        ]);

        $visitor->setRelation(
            'latestFacialPhoto',
            null
        );

        $visitor->setRelation(
            'facialCredentialSynchronizations',
            new Collection
        );

        self::assertSame(
            [
                'label' => 'Não iniciada',
                'color' => 'gray',
            ],
            VisitorFacialCredentialSynchronizationPresentation::summary(
                $visitor
            )
        );

        self::assertSame(
            [],
            VisitorFacialCredentialSynchronizationPresentation::details(
                $visitor
            )
        );
    }

    public function test_it_waits_for_a_ready_derivative(): void
    {
        $visitor = $this->visitor(
            photoStatus: FacialPhotoStatus::Approved,
            derivativeStatus: FacialPhotoDerivativeStatus::Processing,
        );

        self::assertSame(
            [
                'label' => 'Preparando foto facial',
                'color' => 'info',
            ],
            VisitorFacialCredentialSynchronizationPresentation::summary(
                $visitor
            )
        );

        self::assertSame(
            [
                'A sincronização somente poderá ser iniciada depois que a foto facial estiver preparada.',
            ],
            VisitorFacialCredentialSynchronizationPresentation::details(
                $visitor
            )
        );
    }

    public function test_it_reports_no_intention_for_the_current_photo(): void
    {
        $visitor = $this->visitor(
            photoStatus: FacialPhotoStatus::Approved,
            derivativeStatus: FacialPhotoDerivativeStatus::Ready,
        );

        self::assertSame(
            [
                'label' => 'Não iniciada',
                'color' => 'gray',
            ],
            VisitorFacialCredentialSynchronizationPresentation::summary(
                $visitor
            )
        );

        self::assertSame(
            [
                'Nenhuma intenção de sincronização foi criada para a foto facial atual.',
            ],
            VisitorFacialCredentialSynchronizationPresentation::details(
                $visitor
            )
        );
    }

    public function test_it_presents_a_pending_intention(): void
    {
        $synchronization =
            $this->synchronization(
                status: FacialCredentialSynchronizationStatus::Pending,
            );

        $visitor = $this->visitor(
            photoStatus: FacialPhotoStatus::Approved,
            derivativeStatus: FacialPhotoDerivativeStatus::Ready,
            synchronizations: [
                $synchronization,
            ],
        );

        self::assertSame(
            [
                'label' => 'Aguardando sincronização',
                'color' => 'warning',
            ],
            VisitorFacialCredentialSynchronizationPresentation::summary(
                $visitor
            )
        );

        $details =
            VisitorFacialCredentialSynchronizationPresentation::details(
                $visitor
            );

        self::assertCount(
            1,
            $details
        );

        self::assertStringContainsString(
            'FAC-001 - LEITOR PRINCIPAL',
            $details[0]
        );

        self::assertStringContainsString(
            'Pendente',
            $details[0]
        );

        self::assertStringContainsString(
            'Cadastro',
            $details[0]
        );
    }

    public function test_it_consolidates_success_for_multiple_devices(): void
    {
        $visitor = $this->visitor(
            photoStatus: FacialPhotoStatus::Approved,
            derivativeStatus: FacialPhotoDerivativeStatus::Ready,
            synchronizations: [
                $this->synchronization(
                    status: FacialCredentialSynchronizationStatus::Succeeded,
                    deviceId: 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
                    code: 'FAC-001',
                ),

                $this->synchronization(
                    status: FacialCredentialSynchronizationStatus::Succeeded,
                    deviceId: 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
                    code: 'FAC-002',
                ),
            ],
        );

        self::assertSame(
            [
                'label' => 'Sincronizada em 2 dispositivos',
                'color' => 'success',
            ],
            VisitorFacialCredentialSynchronizationPresentation::summary(
                $visitor
            )
        );
    }

    public function test_it_uses_only_the_current_context_and_latest_version(): void
    {
        $oldVersion =
            $this->synchronization(
                status: FacialCredentialSynchronizationStatus::Succeeded,
                version: 1,
            );

        $latestVersion =
            $this->synchronization(
                status: FacialCredentialSynchronizationStatus::Blocked,
                version: 2,
            );

        $staleContext =
            $this->synchronization(
                status: FacialCredentialSynchronizationStatus::RequiresAttention,
                photoId: '99999999-9999-4999-8999-999999999999',
                derivativeId: '88888888-8888-4888-8888-888888888888',
                deviceId: 'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
            );

        $visitor = $this->visitor(
            photoStatus: FacialPhotoStatus::Approved,
            derivativeStatus: FacialPhotoDerivativeStatus::Ready,
            synchronizations: [
                $oldVersion,
                $latestVersion,
                $staleContext,
            ],
        );

        self::assertSame(
            [
                'label' => 'Sincronização bloqueada',
                'color' => 'warning',
            ],
            VisitorFacialCredentialSynchronizationPresentation::summary(
                $visitor
            )
        );

        $details =
            VisitorFacialCredentialSynchronizationPresentation::details(
                $visitor
            );

        self::assertCount(
            1,
            $details
        );

        self::assertStringContainsString(
            'versão 2',
            $details[0]
        );
    }

    public function test_same_device_with_multiple_operations_is_counted_once(): void
    {
        $visitor = $this->visitor(
            photoStatus: FacialPhotoStatus::Approved,
            derivativeStatus: FacialPhotoDerivativeStatus::Ready,
            synchronizations: [
                $this->synchronization(
                    status: FacialCredentialSynchronizationStatus::Succeeded,
                    operation: 'register',
                ),

                $this->synchronization(
                    status: FacialCredentialSynchronizationStatus::Succeeded,
                    version: 2,
                    operation: 'replace',
                ),
            ],
        );

        self::assertSame(
            [
                'label' => 'Sincronizada',
                'color' => 'success',
            ],
            VisitorFacialCredentialSynchronizationPresentation::summary(
                $visitor
            )
        );

        self::assertCount(
            2,
            VisitorFacialCredentialSynchronizationPresentation::details(
                $visitor
            )
        );
    }

    public function test_it_ignores_a_synchronization_from_another_scope(): void
    {
        $visitor = $this->visitor(
            photoStatus: FacialPhotoStatus::Approved,
            derivativeStatus: FacialPhotoDerivativeStatus::Ready,
            synchronizations: [
                $this->synchronization(
                    status: FacialCredentialSynchronizationStatus::Pending,
                ),

                $this->synchronization(
                    status: FacialCredentialSynchronizationStatus::RequiresAttention,
                    deviceId: 'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
                    code: 'FAC-OUTRA-UNIDADE',
                    organizationId: '99999999-9999-4999-8999-999999999999',
                ),
            ],
        );

        self::assertSame(
            [
                'label' => 'Aguardando sincronização',
                'color' => 'warning',
            ],
            VisitorFacialCredentialSynchronizationPresentation::summary(
                $visitor
            )
        );

        $details =
            VisitorFacialCredentialSynchronizationPresentation::details(
                $visitor
            );

        self::assertCount(
            1,
            $details
        );

        self::assertStringNotContainsString(
            'FAC-OUTRA-UNIDADE',
            $details[0]
        );
    }

    public function test_it_presents_safe_attempt_information(): void
    {
        $attempt =
            new FacialCredentialSynchronizationAttemptRecord([
                'attempt_number' => 1,
                'status' => FacialCredentialSynchronizationAttemptStatus::Blocked,
                'provider' => 'disabled',
                'failure_code' => 'provider_disabled',
                'message' => 'A sincronização facial permanece desativada.',
                'started_at' => Carbon::parse(
                    '2026-08-05 15:30:00'
                ),
                'completed_at' => Carbon::parse(
                    '2026-08-05 15:30:01'
                ),
                'plan_fingerprint' => str_repeat('a', 64),
            ]);

        $synchronization =
            $this->synchronization(
                status: FacialCredentialSynchronizationStatus::Blocked,
                attempt: $attempt,
            );

        $visitor = $this->visitor(
            photoStatus: FacialPhotoStatus::Approved,
            derivativeStatus: FacialPhotoDerivativeStatus::Ready,
            synchronizations: [
                $synchronization,
            ],
        );

        $serialized = implode(
            ' ',
            VisitorFacialCredentialSynchronizationPresentation::details(
                $visitor
            )
        );

        self::assertStringContainsString(
            'origem desativada',
            $serialized
        );

        self::assertStringContainsString(
            '05/08/2026 15:30:01',
            $serialized
        );

        self::assertStringContainsString(
            'A sincronização facial permanece desativada.',
            $serialized
        );

        self::assertStringNotContainsString(
            'provider_disabled',
            $serialized
        );

        self::assertStringNotContainsString(
            str_repeat('a', 64),
            $serialized
        );

        self::assertStringNotContainsString(
            'fingerprint',
            strtolower($serialized)
        );
    }

    public function test_it_integrates_the_read_only_presentation_into_the_ui(): void
    {
        $infolist = file_get_contents(
            base_path(
                'app/Modules/Operations/UI/Filament/'
                .'Resources/VisitorRecords/Schemas/'
                .'VisitorRecordInfolist.php'
            )
        );

        $table = file_get_contents(
            base_path(
                'app/Modules/Operations/UI/Filament/'
                .'Resources/VisitorRecords/Tables/'
                .'VisitorRecordsTable.php'
            )
        );

        self::assertIsString(
            $infolist
        );

        self::assertIsString(
            $table
        );

        self::assertStringContainsString(
            "TextEntry::make('facial_credential_synchronization_status')",
            $infolist
        );

        self::assertStringContainsString(
            "TextEntry::make('facial_credential_synchronization_details')",
            $infolist
        );

        self::assertStringContainsString(
            "TextColumn::make('facial_credential_synchronization_status')",
            $table
        );

        self::assertStringContainsString(
            "'facialCredentialSynchronizations.accessDevice'",
            $table
        );

        self::assertStringContainsString(
            "'facialCredentialSynchronizations.latestAttempt'",
            $table
        );

        foreach (
            [
                'plan_fingerprint',
                'context_fingerprint',
                'failure_code',
                'source_sha256',
                'sha256',
            ] as $fragment
        ) {
            self::assertStringNotContainsString(
                $fragment,
                $infolist
            );
        }
    }

    /**
     * @param  list<FacialCredentialSynchronizationRecord>  $synchronizations
     */
    private function visitor(
        FacialPhotoStatus $photoStatus,
        FacialPhotoDerivativeStatus $derivativeStatus,
        array $synchronizations = [],
    ): VisitorRecord {
        $photo = new FacialPhotoRecord([
            'id' => '11111111-1111-4111-8111-111111111111',
            'status' => $photoStatus,
            'sha256' => str_repeat('1', 64),
        ]);

        $derivative =
            new FacialPhotoDerivativeRecord([
                'id' => '22222222-2222-4222-8222-222222222222',
                'profile' => 'vanguard_normalized',
                'policy_version' => 'vanguard-normalization-v1',
                'status' => $derivativeStatus,
                'source_sha256' => str_repeat('1', 64),
            ]);

        $derivative->created_at =
            Carbon::parse(
                '2026-08-05 15:00:00'
            );

        $photo->setRelation(
            'derivatives',
            new Collection([
                $derivative,
            ])
        );

        $visitor = new VisitorRecord;

        $visitor->setAttribute(
            'tenant_id',
            '33333333-3333-4333-8333-333333333333'
        );

        $visitor->setAttribute(
            'organization_id',
            '44444444-4444-4444-8444-444444444444'
        );

        $visitor->setRelation(
            'latestFacialPhoto',
            $photo
        );

        $visitor->setRelation(
            'facialCredentialSynchronizations',
            new Collection(
                $synchronizations
            )
        );

        return $visitor;
    }

    private function synchronization(
        FacialCredentialSynchronizationStatus $status,
        int $version = 1,
        string $photoId =
            '11111111-1111-4111-8111-111111111111',
        string $derivativeId =
            '22222222-2222-4222-8222-222222222222',
        string $deviceId =
            'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
        string $code = 'FAC-001',
        string $operation = 'register',
        string $organizationId =
            '44444444-4444-4444-8444-444444444444',
        ?FacialCredentialSynchronizationAttemptRecord $attempt = null,
    ): FacialCredentialSynchronizationRecord {
        $record =
            new FacialCredentialSynchronizationRecord([
                'tenant_id' => '33333333-3333-4333-8333-333333333333',
                'organization_id' => $organizationId,
                'id' => (string) fake()->uuid(),
                'facial_photo_id' => $photoId,
                'facial_photo_derivative_id' => $derivativeId,
                'access_device_id' => $deviceId,
                'operation' => $operation,
                'status' => $status,
                'version' => $version,
            ]);

        $record->setAttribute(
            'tenant_id',
            '33333333-3333-4333-8333-333333333333'
        );

        $record->setAttribute(
            'organization_id',
            $organizationId
        );

        $record->created_at =
            Carbon::parse(
                sprintf(
                    '2026-08-05 15:%02d:00',
                    $version
                )
            );

        $record->setRelation(
            'accessDevice',
            new AccessDeviceRecord([
                'tenant_id' => '33333333-3333-4333-8333-333333333333',
                'organization_id' => $organizationId,
                'id' => $deviceId,
                'code' => $code,
                'name' => 'LEITOR PRINCIPAL',
                'model' => 'SS 3532 MF',
            ])
        );

        $device = $record->getRelation(
            'accessDevice'
        );

        if (! $device instanceof AccessDeviceRecord) {
            throw new \RuntimeException(
                'Dispositivo sintético não disponível.'
            );
        }

        $device->setAttribute(
            'tenant_id',
            '33333333-3333-4333-8333-333333333333'
        );

        $device->setAttribute(
            'organization_id',
            $organizationId
        );

        $record->setRelation(
            'latestAttempt',
            $attempt
        );

        return $record;
    }
}
