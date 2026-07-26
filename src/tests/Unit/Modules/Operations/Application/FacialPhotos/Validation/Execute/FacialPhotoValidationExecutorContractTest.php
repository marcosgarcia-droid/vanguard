<?php

namespace Tests\Unit\Modules\Operations\Application\FacialPhotos\Validation\Execute;

use App\Modules\Operations\Application\FacialPhotos\Validation\Execute\ExecuteFacialPhotoValidationCommand;
use App\Modules\Operations\Application\FacialPhotos\Validation\Execute\ExecuteFacialPhotoValidationException;
use App\Modules\Operations\Application\FacialPhotos\Validation\Execute\ExecuteFacialPhotoValidationResult;
use App\Modules\Operations\Application\FacialPhotos\Validation\Execute\FacialPhotoValidationExecutor;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class FacialPhotoValidationExecutorContractTest extends TestCase
{
    public function test_it_defines_the_executor_contract(): void
    {
        $contract = new ReflectionClass(
            FacialPhotoValidationExecutor::class
        );

        $method = new ReflectionMethod(
            FacialPhotoValidationExecutor::class,
            'execute'
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
            ExecuteFacialPhotoValidationCommand::class,
            (string) $parameters[0]->getType()
        );

        $this->assertSame(
            ExecuteFacialPhotoValidationResult::class,
            (string) $method->getReturnType()
        );
    }

    public function test_it_exposes_a_safe_disabled_message(): void
    {
        $this->assertSame(
            'A validação facial está desativada neste ambiente.',
            ExecuteFacialPhotoValidationException::validationDisabled()
                ->getMessage()
        );
    }
}
