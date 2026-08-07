<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\UI\Filament\Resources\VisitorRecords;

use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoDerivativeStatus;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialPhotoDerivativeRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialPhotoRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use App\Modules\Operations\UI\Filament\Resources\VisitorRecords\Actions\CreateVisitorFacialCredentialSynchronizationAction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Tests\TestCase;

final class CreateVisitorFacialCredentialSynchronizationActionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set(
            'facial_photos.intelbras_derivative.profile',
            'intelbras_facial_credential'
        );

        config()->set(
            'facial_photos.intelbras_derivative.policy_version',
            'intelbras-facial-credential-v1'
        );
    }

    public function test_it_requires_an_active_visitor_with_an_approved_current_photo_and_ready_derivative(): void
    {
        self::assertFalse(
            CreateVisitorFacialCredentialSynchronizationAction::isEligibleRecord(
                $this->visitorWithoutPhoto()
            )
        );

        self::assertFalse(
            CreateVisitorFacialCredentialSynchronizationAction::isEligibleRecord(
                $this->visitorWithPhoto(
                    photoStatus: FacialPhotoStatus::PendingValidation,
                    derivativeStatus: FacialPhotoDerivativeStatus::Ready,
                )
            )
        );

        self::assertFalse(
            CreateVisitorFacialCredentialSynchronizationAction::isEligibleRecord(
                $this->visitorWithPhoto(
                    photoStatus: FacialPhotoStatus::Approved,
                    derivativeStatus: FacialPhotoDerivativeStatus::Processing,
                )
            )
        );

        self::assertTrue(
            CreateVisitorFacialCredentialSynchronizationAction::isEligibleRecord(
                $this->visitorWithPhoto(
                    photoStatus: FacialPhotoStatus::Approved,
                    derivativeStatus: FacialPhotoDerivativeStatus::Ready,
                )
            )
        );

        $inactive =
            $this->visitorWithPhoto(
                photoStatus: FacialPhotoStatus::Approved,
                derivativeStatus: FacialPhotoDerivativeStatus::Ready,
            );

        $inactive->status = 'inactive';

        self::assertFalse(
            CreateVisitorFacialCredentialSynchronizationAction::isEligibleRecord(
                $inactive
            )
        );
    }

    public function test_the_action_is_manual_confirmed_and_creation_only(): void
    {
        $source = file_get_contents(
            base_path(
                'app/Modules/Operations/UI/Filament/'
                .'Resources/VisitorRecords/Actions/'
                .'CreateVisitorFacialCredentialSynchronizationAction.php'
            )
        );

        self::assertIsString($source);

        self::assertStringContainsString(
            "'Preparar sincronização facial'",
            $source
        );

        self::assertStringContainsString(
            '->requiresConfirmation()',
            $source
        );

        self::assertStringContainsString(
            'Gate::authorize(',
            $source
        );

        self::assertStringContainsString(
            "'createFacialCredentialSynchronization'",
            $source
        );

        self::assertStringContainsString(
            'CreateFacialCredentialSynchronizationUseCase::class',
            $source
        );

        self::assertStringContainsString(
            'Nenhuma imagem será enviada',
            $source
        );

        self::assertStringNotContainsString(
            'ExecuteFacialCredentialSynchronizationUseCase',
            $source
        );

        foreach (
            [
                'Http::',
                'Queue::',
                'Bus::',
                'Storage::',
                'dispatch(',
                'dispatchSync(',
                'curl_',
                'file_get_contents(',
                'base64_encode(',
                'base64_decode(',
            ] as $prohibited
        ) {
            self::assertStringNotContainsString(
                $prohibited,
                $source
            );
        }
    }

    public function test_the_action_does_not_reference_credentials_or_expose_sensitive_fingerprints(): void
    {
        $source = file_get_contents(
            base_path(
                'app/Modules/Operations/UI/Filament/'
                .'Resources/VisitorRecords/Actions/'
                .'CreateVisitorFacialCredentialSynchronizationAction.php'
            )
        );

        self::assertIsString($source);

        foreach (
            [
                'credential_username',
                'credential_password',
                'plan_fingerprint',
                'context_fingerprint',
                'raw_payload',
            ] as $sensitive
        ) {
            self::assertStringNotContainsString(
                $sensitive,
                $source
            );
        }

        self::assertSame(
            1,
            substr_count(
                $source,
                'source_sha256'
            )
        );

        self::assertStringContainsString(
            '$candidate->source_sha256',
            $source
        );

    }

    private function visitorWithoutPhoto(): VisitorRecord
    {
        $visitor = new VisitorRecord([
            'status' => 'active',
        ]);

        $visitor->setRelation(
            'latestFacialPhoto',
            null
        );

        return $visitor;
    }

    private function visitorWithPhoto(
        FacialPhotoStatus $photoStatus,
        FacialPhotoDerivativeStatus $derivativeStatus,
    ): VisitorRecord {
        $sourceSha256 = str_repeat(
            'a',
            64
        );

        $photo = new FacialPhotoRecord([
            'id' => (string) Str::uuid(),
            'status' => $photoStatus,
            'sha256' => $sourceSha256,
        ]);

        $derivative =
            new FacialPhotoDerivativeRecord([
                'id' => (string) Str::uuid(),
                'profile' => 'intelbras_facial_credential',
                'policy_version' => 'intelbras-facial-credential-v1',
                'status' => $derivativeStatus,
                'source_sha256' => $sourceSha256,
                'sha256' => str_repeat('b', 64),
                'width' => 800,
                'height' => 1000,
                'mime_type' => 'image/jpeg',
                'size_bytes' => 30_000,
            ]);

        $derivative->generated_at = now();

        $photo->setRelation(
            'derivatives',
            new Collection([
                $derivative,
            ])
        );

        $visitor = new VisitorRecord([
            'status' => 'active',
        ]);

        $visitor->setRelation(
            'latestFacialPhoto',
            $photo
        );

        return $visitor;
    }
}
