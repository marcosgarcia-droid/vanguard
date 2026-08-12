<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Infrastructure\Persistence\Eloquent;

use App\Models\User;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use App\Modules\Operations\Application\FacialPhotos\Derivatives\Reprocess\ReprocessFacialPhotoDerivativeCommand;
use App\Modules\Operations\Application\FacialPhotos\Derivatives\Reprocess\ReprocessFacialPhotoDerivativeException;
use App\Modules\Operations\Application\FacialPhotos\Derivatives\Reprocess\ReprocessFacialPhotoDerivativeRepository;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoDerivativeStatus;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoSource;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoStatus;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoSubjectType;
use App\Modules\Operations\Domain\Visitors\VisitorStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\EloquentReprocessFacialPhotoDerivativeRepository;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialPhotoDerivativeRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialPhotoRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class EloquentReprocessFacialPhotoDerivativeRepositoryTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_it_prepares_a_failed_derivative_for_reprocessing(): void
    {
        $context = $this->context(
            FacialPhotoStatus::Approved,
            FacialPhotoDerivativeStatus::Failed,
        );

        $result = $this->repository()->prepare(
            $this->command(
                $context['visitor'],
                $context['operator']
            ),
            'vanguard_normalized',
            'vanguard-normalization-v1',
        );

        $this->assertSame(
            $context['photo']->id,
            $result->photoId
        );

        $this->assertSame(
            $context['operator']->name,
            $result->requesterName
        );

        $this->assertSame(
            FacialPhotoDerivativeStatus::Failed,
            $result->previousStatus
        );
    }

    public function test_it_allows_a_missing_derivative_for_an_approved_photo(): void
    {
        $context = $this->context(
            FacialPhotoStatus::Approved,
            null,
        );

        $result = $this->repository()->prepare(
            $this->command(
                $context['visitor'],
                $context['operator']
            ),
            'vanguard_normalized',
            'vanguard-normalization-v1',
        );

        $this->assertNull(
            $result->previousStatus
        );
    }

    public function test_it_rejects_a_ready_derivative(): void
    {
        $context = $this->context(
            FacialPhotoStatus::Approved,
            FacialPhotoDerivativeStatus::Ready,
        );

        $this->assertFailure(
            'facial_derivative_already_ready',
            fn () => $this->repository()->prepare(
                $this->command(
                    $context['visitor'],
                    $context['operator']
                ),
                'vanguard_normalized',
                'vanguard-normalization-v1',
            ));
    }

    public function test_it_rejects_a_derivative_already_processing(): void
    {
        $context = $this->context(
            FacialPhotoStatus::Approved,
            FacialPhotoDerivativeStatus::Processing,
        );

        $this->assertFailure(
            'facial_derivative_already_processing',
            fn () => $this->repository()->prepare(
                $this->command(
                    $context['visitor'],
                    $context['operator']
                ),
                'vanguard_normalized',
                'vanguard-normalization-v1',
            ));
    }

    public function test_it_rejects_an_unapproved_photo(): void
    {
        $context = $this->context(
            FacialPhotoStatus::PendingValidation,
            null,
        );

        $this->assertFailure(
            'facial_photo_not_approved',
            fn () => $this->repository()->prepare(
                $this->command(
                    $context['visitor'],
                    $context['operator']
                ),
                'vanguard_normalized',
                'vanguard-normalization-v1',
            ));
    }

    public function test_it_reauthorizes_the_operator_in_the_backend(): void
    {
        $context = $this->context(
            FacialPhotoStatus::Approved,
            FacialPhotoDerivativeStatus::Failed,
            authorized: false,
        );

        $this->assertFailure(
            'operation_not_authorized',
            fn () => $this->repository()->prepare(
                $this->command(
                    $context['visitor'],
                    $context['operator']
                ),
                'vanguard_normalized',
                'vanguard-normalization-v1',
            ));
    }

    public function test_the_permission_and_binding_are_registered(): void
    {
        $this->assertInstanceOf(
            EloquentReprocessFacialPhotoDerivativeRepository::class,
            app(
                ReprocessFacialPhotoDerivativeRepository::class
            )
        );

        $policy = file_get_contents(
            base_path(
                'app/Modules/Operations/Infrastructure/'
                .'Persistence/Eloquent/VisitorRecordPolicy.php'
            )
        );

        $seeder = file_get_contents(
            base_path(
                'database/seeders/VanguardAccessSeeder.php'
            )
        );

        $this->assertIsString(
            $policy
        );

        $this->assertIsString(
            $seeder
        );

        $this->assertStringContainsString(
            'reprocessFacialPhotoDerivative',
            $policy
        );

        $this->assertSame(
            3,
            substr_count(
                $seeder,
                'ReprocessFacialPhotoDerivative:VisitorRecord'
            )
        );
    }

    /**
     * @return array{
     *     visitor: VisitorRecord,
     *     operator: User,
     *     photo: FacialPhotoRecord
     * }
     */
    private function context(
        FacialPhotoStatus $photoStatus,
        ?FacialPhotoDerivativeStatus $derivativeStatus,
        bool $authorized = true,
    ): array {
        $tenant = TenantRecord::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'GRUPO A5F',
            'status' => 'active',
        ]);

        $organization =
            OrganizationRecord::query()->create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'status' => 'active',
                'legal_name' => 'UNIDADE A5F LTDA',
                'display_name' => 'UNIDADE A5F',
                'unit_code' => 'A5F-01',
            ]);

        $visitor = VisitorRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'full_name' => 'VISITANTE SINTÉTICO A5F',
            'status' => VisitorStatus::Active,
        ]);

        $operator = User::factory()->create([
            'name' => 'OPERADOR SINTÉTICO A5F',
        ]);

        if ($authorized) {
            $role = Role::findOrCreate(
                config(
                    'filament-shield.super_admin.name',
                    'super_admin'
                ),
                'web'
            );

            $operator->assignRole(
                $role
            );
        }

        $photo = $visitor
            ->facialPhotos()
            ->create([
                'tenant_id' => $tenant->id,
                'organization_id' => $organization->id,
                'created_by' => $operator->id,
                'source' => FacialPhotoSource::Webcam,
                'status' => $photoStatus,
                'captured_at' => now(),
                'approved_at' => $photoStatus === FacialPhotoStatus::Approved
                        ? now()
                        : null,
                'sha256' => str_repeat('a', 64),
            ]);

        if (
            $derivativeStatus
                instanceof FacialPhotoDerivativeStatus
        ) {
            FacialPhotoDerivativeRecord::query()->create([
                'facial_photo_id' => $photo->id,
                'tenant_id' => $tenant->id,
                'organization_id' => $organization->id,
                'profile' => 'vanguard_normalized',
                'policy_version' => 'vanguard-normalization-v1',
                'status' => $derivativeStatus,
                'source_sha256' => str_repeat('a', 64),
                /*
                 * Este teste verifica somente a elegibilidade pelo
                 * estado do derivado. Nenhuma mídia inexistente deve
                 * ser referenciada pela fixture.
                 */
                'media_id' => null,
                'sha256' => $derivativeStatus
                        === FacialPhotoDerivativeStatus::Ready
                            ? str_repeat('b', 64)
                            : null,
                'generated_at' => $derivativeStatus
                        === FacialPhotoDerivativeStatus::Ready
                            ? now()
                            : null,
            ]);
        }

        return [
            'visitor' => $visitor,
            'operator' => $operator,
            'photo' => $photo,
        ];
    }

    private function command(
        VisitorRecord $visitor,
        User $operator
    ): ReprocessFacialPhotoDerivativeCommand {
        return new ReprocessFacialPhotoDerivativeCommand(
            subjectType: FacialPhotoSubjectType::Visitor,
            subjectId: (string) $visitor->id,
            operatorUserId: (int) $operator->id,
            requestId: (string) Str::uuid(),
        );
    }

    private function repository(): ReprocessFacialPhotoDerivativeRepository
    {
        return app(
            ReprocessFacialPhotoDerivativeRepository::class
        );
    }

    private function assertFailure(
        string $failureCode,
        callable $operation
    ): void {
        try {
            $operation();

            $this->fail(
                'A operação deveria ter sido bloqueada.'
            );
        } catch (
            ReprocessFacialPhotoDerivativeException $exception
        ) {
            $this->assertSame(
                $failureCode,
                $exception->failureCode
            );
        }
    }
}
