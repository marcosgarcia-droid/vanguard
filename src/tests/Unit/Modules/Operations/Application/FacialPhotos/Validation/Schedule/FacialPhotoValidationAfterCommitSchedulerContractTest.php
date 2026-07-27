<?php

namespace Tests\Unit\Modules\Operations\Application\FacialPhotos\Validation\Schedule;

use App\Modules\Operations\Application\FacialPhotos\Registration\RegisterVisitorFacialPhotoResult;
use App\Modules\Operations\Application\FacialPhotos\Validation\Schedule\FacialPhotoValidationAfterCommitScheduler;
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
            2,
            $parameters
        );

        $this->assertSame(
            RegisterVisitorFacialPhotoResult::class,
            (string) $parameters[0]->getType()
        );

        $operatorType = $parameters[1]->getType();

        $this->assertInstanceOf(
            ReflectionNamedType::class,
            $operatorType
        );

        $this->assertSame(
            'int',
            $operatorType->getName()
        );

        $this->assertTrue(
            $operatorType->allowsNull()
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
}
