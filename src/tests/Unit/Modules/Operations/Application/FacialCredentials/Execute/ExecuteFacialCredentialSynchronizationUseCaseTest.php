<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Application\FacialCredentials\Execute;

use App\Modules\Operations\Application\FacialCredentials\Execute\ExecuteFacialCredentialSynchronizationCommand;
use App\Modules\Operations\Application\FacialCredentials\Execute\ExecuteFacialCredentialSynchronizationReason;
use App\Modules\Operations\Application\FacialCredentials\Execute\ExecuteFacialCredentialSynchronizationRepository;
use App\Modules\Operations\Application\FacialCredentials\Execute\ExecuteFacialCredentialSynchronizationResult;
use App\Modules\Operations\Application\FacialCredentials\Execute\ExecuteFacialCredentialSynchronizationUseCase;
use App\Modules\Operations\Domain\FacialCredentials\FacialCredentialSynchronizationAttemptStatus;
use Tests\TestCase;

final class ExecuteFacialCredentialSynchronizationUseCaseTest extends TestCase
{
    public function test_it_delegates_execution_to_the_repository(): void
    {
        $expected =
            ExecuteFacialCredentialSynchronizationResult::executed(
                synchronizationId: 'sync-synthetic-001',
                attemptNumber: 1,
                status: FacialCredentialSynchronizationAttemptStatus::Blocked,
                provider: 'disabled',
                scenario: null,
                failureCode: 'provider_disabled',
            );

        $repository =
            new InMemoryExecuteFacialCredentialSynchronizationRepository(
                $expected
            );

        $result = (
            new ExecuteFacialCredentialSynchronizationUseCase(
                $repository
            )
        )->execute(
            new ExecuteFacialCredentialSynchronizationCommand(
                'sync-synthetic-001'
            )
        );

        self::assertSame(
            $expected,
            $result
        );

        self::assertSame(
            1,
            $repository->calls
        );
    }

    public function test_safe_result_contains_no_fingerprint_or_person_data(): void
    {
        $result =
            ExecuteFacialCredentialSynchronizationResult::executed(
                synchronizationId: 'sync-synthetic-001',
                attemptNumber: 1,
                status: FacialCredentialSynchronizationAttemptStatus::Succeeded,
                provider: 'simulator',
                scenario: 'succeeded',
                failureCode: null,
            );

        $serialized = json_encode(
            $result->toSafeArray(),
            JSON_THROW_ON_ERROR
        );

        self::assertStringNotContainsString(
            'fingerprint',
            $serialized
        );

        self::assertStringNotContainsString(
            'sha256',
            $serialized
        );

        self::assertStringNotContainsString(
            'VISITANTE SINTÉTICO',
            $serialized
        );
    }

    public function test_not_found_result_has_no_attempt(): void
    {
        $result =
            ExecuteFacialCredentialSynchronizationResult::withoutAttempt(
                ExecuteFacialCredentialSynchronizationReason::SynchronizationNotFound
            );

        self::assertFalse(
            $result->reason->isSuccessful()
        );

        self::assertNull(
            $result->attemptNumber
        );

        self::assertNull(
            $result->status
        );
    }
}

final class InMemoryExecuteFacialCredentialSynchronizationRepository implements ExecuteFacialCredentialSynchronizationRepository
{
    public int $calls = 0;

    public function __construct(
        private readonly ExecuteFacialCredentialSynchronizationResult $result
    ) {}

    public function execute(
        ExecuteFacialCredentialSynchronizationCommand $command
    ): ExecuteFacialCredentialSynchronizationResult {
        $this->calls++;

        return $this->result;
    }
}
