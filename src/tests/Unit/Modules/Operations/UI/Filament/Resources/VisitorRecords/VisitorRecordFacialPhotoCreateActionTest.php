<?php

namespace Tests\Unit\Modules\Operations\UI\Filament\Resources\VisitorRecords;

use App\Models\User;
use App\Modules\Identity\Application\Tenancy\TenantContext;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Application\FacialPhotos\Preview\Receipts\FacialPhotoPreviewReceipt;
use App\Modules\Operations\Application\FacialPhotos\Preview\Receipts\FacialPhotoPreviewReceiptCodec;
use App\Modules\Operations\Application\FacialPhotos\Registration\RegisterVisitorFacialPhotoException;
use App\Modules\Operations\Application\FacialPhotos\Registration\RegisterVisitorFacialPhotoRepository;
use App\Modules\Operations\Application\FacialPhotos\TechnicalAnalysis\AnalyzeFacialPhotoUseCase;
use App\Modules\Operations\Application\FacialPhotos\TechnicalAnalysis\FacialPhotoTechnicalAnalyzer;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoPreviewDecision;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoSource;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoStatus;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoTechnicalAnalysis;
use App\Modules\Operations\Domain\Visitors\VisitorStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\EloquentRegisterVisitorFacialPhotoRepository;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialPhotoRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use App\Modules\Operations\Infrastructure\Storage\FacialPhotoMediaCleanup;
use App\Modules\Operations\UI\Filament\Resources\VisitorRecords\Pages\ListVisitorRecords;
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

final class VisitorRecordFacialPhotoCreateActionTest extends TestCase
{
    use RefreshDatabase;

    private const PHOTO_CONFIRMATION_CONTEXT =
        'visitor.create.photo_capture';

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

        $this->imageDirectory = storage_path(
            'framework/testing/'
            .'visitor-create-action-facial-photo'
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

    public function test_preview_response_carries_the_current_request_identifier(): void
    {
        $context = $this->context();

        $operator = $this->operator();

        $this->allowOrganization(
            $operator,
            $context['organization']
        );

        $this->actingAs($operator);

        $requestId =
            '75f39c04-b68e-4ea0-84bf-823442b91c72';

        $upload = $this->checkerboardUpload(
            'visitante-preview-correlacionado.jpg'
        );

        Livewire::test(
            ListVisitorRecords::class
        )
            ->assertActionVisible('create')
            ->mountAction('create')
            ->set(
                'mountedActions.0.data.photo_capture_request_id',
                $requestId
            )
            ->set(
                'mountedActions.0.data.photo_capture',
                $upload
            )
            ->assertDispatched(
                'visitor-photo-preview-completed',
                function (
                    string $event,
                    array $parameters
                ) use ($requestId): bool {
                    return $event
                            === 'visitor-photo-preview-completed'
                        && (
                            $parameters['requestId']
                                ?? null
                        ) === $requestId
                        && (
                            $parameters['statePath']
                                ?? null
                        ) === 'mountedActions.0.data.photo_capture'
                        && array_key_exists(
                            'result',
                            $parameters
                        );
                }
            );
    }

    public function test_create_action_persists_visitor_relationships_and_webcam_photo(): void
    {
        $context = $this->context();

        $operator = $this->operator();

        $this->allowOrganization(
            $operator,
            $context['organization']
        );

        $this->actingAs($operator);

        $upload = $this->checkerboardUpload(
            'visitante-camera-livewire-success.jpg'
        );

        $component = Livewire::test(
            ListVisitorRecords::class
        )
            ->assertActionVisible('create')
            ->mountAction('create');

        $this->fillMountedCreateAction(
            component: $component,
            actionData: $this->creationData(
                organization: $context['organization'],
                upload: $upload,
                fullName: 'VISITANTE LIVEWIRE FACIAL',
                documentNumber: '52998224725',
                contactValue: '(38) 99999-1100',
            ),
        );

        $component
            ->callMountedAction()
            ->assertHasNoErrors();

        $visitor = VisitorRecord::query()
            ->where(
                'full_name',
                'VISITANTE LIVEWIRE FACIAL'
            )
            ->sole();

        $this->assertSame(
            $context['tenant']->id,
            $visitor->tenant_id
        );

        $this->assertSame(
            $context['organization']->id,
            $visitor->organization_id
        );

        $this->assertSame(
            VisitorStatus::Active,
            $visitor->status
        );

        $this->assertSame(
            'local',
            $visitor->photo_disk
        );

        $this->assertIsString(
            $visitor->photo_path
        );

        $this->assertNotNull(
            $visitor->photo_uploaded_at
        );

        Storage::disk('local')
            ->assertExists(
                $visitor->photo_path
            );

        $document = $visitor
            ->documents()
            ->sole();

        $this->assertSame(
            'cpf',
            $document->type
        );

        $this->assertSame(
            '52998224725',
            $document->number
        );

        $this->assertSame(
            '52998224725',
            $document->normalized_number
        );

        $this->assertTrue(
            $document->is_primary
        );

        $contact = $visitor
            ->contacts()
            ->sole();

        $this->assertSame(
            'mobile',
            $contact->type
        );

        $this->assertSame(
            '38999991100',
            $contact->value
        );

        $this->assertSame(
            '38999991100',
            $contact->normalized_value
        );

        $this->assertTrue(
            $contact->is_primary
        );

        $photo = FacialPhotoRecord::query()
            ->sole();

        $this->assertSame(
            $context['tenant']->id,
            $photo->tenant_id
        );

        $this->assertSame(
            $context['organization']->id,
            $photo->organization_id
        );

        $this->assertSame(
            $operator->id,
            $photo->created_by
        );

        $this->assertSame(
            FacialPhotoSource::Webcam,
            $photo->source
        );

        $this->assertSame(
            FacialPhotoStatus::PendingValidation,
            $photo->status
        );

        $this->assertNotNull(
            $photo->analyzed_at
        );

        $this->assertNull(
            $photo->approved_at
        );

        $media = $photo->getFirstMedia(
            FacialPhotoRecord::ORIGINAL_COLLECTION
        );

        $this->assertInstanceOf(
            Media::class,
            $media
        );

        $this->assertSame(
            'facial_photos',
            $media->disk
        );

        Storage::disk('facial_photos')
            ->assertExists(
                $media->getPathRelativeToRoot()
            );

        $this->assertDatabaseCount(
            'visitors',
            1
        );

        $this->assertDatabaseCount(
            'visitor_documents',
            1
        );

        $this->assertDatabaseCount(
            'visitor_contacts',
            1
        );

        $this->assertDatabaseCount(
            'facial_photos',
            1
        );

        $this->assertDatabaseCount(
            'media',
            1
        );
    }

    public function test_create_action_rejects_an_invalid_confirmation_without_creating_records_or_files(): void
    {
        $context = $this->context();

        $operator = $this->operator();

        $this->allowOrganization(
            $operator,
            $context['organization']
        );

        $this->actingAs($operator);

        $upload = $this->checkerboardUpload(
            'visitante-camera-confirmacao-invalida.jpg'
        );

        $actionData = $this->creationData(
            organization: $context['organization'],
            upload: $upload,
            fullName: 'VISITANTE CONFIRMAÇÃO INVÁLIDA',
            documentNumber: '39053344705',
            contactValue: '(38) 99999-3300',
        );

        $actionData['photo_capture_receipt'] =
            'confirmacao-facial-invalida';

        $component = Livewire::test(
            ListVisitorRecords::class
        )
            ->assertActionVisible('create')
            ->mountAction('create');

        $this->fillMountedCreateAction(
            component: $component,
            actionData: $actionData,
        );

        $component
            ->callMountedAction();

        $this->assertSame(
            [
                'A confirmação temporária da foto é inválida. Analise a imagem novamente.',
            ],
            $component->errors()
                ->get('photo_capture')
        );

        $this->assertDatabaseCount(
            'visitors',
            0
        );

        $this->assertDatabaseCount(
            'visitor_documents',
            0
        );

        $this->assertDatabaseCount(
            'visitor_contacts',
            0
        );

        $this->assertDatabaseCount(
            'facial_photos',
            0
        );

        $this->assertDatabaseCount(
            'media',
            0
        );

        $this->assertSame(
            [],
            Storage::disk('local')
                ->allFiles(
                    'visitors/photos'
                )
        );

        $this->assertSame(
            [],
            Storage::disk('facial_photos')
                ->allFiles()
        );
    }

    public function test_create_action_rejects_reused_confirmation_without_duplicating_records_or_files(): void
    {
        $context = $this->context();

        $operator = $this->operator();

        $this->allowOrganization(
            $operator,
            $context['organization']
        );

        $this->actingAs($operator);

        $firstUpload = $this->checkerboardUpload(
            'visitante-camera-duplo-envio-primeiro.jpg'
        );

        $firstFingerprint = hash_file(
            'sha256',
            $firstUpload->getRealPath()
        );

        $this->assertIsString(
            $firstFingerprint
        );

        $firstActionData = $this->creationData(
            organization: $context['organization'],
            upload: $firstUpload,
            fullName: 'VISITANTE DUPLO ENVIO ORIGINAL',
            documentNumber: '15350986774',
            contactValue: '(38) 99999-4400',
        );

        $confirmationReceipt =
            $firstActionData['photo_capture_receipt'];

        $this->assertIsString(
            $confirmationReceipt
        );

        $firstComponent = Livewire::test(
            ListVisitorRecords::class
        )
            ->assertActionVisible('create')
            ->mountAction('create');

        $this->fillMountedCreateAction(
            component: $firstComponent,
            actionData: $firstActionData,
        );

        $firstComponent
            ->callMountedAction()
            ->assertHasNoErrors();

        $visitor = VisitorRecord::query()
            ->where(
                'full_name',
                'VISITANTE DUPLO ENVIO ORIGINAL'
            )
            ->sole();

        $photo = FacialPhotoRecord::query()
            ->sole();

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

        $duplicateUpload = $this->checkerboardUpload(
            'visitante-camera-duplo-envio-segundo.jpg'
        );

        $duplicateFingerprint = hash_file(
            'sha256',
            $duplicateUpload->getRealPath()
        );

        $this->assertSame(
            $firstFingerprint,
            $duplicateFingerprint
        );

        $duplicateActionData =
            $firstActionData;

        $duplicateActionData['photo_capture'] =
            $duplicateUpload;

        $duplicateActionData['photo_capture_receipt'] =
            $confirmationReceipt;

        $duplicateActionData['full_name'] =
            'VISITANTE DUPLO ENVIO BLOQUEADO';

        $duplicateActionData['documents'][0]['number'] =
            '41863025703';

        $duplicateActionData['contacts'][0]['value'] =
            '(38) 99999-5500';

        $duplicateComponent = Livewire::test(
            ListVisitorRecords::class
        )
            ->assertActionVisible('create')
            ->mountAction('create');

        $this->fillMountedCreateAction(
            component: $duplicateComponent,
            actionData: $duplicateActionData,
        );

        $duplicateComponent
            ->callMountedAction();

        $this->assertSame(
            [
                'Esta confirmação da foto facial já foi utilizada. '
                    .'Analise ou capture a imagem novamente.',
            ],
            $duplicateComponent->errors()
                ->get('photo_capture')
        );

        $this->assertDatabaseCount(
            'visitors',
            1
        );

        $this->assertDatabaseCount(
            'visitor_documents',
            1
        );

        $this->assertDatabaseCount(
            'visitor_contacts',
            1
        );

        $this->assertDatabaseCount(
            'facial_photos',
            1
        );

        $this->assertDatabaseCount(
            'facial_photo_confirmation_consumptions',
            1
        );

        $this->assertDatabaseCount(
            'media',
            1
        );

        $this->assertDatabaseHas(
            'visitors',
            [
                'id' => $visitor->id,
                'full_name' => 'VISITANTE DUPLO ENVIO ORIGINAL',
            ]
        );

        $this->assertDatabaseMissing(
            'visitors',
            [
                'full_name' => 'VISITANTE DUPLO ENVIO BLOQUEADO',
            ]
        );

        $this->assertDatabaseHas(
            'facial_photos',
            [
                'id' => $photo->id,
            ]
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

    public function test_create_action_rolls_back_visitor_relationships_and_files_when_analysis_fails(): void
    {
        $context = $this->context();

        $operator = $this->operator();

        $this->allowOrganization(
            $operator,
            $context['organization']
        );

        $this->actingAs($operator);

        app()->instance(
            RegisterVisitorFacialPhotoRepository::class,
            new EloquentRegisterVisitorFacialPhotoRepository(
                new AnalyzeFacialPhotoUseCase(
                    new class implements FacialPhotoTechnicalAnalyzer
                    {
                        public function analyze(
                            string $absolutePath
                        ): FacialPhotoTechnicalAnalysis {
                            throw new RuntimeException(
                                'Falha sintética no analisador Livewire.'
                            );
                        }
                    }
                ),
                app(FacialPhotoMediaCleanup::class),
            )
        );

        $upload = $this->checkerboardUpload(
            'visitante-camera-livewire-failure.jpg'
        );

        $component = Livewire::test(
            ListVisitorRecords::class
        )
            ->assertActionVisible('create')
            ->mountAction('create');

        $this->fillMountedCreateAction(
            component: $component,
            actionData: $this->creationData(
                organization: $context['organization'],
                upload: $upload,
                fullName: 'VISITANTE LIVEWIRE ROLLBACK',
                documentNumber: '11144477735',
                contactValue: '(38) 99999-2200',
            ),
        );

        $caught = null;

        try {
            $component->callMountedAction();
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

        $this->assertDatabaseCount(
            'visitors',
            0
        );

        $this->assertDatabaseCount(
            'visitor_documents',
            0
        );

        $this->assertDatabaseCount(
            'visitor_contacts',
            0
        );

        $this->assertDatabaseCount(
            'facial_photos',
            0
        );

        $this->assertDatabaseCount(
            'media',
            0
        );

        $this->assertSame(
            [],
            Storage::disk('local')
                ->allFiles(
                    'visitors/photos'
                )
        );

        $this->assertSame(
            [],
            Storage::disk('facial_photos')
                ->allFiles()
        );
    }

    /**
     * @param  array<string, mixed>  $actionData
     */
    private function fillMountedCreateAction(
        mixed $component,
        array $actionData,
    ): void {
        $documents = $actionData['documents']
            ?? null;

        $contacts = $actionData['contacts']
            ?? null;

        $this->assertIsArray(
            $documents
        );

        $this->assertIsArray(
            $contacts
        );

        $document = reset(
            $documents
        );

        $contact = reset(
            $contacts
        );

        $this->assertIsArray(
            $document
        );

        $this->assertIsArray(
            $contact
        );

        unset(
            $actionData['documents'],
            $actionData['contacts'],
        );

        $component->fillForm(
            $actionData
        );

        $mountedDocuments = $component->get(
            'mountedActions.0.data.documents'
        );

        $mountedContacts = $component->get(
            'mountedActions.0.data.contacts'
        );

        $this->assertIsArray(
            $mountedDocuments
        );

        $this->assertIsArray(
            $mountedContacts
        );

        $documentKey = array_key_first(
            $mountedDocuments
        );

        $contactKey = array_key_first(
            $mountedContacts
        );

        $this->assertIsString(
            $documentKey
        );

        $this->assertIsString(
            $contactKey
        );

        foreach (
            $document as $field => $value
        ) {
            $component->set(
                "mountedActions.0.data.documents.{$documentKey}.{$field}",
                $value
            );
        }

        foreach (
            $contact as $field => $value
        ) {
            $component->set(
                "mountedActions.0.data.contacts.{$contactKey}.{$field}",
                $value
            );
        }

        $this->assertSame(
            $document['number'],
            $component->get(
                "mountedActions.0.data.documents.{$documentKey}.number"
            )
        );

        $this->assertSame(
            $contact['value'],
            $component->get(
                "mountedActions.0.data.contacts.{$contactKey}.value"
            )
        );
    }

    /**
     * @return array{
     *     tenant: TenantRecord,
     *     organization: OrganizationRecord
     * }
     */
    private function context(): array
    {
        $tenant = TenantRecord::query()
            ->create([
                'id' => (string) Str::uuid(),
                'name' => 'GRUPO LIVEWIRE FOTO FACIAL',
                'status' => 'active',
            ]);

        $organization =
            OrganizationRecord::query()
                ->create([
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenant->id,
                    'status' => 'active',
                    'legal_name' => 'UNIDADE LIVEWIRE FOTO FACIAL LTDA',
                    'display_name' => 'UNIDADE LIVEWIRE FOTO FACIAL',
                    'unit_code' => 'LWF-01',
                ]);

        return [
            'tenant' => $tenant,
            'organization' => $organization,
        ];
    }

    private function operator(): User
    {
        $permissions = [
            'ViewAny:VisitorRecord',
            'Create:VisitorRecord',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate(
                $permission,
                'web'
            );
        }

        $role = Role::findOrCreate(
            'visitor_livewire_facial_operator_test',
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

    /**
     * @return array<string, mixed>
     */
    private function creationData(
        OrganizationRecord $organization,
        UploadedFile $upload,
        string $fullName,
        string $documentNumber,
        string $contactValue,
    ): array {
        return [
            'tenant_id' => $organization->tenant_id,
            'photo_disk' => 'local',
            'photo_capture' => $upload,
            'photo_capture_receipt' => $this->confirmedReceipt(
                $upload,
                self::PHOTO_CONFIRMATION_CONTEXT
            ),
            'photo_path' => null,
            'full_name' => $fullName,
            'preferred_name' => 'Visitante Facial',
            'organization_id' => $organization->id,
            'partner_id' => null,
            'birth_date' => null,
            'status' => VisitorStatus::Active->value,
            'documents' => [
                [
                    'type' => 'cpf',
                    'number' => $documentNumber,
                    'state' => null,
                    'is_primary' => true,
                    'issuing_authority' => null,
                    'issued_at' => null,
                    'expires_at' => null,
                    'notes' => null,
                ],
            ],
            'contacts' => [
                [
                    'type' => 'mobile',
                    'label' => 'Celular pessoal',
                    'value' => $contactValue,
                    'is_primary' => true,
                    'notes' => null,
                ],
            ],
            'notes' => 'Cadastro Livewire com foto facial.',
        ];
    }

    private function confirmedReceipt(
        UploadedFile $upload,
        string $context
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
                decision: FacialPhotoPreviewDecision::Inconclusive,
                statePath: $context,
                userId: (int) $userId,
                expiresAt: now()
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

        imagedestroy($image);

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
}
