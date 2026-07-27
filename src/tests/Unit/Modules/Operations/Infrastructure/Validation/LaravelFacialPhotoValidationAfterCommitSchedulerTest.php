<?php

namespace Tests\Unit\Modules\Operations\Infrastructure\Validation;

use App\Modules\Operations\Application\FacialPhotos\Registration\RegisterVisitorFacialPhotoResult;
use App\Modules\Operations\Application\FacialPhotos\Validation\Execute\ExecuteFacialPhotoValidationCommand;
use App\Modules\Operations\Application\FacialPhotos\Validation\Execute\ExecuteFacialPhotoValidationResult;
use App\Modules\Operations\Application\FacialPhotos\Validation\Execute\FacialPhotoValidationExecutor;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoStatus;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoStatusTransitionPolicy;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoTechnicalAnalysis;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoTechnicalIssue;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoValidationDecision;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoValidationResult;
use App\Modules\Operations\Infrastructure\Validation\LaravelFacialPhotoValidationAfterCommitScheduler;
use DateTimeImmutable;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

final class LaravelFacialPhotoValidationAfterCommitSchedulerTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = DB::connection();

        $this->rollbackOpenTransactions();
    }

    protected function tearDown(): void
    {
        $this->rollbackOpenTransactions();

        parent::tearDown();
    }

    public function test_it_does_not_schedule_when_the_feature_is_disabled(): void
    {
        $executor = $this->createMock(
            FacialPhotoValidationExecutor::class
        );

        $executor
            ->expects($this->never())
            ->method('execute');

        $handler = $this->createMock(
            ExceptionHandler::class
        );

        $this->connection->beginTransaction();

        $scheduled = $this
            ->scheduler(
                enabled: false,
                executor: $executor,
                handler: $handler,
            )
            ->schedule(
                registration: $this->registration(),
                operatorUserId: 42,
            );

        $this->assertFalse(
            $scheduled
        );

        $this->connection->commit();
    }

    public function test_it_does_not_schedule_a_technically_rejected_photo(): void
    {
        $executor = $this->createMock(
            FacialPhotoValidationExecutor::class
        );

        $executor
            ->expects($this->never())
            ->method('execute');

        $handler = $this->createMock(
            ExceptionHandler::class
        );

        $scheduled = $this
            ->scheduler(
                enabled: true,
                executor: $executor,
                handler: $handler,
            )
            ->schedule(
                registration: $this->registration(
                    FacialPhotoStatus::Rejected
                ),
                operatorUserId: 42,
            );

        $this->assertFalse(
            $scheduled
        );
    }

    public function test_it_executes_immediately_without_an_open_transaction(): void
    {
        $executor = $this->createMock(
            FacialPhotoValidationExecutor::class
        );

        $executor
            ->expects($this->once())
            ->method('execute')
            ->with(
                $this->callback(
                    function (
                        ExecuteFacialPhotoValidationCommand $command
                    ): bool {
                        $this->assertSame(
                            'photo-scheduler-1',
                            $command->photoId
                        );

                        $this->assertSame(
                            42,
                            $command->operatorUserId
                        );

                        return true;
                    }
                )
            )
            ->willReturn(
                $this->executionResult()
            );

        $handler = $this->createMock(
            ExceptionHandler::class
        );

        $scheduled = $this
            ->scheduler(
                enabled: true,
                executor: $executor,
                handler: $handler,
            )
            ->schedule(
                registration: $this->registration(),
                operatorUserId: 42,
            );

        $this->assertTrue(
            $scheduled
        );
    }

    public function test_it_defers_execution_until_the_transaction_commits(): void
    {
        $executed = false;

        $executor = $this->createMock(
            FacialPhotoValidationExecutor::class
        );

        $executor
            ->expects($this->once())
            ->method('execute')
            ->willReturnCallback(
                function (
                    ExecuteFacialPhotoValidationCommand $command
                ) use (&$executed): ExecuteFacialPhotoValidationResult {
                    $this->assertSame(
                        'photo-scheduler-1',
                        $command->photoId
                    );

                    $this->assertSame(
                        84,
                        $command->operatorUserId
                    );

                    $executed = true;

                    return $this->executionResult();
                }
            );

        $handler = $this->createMock(
            ExceptionHandler::class
        );

        $this->connection->beginTransaction();

        $scheduled = $this
            ->scheduler(
                enabled: true,
                executor: $executor,
                handler: $handler,
            )
            ->schedule(
                registration: $this->registration(),
                operatorUserId: 84,
            );

        $this->assertTrue(
            $scheduled
        );

        $this->assertFalse(
            $executed
        );

        $this->connection->commit();

        $this->assertTrue(
            $executed
        );
    }

    public function test_it_does_not_execute_when_the_transaction_rolls_back(): void
    {
        $executor = $this->createMock(
            FacialPhotoValidationExecutor::class
        );

        $executor
            ->expects($this->never())
            ->method('execute');

        $handler = $this->createMock(
            ExceptionHandler::class
        );

        $this->connection->beginTransaction();

        $scheduled = $this
            ->scheduler(
                enabled: true,
                executor: $executor,
                handler: $handler,
            )
            ->schedule(
                registration: $this->registration(),
                operatorUserId: 42,
            );

        $this->assertTrue(
            $scheduled
        );

        $this->connection->rollBack();
    }

    public function test_it_reports_an_immediate_failure_without_propagating_it(): void
    {
        $failure = new RuntimeException(
            'Falha sintética imediata.'
        );

        $executor = $this->createMock(
            FacialPhotoValidationExecutor::class
        );

        $executor
            ->expects($this->once())
            ->method('execute')
            ->willThrowException(
                $failure
            );

        $handler = $this->createMock(
            ExceptionHandler::class
        );

        $handler
            ->expects($this->once())
            ->method('report')
            ->with($failure);

        $scheduled = $this
            ->scheduler(
                enabled: true,
                executor: $executor,
                handler: $handler,
            )
            ->schedule(
                registration: $this->registration(),
                operatorUserId: 42,
            );

        $this->assertTrue(
            $scheduled
        );
    }

    public function test_it_reports_a_deferred_failure_without_breaking_commit(): void
    {
        $failure = new RuntimeException(
            'Falha sintética pós-commit.'
        );

        $executor = $this->createMock(
            FacialPhotoValidationExecutor::class
        );

        $executor
            ->expects($this->once())
            ->method('execute')
            ->willThrowException(
                $failure
            );

        $handler = $this->createMock(
            ExceptionHandler::class
        );

        $handler
            ->expects($this->once())
            ->method('report')
            ->with($failure);

        $this->connection->beginTransaction();

        $scheduled = $this
            ->scheduler(
                enabled: true,
                executor: $executor,
                handler: $handler,
            )
            ->schedule(
                registration: $this->registration(),
                operatorUserId: 42,
            );

        $this->assertTrue(
            $scheduled
        );

        $this->connection->commit();

        $this->assertSame(
            0,
            $this->connection->transactionLevel()
        );
    }

    public function test_it_reports_a_transaction_level_failure_without_propagating_it(): void
    {
        $failure = new RuntimeException(
            'Falha sintética ao consultar a transação.'
        );

        $connection = $this->createMock(
            Connection::class
        );

        $connection
            ->expects($this->once())
            ->method('transactionLevel')
            ->willThrowException(
                $failure
            );

        $connection
            ->expects($this->never())
            ->method('afterCommit');

        $executor = $this->createMock(
            FacialPhotoValidationExecutor::class
        );

        $executor
            ->expects($this->never())
            ->method('execute');

        $handler = $this->createMock(
            ExceptionHandler::class
        );

        $handler
            ->expects($this->once())
            ->method('report')
            ->with($failure);

        $scheduled = $this
            ->scheduler(
                enabled: true,
                executor: $executor,
                handler: $handler,
                connection: $connection,
            )
            ->schedule(
                registration: $this->registration(),
                operatorUserId: 42,
            );

        $this->assertFalse(
            $scheduled
        );
    }

    public function test_it_reports_an_after_commit_registration_failure_without_propagating_it(): void
    {
        $failure = new RuntimeException(
            'Falha sintética ao registrar o pós-commit.'
        );

        $connection = $this->createMock(
            Connection::class
        );

        $connection
            ->expects($this->once())
            ->method('transactionLevel')
            ->willReturn(1);

        $connection
            ->expects($this->once())
            ->method('afterCommit')
            ->with(
                $this->callback(
                    static fn (mixed $callback): bool => is_callable($callback)
                )
            )
            ->willThrowException(
                $failure
            );

        $executor = $this->createMock(
            FacialPhotoValidationExecutor::class
        );

        $executor
            ->expects($this->never())
            ->method('execute');

        $handler = $this->createMock(
            ExceptionHandler::class
        );

        $handler
            ->expects($this->once())
            ->method('report')
            ->with($failure);

        $scheduled = $this
            ->scheduler(
                enabled: true,
                executor: $executor,
                handler: $handler,
                connection: $connection,
            )
            ->schedule(
                registration: $this->registration(),
                operatorUserId: 42,
            );

        $this->assertFalse(
            $scheduled
        );
    }

    public function test_it_swallows_a_reporting_failure_when_scheduling_cannot_continue(): void
    {
        $schedulingFailure = new RuntimeException(
            'Falha sintética ao consultar a transação.'
        );

        $reportingFailure = new RuntimeException(
            'Falha sintética no reporter.'
        );

        $connection = $this->createMock(
            Connection::class
        );

        $connection
            ->expects($this->once())
            ->method('transactionLevel')
            ->willThrowException(
                $schedulingFailure
            );

        $executor = $this->createMock(
            FacialPhotoValidationExecutor::class
        );

        $executor
            ->expects($this->never())
            ->method('execute');

        $handler = $this->createMock(
            ExceptionHandler::class
        );

        $handler
            ->expects($this->once())
            ->method('report')
            ->with($schedulingFailure)
            ->willThrowException(
                $reportingFailure
            );

        $scheduled = $this
            ->scheduler(
                enabled: true,
                executor: $executor,
                handler: $handler,
                connection: $connection,
            )
            ->schedule(
                registration: $this->registration(),
                operatorUserId: 42,
            );

        $this->assertFalse(
            $scheduled
        );
    }

    private function scheduler(
        bool $enabled,
        FacialPhotoValidationExecutor $executor,
        ExceptionHandler $handler,
        ?Connection $connection = null,
    ): LaravelFacialPhotoValidationAfterCommitScheduler {
        return new LaravelFacialPhotoValidationAfterCommitScheduler(
            enabled: $enabled,
            executor: $executor,
            connection: $connection ?? $this->connection,
            exceptionHandler: $handler,
        );
    }

    private function registration(
        FacialPhotoStatus $status =
            FacialPhotoStatus::PendingValidation
    ): RegisterVisitorFacialPhotoResult {
        $passed = $status
            === FacialPhotoStatus::PendingValidation;

        return new RegisterVisitorFacialPhotoResult(
            photoId: 'photo-scheduler-1',
            status: $status,
            technicalAnalysis: new FacialPhotoTechnicalAnalysis(
                version: 'technical-v1',
                passed: $passed,
                metrics: [
                    'width' => 720,
                    'height' => 900,
                ],
                issues: $passed
                    ? []
                    : [
                        FacialPhotoTechnicalIssue::ResolutionTooLow,
                    ],
            ),
        );
    }

    private function executionResult(): ExecuteFacialPhotoValidationResult
    {
        $validation = new FacialPhotoValidationResult(
            validator: 'scheduler-test-validator',
            version: 'scheduler-test-v1',
            decision: FacialPhotoValidationDecision::Approved,
            faceCount: 1,
            metrics: [
                'confidence' => 0.99,
            ],
            issues: [],
        );

        $transition =
            (new FacialPhotoStatusTransitionPolicy)
                ->transition(
                    currentStatus: FacialPhotoStatus::PendingValidation,
                    decision: $validation->decision,
                );

        return new ExecuteFacialPhotoValidationResult(
            photoId: 'photo-scheduler-1',
            attemptId: 'attempt-scheduler-1',
            attemptNumber: 1,
            validation: $validation,
            transition: $transition,
            validatedAt: new DateTimeImmutable(
                '2026-07-26 15:00:00'
            ),
        );
    }

    private function rollbackOpenTransactions(): void
    {
        while (
            $this->connection->transactionLevel() > 0
        ) {
            $this->connection->rollBack();
        }
    }
}
