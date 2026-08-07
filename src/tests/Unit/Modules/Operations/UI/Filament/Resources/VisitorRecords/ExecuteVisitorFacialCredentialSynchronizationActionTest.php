<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\UI\Filament\Resources\VisitorRecords;

use App\Modules\Operations\Application\FacialCredentials\Execute\ExecuteFacialCredentialSynchronizationCommand;
use App\Modules\Operations\Application\FacialCredentials\Execute\ExecuteFacialCredentialSynchronizationRepository;
use App\Modules\Operations\Application\FacialCredentials\Execute\ExecuteFacialCredentialSynchronizationResult;
use App\Modules\Operations\Application\FacialCredentials\Execute\ExecuteFacialCredentialSynchronizationUseCase;
use App\Modules\Operations\Domain\FacialCredentials\FacialCredentialSynchronizationAttemptStatus;
use App\Modules\Operations\Domain\FacialCredentials\FacialCredentialSynchronizationStatus;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoDerivativeStatus;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoStatus;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialCredentialSynchronizationRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialPhotoDerivativeRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialPhotoRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use App\Modules\Operations\UI\Filament\Resources\VisitorRecords\Actions\ExecuteVisitorFacialCredentialSynchronizationAction;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

final class ExecuteVisitorFacialCredentialSynchronizationActionTest extends TestCase
{
    public function test_it_has_the_expected_filament_action_name(): void
    {
        self::assertSame(
            'executeFacialCredentialSynchronization',
            ExecuteVisitorFacialCredentialSynchronizationAction::make()
                ->getName()
        );
    }

    public function test_it_executes_only_the_selected_eligible_intention(): void
    {
        [
            $visitor,
            $synchronization,
        ] = $this->fixture();

        $repository = new class implements ExecuteFacialCredentialSynchronizationRepository
        {
            public ?ExecuteFacialCredentialSynchronizationCommand $received = null;

            public function execute(
                ExecuteFacialCredentialSynchronizationCommand $command
            ): ExecuteFacialCredentialSynchronizationResult {
                $this->received = $command;

                return ExecuteFacialCredentialSynchronizationResult::executed(
                    synchronizationId: $command->synchronizationId,
                    attemptNumber: 1,
                    status: FacialCredentialSynchronizationAttemptStatus::Succeeded,
                    provider: 'simulator',
                    scenario: 'succeeded',
                    failureCode: null,
                );
            }
        };

        $outcome =
            ExecuteVisitorFacialCredentialSynchronizationAction::executeEligible(
                visitor: $visitor,
                synchronizationId: (string) $synchronization->getKey(),
                executor: new ExecuteFacialCredentialSynchronizationUseCase(
                    $repository
                ),
            );

        self::assertInstanceOf(
            ExecuteFacialCredentialSynchronizationResult::class,
            $outcome['result']
        );

        self::assertSame(
            $synchronization->getKey(),
            $repository->received?->synchronizationId
        );
    }

    public function test_it_refuses_execution_when_the_simulator_is_not_ready(): void
    {
        [
            $visitor,
            $synchronization,
        ] = $this->fixture(
            simulatorReady: false
        );

        $repository = new class implements ExecuteFacialCredentialSynchronizationRepository
        {
            public bool $called = false;

            public function execute(
                ExecuteFacialCredentialSynchronizationCommand $command
            ): ExecuteFacialCredentialSynchronizationResult {
                $this->called = true;

                return ExecuteFacialCredentialSynchronizationResult::executed(
                    synchronizationId: $command->synchronizationId,
                    attemptNumber: 1,
                    status: FacialCredentialSynchronizationAttemptStatus::Succeeded,
                    provider: 'simulator',
                    scenario: 'succeeded',
                    failureCode: null,
                );
            }
        };

        $outcome =
            ExecuteVisitorFacialCredentialSynchronizationAction::executeEligible(
                visitor: $visitor,
                synchronizationId: (string) $synchronization->getKey(),
                executor: new ExecuteFacialCredentialSynchronizationUseCase(
                    $repository
                ),
            );

        self::assertNull(
            $outcome['result']
        );

        self::assertFalse(
            $repository->called
        );

        self::assertStringContainsString(
            'desativada',
            $outcome['message']
        );
    }

    public function test_it_refuses_an_ineligible_or_stale_intention(): void
    {
        [
            $visitor,
        ] = $this->fixture();

        $repository = new class implements ExecuteFacialCredentialSynchronizationRepository
        {
            public bool $called = false;

            public function execute(
                ExecuteFacialCredentialSynchronizationCommand $command
            ): ExecuteFacialCredentialSynchronizationResult {
                $this->called = true;

                return ExecuteFacialCredentialSynchronizationResult::executed(
                    synchronizationId: $command->synchronizationId,
                    attemptNumber: 1,
                    status: FacialCredentialSynchronizationAttemptStatus::Succeeded,
                    provider: 'simulator',
                    scenario: 'succeeded',
                    failureCode: null,
                );
            }
        };

        $outcome =
            ExecuteVisitorFacialCredentialSynchronizationAction::executeEligible(
                visitor: $visitor,
                synchronizationId: '90000000-0000-4000-8000-000000000099',
                executor: new ExecuteFacialCredentialSynchronizationUseCase(
                    $repository
                ),
            );

        self::assertNull(
            $outcome['result']
        );

        self::assertFalse(
            $repository->called
        );

        self::assertStringContainsString(
            'não está mais pendente',
            $outcome['message']
        );
    }

    /**
     * @return array{
     *     0: VisitorRecord,
     *     1: FacialCredentialSynchronizationRecord
     * }
     */
    private function fixture(
        bool $simulatorReady = true
    ): array {
        config()->set(
            'facial_photos.intelbras_derivative.profile',
            'intelbras_facial_credential'
        );

        config()->set(
            'facial_photos.intelbras_derivative.policy_version',
            'intelbras-facial-credential-v1'
        );

        config()->set(
            'intelbras_facial_synchronization.provider',
            $simulatorReady
                ? 'simulator'
                : 'disabled'
        );

        config()->set(
            'intelbras_facial_synchronization.simulator.enabled',
            $simulatorReady
        );

        config()->set(
            'intelbras_facial_synchronization.simulator.allowed_environments',
            ['testing']
        );

        config()->set(
            'intelbras_facial_synchronization.simulator.scenario',
            'succeeded'
        );

        $visitor = (new VisitorRecord)->forceFill([
            'id' => '40000000-0000-4000-8000-000000000001',
            'tenant_id' => '30000000-0000-4000-8000-000000000001',
            'organization_id' => '50000000-0000-4000-8000-000000000001',
            'name' => 'VISITANTE SINTÉTICO A5G.3-A2',
        ]);

        $photo = (new FacialPhotoRecord)->forceFill([
            'id' => '60000000-0000-4000-8000-000000000001',
            'visitor_id' => $visitor->getKey(),
            'status' => FacialPhotoStatus::Approved,
            'sha256' => str_repeat('a', 64),
        ]);

        $derivative =
            (new FacialPhotoDerivativeRecord)->forceFill([
                'id' => '70000000-0000-4000-8000-000000000001',
                'facial_photo_id' => $photo->getKey(),
                'status' => FacialPhotoDerivativeStatus::Ready,
                'profile' => 'intelbras_facial_credential',
                'policy_version' => 'intelbras-facial-credential-v1',
                'source_sha256' => str_repeat('a', 64),
            ]);

        $photo->setRelation(
            'derivatives',
            new Collection([
                $derivative,
            ])
        );

        $visitor->setRelation(
            'latestFacialPhoto',
            $photo
        );

        $synchronization =
            (new FacialCredentialSynchronizationRecord)->forceFill([
                'id' => '10000000-0000-4000-8000-000000000001',
                'tenant_id' => $visitor->tenant_id,
                'organization_id' => $visitor->organization_id,
                'visitor_id' => $visitor->getKey(),
                'facial_photo_id' => $photo->getKey(),
                'facial_photo_derivative_id' => $derivative->getKey(),
                'access_device_id' => '20000000-0000-4000-8000-000000000001',
                'operation' => 'register',
                'status' => FacialCredentialSynchronizationStatus::Pending,
                'version' => 1,
            ]);

        $visitor->setRelation(
            'facialCredentialSynchronizations',
            new Collection([
                $synchronization,
            ])
        );

        return [
            $visitor,
            $synchronization,
        ];
    }
}
