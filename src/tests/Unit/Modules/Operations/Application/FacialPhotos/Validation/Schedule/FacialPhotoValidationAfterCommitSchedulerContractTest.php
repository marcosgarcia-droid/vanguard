<?php

namespace Tests\Unit\Modules\Operations\Application\FacialPhotos\Validation\Schedule;

use App\Modules\Operations\Application\FacialPhotos\Validation\Schedule\FacialPhotoValidationAfterCommitScheduler;
use App\Modules\Operations\Application\FacialPhotos\Validation\Schedule\ScheduleFacialPhotoValidationCommand;
use App\Modules\Operations\Domain\FacialPhotos\FacialPhotoStatus;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

final class FacialPhotoValidationAfterCommitSchedulerContractTest extends TestCase
{
    public function test_it_defines_the_post_commit_scheduler_contract(): void
    {
        $contract = new ReflectionClass(
            FacialPhotoValidationAfterCommitScheduler::class
        );

        $method = new ReflectionMethod(
            FacialPhotoValidationAfterCommitScheduler::class,
            'schedule'
        );

        $parameters = $method->getParameters();

        $this->assertTrue(
            $contract->isInterface()
        );

        $this->assertCount(
            1,
            $parameters
        );

        $this->assertSame(
            ScheduleFacialPhotoValidationCommand::class,
            (string) $parameters[0]->getType()
        );

        $returnType = $method->getReturnType();

        $this->assertInstanceOf(
            ReflectionNamedType::class,
            $returnType
        );

        $this->assertSame(
            'bool',
            $returnType->getName()
        );
    }

    public function test_schedule_command_carries_subject_neutral_context(): void
    {
        $command = new ScheduleFacialPhotoValidationCommand(
            photoId: 'photo-neutral-1',
            status: FacialPhotoStatus::PendingValidation,
            operatorUserId: 42,
        );

        $this->assertSame(
            'photo-neutral-1',
            $command->photoId
        );

        $this->assertSame(
            FacialPhotoStatus::PendingValidation,
            $command->status
        );

        $this->assertSame(
            42,
            $command->operatorUserId
        );

        $this->assertTrue(
            $command->awaitsAdditionalValidation()
        );
    }
}
