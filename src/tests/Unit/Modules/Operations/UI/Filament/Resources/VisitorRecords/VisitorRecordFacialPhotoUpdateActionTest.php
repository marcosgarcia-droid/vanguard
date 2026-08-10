<?php

namespace Tests\Unit\Modules\Operations\UI\Filament\Resources\VisitorRecords;

use App\Models\User;
use App\Modules\Identity\Application\Tenancy\TenantContext;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Application\FacialPhotos\Preview\Receipts\FacialPhotoPreviewReceipt;
use App\Modules\Operations\Application\FacialPhotos\Preview\Receipts\FacialPhotoPreviewReceiptCodec;
use App\Modules\Operations\Application\FacialPhotos\Registration\RegisterFacialPhotoRepository;
use App\Modules\Operations\Application\FacialPhotos\Registration\RegisterVisitorFacialPhotoException;
use App\Modules\Operations\Application\FacialPhotos\TechnicalAnalysis\AnalyzeFacialPhotoUseCase;
use App\Modules\Operations\Application\FacialPhotos\TechnicalAnalysis\FacialPhotoTechnicalAnalyzer;
use App\Modules\Operations\Application\FacialPhotos\Validation\FacialPhotoValidator;
use App\Modules\Operations\Application\FacialPhotos\Validation\Resolution\FacialPhotoValidatorResolver;
use App\Modules\Operations\Application\FacialPhotos\Validation\Resolution\FacialPhotoValidatorSelection;
use App\Modules\Operations\Application\FacialPhotos\Validation\Schedule\FacialPhotoValidationAfterCommitScheduler;
use App\Modules\Operations\Application\FacialPhotos\Validation\Schedule\ScheduleFacialPhotoValidationCommand;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoPreviewDecision;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoSource;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoStatus;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoTechnicalAnalysis;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoValidationDecision;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoValidationResult;
use App\Modules\Operations\Domain\Visitors\VisitorStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\EloquentRegisterFacialPhotoRepository;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialPhotoRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use App\Modules\Operations\Infrastructure\Storage\FacialPhotoMediaCleanup;
use App\Modules\Operations\Infrastructure\Storage\VisitorFacialPhotoCaptureRegistrar;
use App\Modules\Operations\UI\Filament\Resources\VisitorRecords\Actions\UpdateVisitorFacialPhotoAction;
use App\Modules\Operations\UI\Filament\Resources\VisitorRecords\Pages\ListVisitorRecords;
use Filament\Actions\Testing\TestAction;
use GdImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;
use Throwable;

final class VisitorRecordFacialPhotoUpdateActionTest extends TestCase
{
    use RefreshDatabase;

    private const CONFIRMATION_KEY =
        'cccccccccccccccccccccccccccccccc'
        .'cccccccccccccccccccccccccccccccc';

    private const CONFIRMATION_CONTEXT =
        'visitor.update.test.photo_capture';

    private string $imageDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();

        app(TenantContext::class)
            ->clearSelectedTenant();

        Storage::fake('local');
        Storage::fake('facial_photos');

        $this->bindApprovedFacialValidator();

        $this->imageDirectory = storage_path(
            'framework/testing/'
            .'visitor-update-action-facial-photo'
        );

        File::deleteDirectory(
            $this->imageDirectory
        );

        File::ensureDirectoryExists(
            $this->imageDirectory
        );
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(
            $this->imageDirectory
        );

        app(TenantContext::class)
            ->clearSelectedTenant();

        parent::tearDown();
    }

    public function test_it_builds_the_update_facial_photo_action(): void
    {
        $action = UpdateVisitorFacialPhotoAction::make();

        $this->assertSame(
            'updateFacialPhoto',
            $action->getName()
        );
    }

    public function test_it_uses_the_official_capture_and_registration_flow(): void
    {
        $source = $this->source(
            'app/Modules/Operations/UI/Filament/Resources/'
            .'VisitorRecords/Actions/'
            .'UpdateVisitorFacialPhotoAction.php'
        );

        foreach (
            [
                "Action::make('updateFacialPhoto')",
                "->label('Atualizar foto facial')",
                "Hidden::make('photo_capture_receipt')",
                "FacialPhotoCapture::make('photo_capture')",
                '->confirmationContext(',
                'ConfirmFacialPhotoPreviewUseCase::class',
                'new ConfirmFacialPhotoPreviewCommand(',
                "'photo_capture_receipt'",
                "'visitor.update.'",
                '->required()',
                '->databaseTransaction()',
                'Gate::authorize(',
                "'update'",
                'VisitorFacialPhotoCaptureRegistrar::class',
                '->register(',
                'expectedSha256: $confirmation->fingerprint',
                'createdBy: $createdBy',
                'confirmationKey: $confirmation->confirmationKey',
                'confirmationContext: $confirmation->confirmationContext',
                '->successNotificationTitle(',
                "'Foto facial atualizada'",
            ] as $expected
        ) {
            $this->assertStringContainsString(
                $expected,
                $source
            );
        }

        $this->assertStringNotContainsString(
            'forceFill([',
            $source
        );

        $this->assertStringNotContainsString(
            "'photo_path' =>",
            $source
        );
    }

    public function test_it_blocks_deleted_or_unauthorized_records(): void
    {
        $source = $this->source(
            'app/Modules/Operations/UI/Filament/Resources/'
            .'VisitorRecords/Actions/'
            .'UpdateVisitorFacialPhotoAction.php'
        );

        $this->assertStringContainsString(
            '! $record->trashed()',
            $source
        );

        $this->assertStringContainsString(
            'auth()->user()?->can(',
            $source
        );

        $this->assertStringContainsString(
            "'Não é possível atualizar a foto '",
            $source
        );

        $this->assertStringContainsString(
            "'Capture ou selecione uma nova foto facial.'",
            $source
        );
    }

    public function test_it_is_available_in_the_visitor_table(): void
    {
        $table = $this->source(
            'app/Modules/Operations/UI/Filament/Resources/'
            .'VisitorRecords/Tables/VisitorRecordsTable.php'
        );

        $this->assertStringContainsString(
            'use App\\Modules\\Operations\\UI\\Filament\\'
            .'Resources\\VisitorRecords\\Actions\\'
            .'UpdateVisitorFacialPhotoAction;',
            $table
        );

        $this->assertStringContainsString(
            'UpdateVisitorFacialPhotoAction::make()',
            $table
        );
    }

    public function test_it_removes_the_legacy_direct_edit_upload(): void
    {
        $form = $this->source(
            'app/Modules/Operations/UI/Filament/Resources/'
            .'VisitorRecords/Schemas/VisitorRecordForm.php'
        );

        $this->assertStringContainsString(
            "FacialPhotoCapture::make('photo_capture')",
            $form
        );

        $this->assertStringNotContainsString(
            "FileUpload::make('photo_path')",
            $form
        );

        $this->assertStringNotContainsString(
            'use Filament\\Forms\\Components\\FileUpload;',
            $form
        );

        $this->assertStringContainsString(
            "->visibleOn('create')",
            $form
        );
    }

    public function test_the_action_registers_a_new_photo_and_preserves_the_previous_history(): void
    {
        $context = $this->context();

        $operator = $this->userWithPermissions(
            [
                'ViewAny:VisitorRecord',
                'View:VisitorRecord',
                'Update:VisitorRecord',
            ],
            'visitor_facial_photo_update_operator'
        );

        $this->allowOrganization(
            $operator,
            $context['organization']
        );

        $this->actingAs($operator);

        $scheduler =
            new VisitorFacialPhotoUpdateValidationSchedulerSpy;

        app()->instance(
            FacialPhotoValidationAfterCommitScheduler::class,
            $scheduler
        );

        $initialUpload =
            $this->checkerboardUpload(
                'visitante-camera-foto-inicial.jpg'
            );

        $initialResult = app(
            VisitorFacialPhotoCaptureRegistrar::class
        )->register(
            visitor: $context['visitor'],
            upload: $initialUpload,
            expectedSha256: $this->fingerprintForUpload(
                $initialUpload
            ),
            createdBy: $operator->id,
            confirmationKey: self::CONFIRMATION_KEY,
            confirmationContext: self::CONFIRMATION_CONTEXT,
        );

        $initialPhoto = FacialPhotoRecord::query()
            ->findOrFail(
                $initialResult->photoId
            );

        $initialMedia = $initialPhoto->getFirstMedia(
            FacialPhotoRecord::ORIGINAL_COLLECTION
        );

        $this->assertInstanceOf(
            Media::class,
            $initialMedia
        );

        $initialMediaPath =
            $initialMedia->getPathRelativeToRoot();

        $context['visitor']->refresh();

        $initialLegacyPath =
            $context['visitor']->photo_path;

        $this->assertIsString(
            $initialLegacyPath
        );

        Storage::disk('local')
            ->assertExists(
                $initialLegacyPath
            );

        Storage::disk('facial_photos')
            ->assertExists(
                $initialMediaPath
            );

        $scheduler->reset();

        $updatedUpload =
            $this->checkerboardUpload(
                'visitante-camera-foto-atualizada.jpg'
            );

        Livewire::test(
            ListVisitorRecords::class
        )
            ->assertActionVisible(
                TestAction::make(
                    'updateFacialPhoto'
                )->table(
                    $context['visitor']
                )
            )
            ->callAction(
                TestAction::make(
                    'updateFacialPhoto'
                )->table(
                    $context['visitor']
                ),
                [
                    'photo_capture' => $updatedUpload,
                    'photo_capture_receipt' => $this->confirmedReceipt(
                        $updatedUpload,
                        $this->confirmationContext(
                            $context['visitor']
                        )
                    ),
                ]
            )
            ->assertHasNoErrors();

        $this->assertSame(
            1,
            $scheduler->calls
        );

        $this->assertNotNull(
            $scheduler->command
        );

        $this->assertSame(
            $operator->id,
            $scheduler->command?->operatorUserId
        );

        $newPhoto = FacialPhotoRecord::query()
            ->findOrFail(
                $scheduler->command->photoId
            );

        $this->assertNotSame(
            $initialPhoto->id,
            $newPhoto->id
        );

        $this->assertSame(
            $operator->id,
            $newPhoto->created_by
        );

        $this->assertSame(
            FacialPhotoSource::Webcam,
            $newPhoto->source
        );

        $this->assertSame(
            FacialPhotoStatus::PendingValidation,
            $newPhoto->status
        );

        $newMedia = $newPhoto->getFirstMedia(
            FacialPhotoRecord::ORIGINAL_COLLECTION
        );

        $this->assertInstanceOf(
            Media::class,
            $newMedia
        );

        $newMediaPath =
            $newMedia->getPathRelativeToRoot();

        $context['visitor']->refresh();

        $this->assertIsString(
            $context['visitor']->photo_path
        );

        $this->assertNotSame(
            $initialLegacyPath,
            $context['visitor']->photo_path
        );

        Storage::disk('local')
            ->assertExists(
                $context['visitor']->photo_path
            );

        Storage::disk('facial_photos')
            ->assertExists(
                $newMediaPath
            );

        $this->assertDatabaseCount(
            'facial_photos',
            2
        );

        $this->assertDatabaseCount(
            'media',
            2
        );

        $this->assertDatabaseHas(
            'facial_photos',
            [
                'id' => $initialPhoto->id,
            ]
        );

        Storage::disk('facial_photos')
            ->assertExists(
                $initialMediaPath
            );
    }

    public function test_the_update_action_rejects_reused_confirmation_without_creating_another_photo(): void
    {
        $context = $this->context();

        $operator = $this->userWithPermissions(
            [
                'ViewAny:VisitorRecord',
                'View:VisitorRecord',
                'Update:VisitorRecord',
            ],
            'visitor_facial_photo_duplicate_confirmation_operator'
        );

        $this->allowOrganization(
            $operator,
            $context['organization']
        );

        $this->actingAs($operator);

        $scheduler =
            new VisitorFacialPhotoUpdateValidationSchedulerSpy;

        app()->instance(
            FacialPhotoValidationAfterCommitScheduler::class,
            $scheduler
        );

        $initialUpload =
            $this->checkerboardUpload(
                'visitante-camera-duplo-envio-inicial.jpg'
            );

        $initialResult = app(
            VisitorFacialPhotoCaptureRegistrar::class
        )->register(
            visitor: $context['visitor'],
            upload: $initialUpload,
            expectedSha256: $this->fingerprintForUpload(
                $initialUpload
            ),
            createdBy: $operator->id,
            confirmationKey: self::CONFIRMATION_KEY,
            confirmationContext: self::CONFIRMATION_CONTEXT,
        );

        $initialPhoto = FacialPhotoRecord::query()
            ->findOrFail(
                $initialResult->photoId
            );

        $scheduler->reset();

        $updatedUpload =
            $this->checkerboardUpload(
                'visitante-camera-duplo-envio-atualizada.jpg'
            );

        $updatedFingerprint =
            $this->fingerprintForUpload(
                $updatedUpload
            );

        $confirmationReceipt =
            $this->confirmedReceipt(
                $updatedUpload,
                $this->confirmationContext(
                    $context['visitor']
                )
            );

        Livewire::test(
            ListVisitorRecords::class
        )
            ->callAction(
                TestAction::make(
                    'updateFacialPhoto'
                )->table(
                    $context['visitor']
                ),
                [
                    'photo_capture' => $updatedUpload,

                    'photo_capture_receipt' => $confirmationReceipt,
                ]
            )
            ->assertHasNoErrors();

        $this->assertSame(
            1,
            $scheduler->calls
        );

        $this->assertNotNull(
            $scheduler->command
        );

        $updatedPhoto = FacialPhotoRecord::query()
            ->findOrFail(
                $scheduler->command->photoId
            );

        $context['visitor']->refresh();

        $legacyPathAfterFirstUpdate =
            $context['visitor']->photo_path;

        $uploadedAtAfterFirstUpdate =
            $context['visitor']
                ->photo_uploaded_at
                ?->format('Y-m-d H:i:s.u');

        $this->assertIsString(
            $legacyPathAfterFirstUpdate
        );

        $localFilesBeforeDuplicate =
            Storage::disk('local')
                ->allFiles(
                    'visitors/photos'
                );

        $facialFilesBeforeDuplicate =
            Storage::disk('facial_photos')
                ->allFiles();

        sort(
            $localFilesBeforeDuplicate
        );

        sort(
            $facialFilesBeforeDuplicate
        );

        $scheduler->reset();

        $duplicateUpload =
            $this->checkerboardUpload(
                'visitante-camera-duplo-envio-repetida.jpg'
            );

        $this->assertSame(
            $updatedFingerprint,
            $this->fingerprintForUpload(
                $duplicateUpload
            )
        );

        $duplicateComponent = Livewire::test(
            ListVisitorRecords::class
        )
            ->callAction(
                TestAction::make(
                    'updateFacialPhoto'
                )->table(
                    $context['visitor']
                ),
                [
                    'photo_capture' => $duplicateUpload,

                    'photo_capture_receipt' => $confirmationReceipt,
                ]
            );

        $this->assertSame(
            [
                'Esta confirmação da foto facial já foi utilizada. '
                    .'Analise ou capture a imagem novamente.',
            ],
            $duplicateComponent->errors()
                ->get('photo_capture')
        );

        $context['visitor']->refresh();

        $this->assertSame(
            $legacyPathAfterFirstUpdate,
            $context['visitor']->photo_path
        );

        $this->assertSame(
            $uploadedAtAfterFirstUpdate,
            $context['visitor']
                ->photo_uploaded_at
                ?->format('Y-m-d H:i:s.u')
        );

        $this->assertDatabaseCount(
            'visitors',
            1
        );

        $this->assertDatabaseCount(
            'facial_photos',
            2
        );

        $this->assertDatabaseCount(
            'facial_photo_confirmation_consumptions',
            2
        );

        $this->assertDatabaseCount(
            'media',
            2
        );

        $this->assertDatabaseHas(
            'facial_photos',
            [
                'id' => $initialPhoto->id,
            ]
        );

        $this->assertDatabaseHas(
            'facial_photos',
            [
                'id' => $updatedPhoto->id,
            ]
        );

        $this->assertSame(
            0,
            $scheduler->calls
        );

        $this->assertNull(
            $scheduler->command
        );

        $localFilesAfterDuplicate =
            Storage::disk('local')
                ->allFiles(
                    'visitors/photos'
                );

        $facialFilesAfterDuplicate =
            Storage::disk('facial_photos')
                ->allFiles();

        sort(
            $localFilesAfterDuplicate
        );

        sort(
            $facialFilesAfterDuplicate
        );

        $this->assertSame(
            $localFilesBeforeDuplicate,
            $localFilesAfterDuplicate
        );

        $this->assertSame(
            $facialFilesBeforeDuplicate,
            $facialFilesAfterDuplicate
        );
    }

    public function test_the_action_is_hidden_without_update_permission(): void
    {
        $context = $this->context();

        $viewer = $this->userWithPermissions(
            [
                'ViewAny:VisitorRecord',
                'View:VisitorRecord',
            ],
            'visitor_facial_photo_viewer'
        );

        $this->allowOrganization(
            $viewer,
            $context['organization']
        );

        $this->actingAs($viewer);

        Livewire::test(
            ListVisitorRecords::class
        )->assertActionHidden(
            TestAction::make(
                'updateFacialPhoto'
            )->table(
                $context['visitor']
            )
        );

        $this->assertDatabaseCount(
            'facial_photos',
            0
        );
    }

    public function test_the_update_action_rejects_an_expired_confirmation_and_preserves_the_previous_photo(): void
    {
        $context = $this->context();

        $operator = $this->userWithPermissions(
            [
                'ViewAny:VisitorRecord',
                'View:VisitorRecord',
                'Update:VisitorRecord',
            ],
            'visitor_facial_photo_expired_receipt_operator'
        );

        $this->allowOrganization(
            $operator,
            $context['organization']
        );

        $this->actingAs($operator);

        $scheduler =
            new VisitorFacialPhotoUpdateValidationSchedulerSpy;

        app()->instance(
            FacialPhotoValidationAfterCommitScheduler::class,
            $scheduler
        );

        $initialUpload =
            $this->checkerboardUpload(
                'visitante-camera-foto-anterior-expiracao.jpg'
            );

        $initialResult = app(
            VisitorFacialPhotoCaptureRegistrar::class
        )->register(
            visitor: $context['visitor'],
            upload: $initialUpload,
            expectedSha256: $this->fingerprintForUpload(
                $initialUpload
            ),
            createdBy: $operator->id,
            confirmationKey: self::CONFIRMATION_KEY,
            confirmationContext: self::CONFIRMATION_CONTEXT,
        );

        $initialPhoto = FacialPhotoRecord::query()
            ->findOrFail(
                $initialResult->photoId
            );

        $initialMedia = $initialPhoto->getFirstMedia(
            FacialPhotoRecord::ORIGINAL_COLLECTION
        );

        $this->assertInstanceOf(
            Media::class,
            $initialMedia
        );

        $initialMediaPath =
            $initialMedia->getPathRelativeToRoot();

        $context['visitor']->refresh();

        $initialLegacyPath =
            $context['visitor']->photo_path;

        $initialUploadedAt =
            $context['visitor']
                ->photo_uploaded_at
                ?->format('Y-m-d H:i:s.u');

        $initialLocalFiles = Storage::disk(
            'local'
        )->allFiles(
            'visitors/photos'
        );

        $initialFacialFiles = Storage::disk(
            'facial_photos'
        )->allFiles();

        $scheduler->reset();

        $expiredUpload =
            $this->checkerboardUpload(
                'visitante-camera-confirmacao-expirada.jpg'
            );

        $component = Livewire::test(
            ListVisitorRecords::class
        )
            ->callAction(
                TestAction::make(
                    'updateFacialPhoto'
                )->table(
                    $context['visitor']
                ),
                [
                    'photo_capture' => $expiredUpload,
                    'photo_capture_receipt' => $this->confirmedReceipt(
                        $expiredUpload,
                        $this->confirmationContext(
                            $context['visitor']
                        ),
                        now()
                            ->subSecond()
                            ->toDateTimeImmutable(),
                    ),
                ]
            );

        $this->assertSame(
            [
                'A análise temporária da foto expirou. Analise a imagem novamente.',
            ],
            $component->errors()
                ->get('photo_capture')
        );

        $context['visitor']->refresh();

        $this->assertSame(
            $initialLegacyPath,
            $context['visitor']->photo_path
        );

        $this->assertSame(
            $initialUploadedAt,
            $context['visitor']
                ->photo_uploaded_at
                ?->format('Y-m-d H:i:s.u')
        );

        $this->assertDatabaseCount(
            'facial_photos',
            1
        );

        $this->assertDatabaseCount(
            'media',
            1
        );

        $this->assertDatabaseHas(
            'facial_photos',
            [
                'id' => $initialPhoto->id,
            ]
        );

        $this->assertSame(
            0,
            $scheduler->calls
        );

        $this->assertNull(
            $scheduler->command
        );

        $this->assertSame(
            $initialLocalFiles,
            Storage::disk('local')
                ->allFiles(
                    'visitors/photos'
                )
        );

        $this->assertSame(
            $initialFacialFiles,
            Storage::disk('facial_photos')
                ->allFiles()
        );

        Storage::disk('local')
            ->assertExists(
                $initialLegacyPath
            );

        Storage::disk('facial_photos')
            ->assertExists(
                $initialMediaPath
            );
    }

    public function test_the_action_rolls_back_the_new_photo_when_analysis_fails(): void
    {
        $context = $this->context();

        $operator = $this->userWithPermissions(
            [
                'ViewAny:VisitorRecord',
                'View:VisitorRecord',
                'Update:VisitorRecord',
            ],
            'visitor_facial_photo_rollback_operator'
        );

        $this->allowOrganization(
            $operator,
            $context['organization']
        );

        $this->actingAs($operator);

        $scheduler =
            new VisitorFacialPhotoUpdateValidationSchedulerSpy;

        app()->instance(
            FacialPhotoValidationAfterCommitScheduler::class,
            $scheduler
        );

        $initialUpload =
            $this->checkerboardUpload(
                'visitante-camera-foto-preservada.jpg'
            );

        $initialResult = app(
            VisitorFacialPhotoCaptureRegistrar::class
        )->register(
            visitor: $context['visitor'],
            upload: $initialUpload,
            expectedSha256: $this->fingerprintForUpload(
                $initialUpload
            ),
            createdBy: $operator->id,
            confirmationKey: self::CONFIRMATION_KEY,
            confirmationContext: self::CONFIRMATION_CONTEXT,
        );

        $initialPhoto = FacialPhotoRecord::query()
            ->findOrFail(
                $initialResult->photoId
            );

        $initialMedia = $initialPhoto->getFirstMedia(
            FacialPhotoRecord::ORIGINAL_COLLECTION
        );

        $this->assertInstanceOf(
            Media::class,
            $initialMedia
        );

        $initialMediaPath =
            $initialMedia->getPathRelativeToRoot();

        $context['visitor']->refresh();

        $initialLegacyPath =
            $context['visitor']->photo_path;

        $initialUploadedAt =
            $context['visitor']
                ->photo_uploaded_at
                ?->format('Y-m-d H:i:s.u');

        $initialLocalFiles = Storage::disk(
            'local'
        )->allFiles(
            'visitors/photos'
        );

        $initialFacialFiles = Storage::disk(
            'facial_photos'
        )->allFiles();

        $scheduler->reset();

        app()->instance(
            RegisterFacialPhotoRepository::class,
            new EloquentRegisterFacialPhotoRepository(
                new AnalyzeFacialPhotoUseCase(
                    new class implements FacialPhotoTechnicalAnalyzer
                    {
                        public function analyze(
                            string $absolutePath
                        ): FacialPhotoTechnicalAnalysis {
                            throw new RuntimeException(
                                'Falha sintética na atualização facial.'
                            );
                        }
                    }
                ),
                app(FacialPhotoMediaCleanup::class),
            )
        );

        $failedUpload =
            $this->checkerboardUpload(
                'visitante-camera-foto-com-falha.jpg'
            );

        $caught = null;

        try {
            Livewire::test(
                ListVisitorRecords::class
            )->callAction(
                TestAction::make(
                    'updateFacialPhoto'
                )->table(
                    $context['visitor']
                ),
                [
                    'photo_capture' => $failedUpload,
                    'photo_capture_receipt' => $this->confirmedReceipt(
                        $failedUpload,
                        $this->confirmationContext(
                            $context['visitor']
                        )
                    ),
                ]
            );
        } catch (Throwable $exception) {
            $caught = $exception;
        }

        $this->assertInstanceOf(
            RegisterVisitorFacialPhotoException::class,
            $caught
        );

        $this->assertSame(
            'Não foi possível registrar e analisar a foto facial.',
            $caught->getMessage()
        );

        $context['visitor']->refresh();

        $this->assertSame(
            $initialLegacyPath,
            $context['visitor']->photo_path
        );

        $this->assertSame(
            $initialUploadedAt,
            $context['visitor']
                ->photo_uploaded_at
                ?->format('Y-m-d H:i:s.u')
        );

        $this->assertDatabaseCount(
            'facial_photos',
            1
        );

        $this->assertDatabaseCount(
            'media',
            1
        );

        $this->assertDatabaseHas(
            'facial_photos',
            [
                'id' => $initialPhoto->id,
            ]
        );

        $this->assertSame(
            0,
            $scheduler->calls
        );

        $this->assertNull(
            $scheduler->command
        );

        $this->assertSame(
            $initialLocalFiles,
            Storage::disk('local')
                ->allFiles(
                    'visitors/photos'
                )
        );

        $this->assertSame(
            $initialFacialFiles,
            Storage::disk('facial_photos')
                ->allFiles()
        );

        Storage::disk('local')
            ->assertExists(
                $initialLegacyPath
            );

        Storage::disk('facial_photos')
            ->assertExists(
                $initialMediaPath
            );
    }

    private function bindApprovedFacialValidator(): void
    {
        config()->set(
            'facial_photos.validation.enabled',
            true
        );

        config()->set(
            'facial_photos.validation.provider',
            'simulator'
        );

        config()->set(
            'facial_photos.validation.simulator.enabled',
            true
        );

        config()->set(
            'facial_photos.validation.simulator.default_scenario',
            'approved'
        );

        app()->instance(
            FacialPhotoValidatorResolver::class,
            new class implements FacialPhotoValidatorResolver
            {
                public function resolve(
                    FacialPhotoValidatorSelection $selection
                ): FacialPhotoValidator {
                    return new class implements FacialPhotoValidator
                    {
                        public function validate(
                            string $absolutePath
                        ): FacialPhotoValidationResult {
                            return new FacialPhotoValidationResult(
                                validator: 'action-test-approved-validator',
                                version: 'action-test-approved-v1',
                                decision: FacialPhotoValidationDecision::Approved,
                                faceCount: 1,
                                metrics: [
                                    'synthetic_test_fixture' => true,
                                ],
                                issues: [],
                            );
                        }
                    };
                }
            }
        );
    }

    /**
     * @return array{
     *     tenant: TenantRecord,
     *     organization: OrganizationRecord,
     *     visitor: VisitorRecord
     * }
     */
    private function context(): array
    {
        $tenant = TenantRecord::query()
            ->create([
                'id' => (string) Str::uuid(),
                'name' => 'GRUPO ATUALIZAÇÃO FACIAL',
                'status' => 'active',
            ]);

        $organization = OrganizationRecord::query()
            ->create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'status' => 'active',
                'legal_name' => 'UNIDADE ATUALIZAÇÃO FACIAL LTDA',
                'display_name' => 'UNIDADE ATUALIZAÇÃO FACIAL',
                'unit_code' => 'UAF-01',
            ]);

        $visitor = VisitorRecord::query()
            ->create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'organization_id' => $organization->id,
                'full_name' => 'VISITANTE ATUALIZAÇÃO FACIAL',
                'preferred_name' => 'Visitante Facial',
                'status' => VisitorStatus::Active->value,
                'photo_disk' => 'local',
            ]);

        return [
            'tenant' => $tenant,
            'organization' => $organization,
            'visitor' => $visitor,
        ];
    }

    /**
     * @param  list<string>  $permissions
     */
    private function userWithPermissions(
        array $permissions,
        string $roleName
    ): User {
        foreach ($permissions as $permission) {
            Permission::findOrCreate(
                $permission,
                'web'
            );
        }

        $role = Role::findOrCreate(
            $roleName,
            'web'
        );

        $role->syncPermissions(
            $permissions
        );

        $user = User::factory()
            ->create();

        $user->assignRole(
            $role
        );

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();

        return $user;
    }

    private function allowOrganization(
        User $user,
        OrganizationRecord $organization
    ): void {
        $user->organizations()->attach(
            $organization->id,
            [
                'role' => 'operator',
                'is_active' => true,
                'granted_at' => now(),
            ]
        );

        app(TenantContext::class)
            ->initializeForUser($user);
    }

    private function fingerprintForUpload(
        UploadedFile $upload
    ): string {
        $absolutePath =
            $upload->getRealPath();

        $this->assertIsString(
            $absolutePath
        );

        $fingerprint = hash_file(
            'sha256',
            $absolutePath
        );

        $this->assertIsString(
            $fingerprint
        );

        return $fingerprint;
    }

    private function confirmationContext(
        VisitorRecord $visitor
    ): string {
        return 'visitor.update.'
            .(string) $visitor->getKey()
            .'.photo_capture';
    }

    private function confirmedReceipt(
        UploadedFile $upload,
        string $context,
        ?\DateTimeImmutable $expiresAt = null,
    ): string {
        $absolutePath =
            $upload->getRealPath();

        $this->assertIsString(
            $absolutePath
        );

        $fingerprint = hash_file(
            'sha256',
            $absolutePath
        );

        $this->assertIsString(
            $fingerprint
        );

        $userId = auth()->id();

        $this->assertTrue(
            is_numeric($userId)
        );

        return app(
            FacialPhotoPreviewReceiptCodec::class
        )->encode(
            new FacialPhotoPreviewReceipt(
                fingerprint: $fingerprint,
                decision: FacialPhotoPreviewDecision::Approved,
                statePath: $context,
                userId: (int) $userId,
                expiresAt: $expiresAt
                    ?? now()
                        ->addMinutes(5)
                        ->toDateTimeImmutable(),
            )
        );
    }

    private function checkerboardUpload(
        string $originalFileName
    ): UploadedFile {
        $width = 720;
        $height = 900;

        $image = imagecreatetruecolor(
            $width,
            $height
        );

        $this->assertInstanceOf(
            GdImage::class,
            $image
        );

        $dark = imagecolorallocate(
            $image,
            35,
            35,
            35
        );

        $light = imagecolorallocate(
            $image,
            220,
            220,
            220
        );

        $block = 24;

        for (
            $y = 0;
            $y < $height;
            $y += $block
        ) {
            for (
                $x = 0;
                $x < $width;
                $x += $block
            ) {
                $color = (
                    (
                        intdiv(
                            $x,
                            $block
                        )
                        + intdiv(
                            $y,
                            $block
                        )
                    ) % 2 === 0
                )
                    ? $dark
                    : $light;

                imagefilledrectangle(
                    $image,
                    $x,
                    $y,
                    min(
                        $width - 1,
                        $x + $block - 1
                    ),
                    min(
                        $height - 1,
                        $y + $block - 1
                    ),
                    $color
                );
            }
        }

        $path = $this->imageDirectory
            .'/'
            .Str::uuid()
            .'.jpg';

        $this->assertTrue(
            imagejpeg(
                $image,
                $path,
                92
            )
        );

        imagedestroy(
            $image
        );

        $imageContent = file_get_contents(
            $path
        );

        $this->assertIsString(
            $imageContent
        );

        return UploadedFile::fake()
            ->createWithContent(
                $originalFileName,
                $imageContent
            )
            ->mimeType('image/jpeg');
    }

    private function source(
        string $relativePath
    ): string {
        $contents = file_get_contents(
            dirname(__DIR__, 8)
            .'/'.$relativePath
        );

        $this->assertIsString(
            $contents
        );

        return $contents;
    }
}

final class VisitorFacialPhotoUpdateValidationSchedulerSpy implements FacialPhotoValidationAfterCommitScheduler
{
    public int $calls = 0;

    public ?ScheduleFacialPhotoValidationCommand $command =
        null;

    public function schedule(
        ScheduleFacialPhotoValidationCommand $command,
    ): bool {
        $this->calls++;
        $this->command = $command;

        return true;
    }

    public function reset(): void
    {
        $this->calls = 0;
        $this->command = null;
    }

    public function test_update_photo_request_identifier_is_temporary(): void
    {
        $source = $this->source(
            'app/Modules/Operations/UI/Filament/Resources/'
            .'VisitorRecords/Actions/'
            .'UpdateVisitorFacialPhotoAction.php'
        );

        $requestPosition = strpos(
            $source,
            "Hidden::make('photo_capture_request_id')"
        );

        $receiptPosition = strpos(
            $source,
            "Hidden::make('photo_capture_receipt')"
        );

        $this->assertIsInt($requestPosition);
        $this->assertIsInt($receiptPosition);
        $this->assertTrue(
            $requestPosition < $receiptPosition
        );

        $requestField = substr(
            $source,
            $requestPosition,
            $receiptPosition - $requestPosition
        );

        $this->assertStringContainsString(
            '->dehydrated(false)',
            $requestField
        );
    }
}
