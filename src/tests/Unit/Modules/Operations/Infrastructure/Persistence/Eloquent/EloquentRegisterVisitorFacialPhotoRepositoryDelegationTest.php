<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Infrastructure\Persistence\Eloquent;

use App\Modules\Operations\Application\FacialPhotos\Registration\RegisterFacialPhotoRepository;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\EloquentRegisterVisitorFacialPhotoRepository;
use ReflectionClass;
use ReflectionNamedType;
use Tests\TestCase;

final class EloquentRegisterVisitorFacialPhotoRepositoryDelegationTest extends TestCase
{
    public function test_legacy_repository_delegates_to_the_generic_core_without_persistence_logic(): void
    {
        $reflection = new ReflectionClass(
            EloquentRegisterVisitorFacialPhotoRepository::class
        );

        $constructor = $reflection->getConstructor();

        self::assertNotNull(
            $constructor
        );

        $parameters = $constructor->getParameters();

        self::assertCount(
            1,
            $parameters
        );

        $type = $parameters[0]->getType();

        self::assertInstanceOf(
            ReflectionNamedType::class,
            $type
        );

        self::assertSame(
            RegisterFacialPhotoRepository::class,
            $type->getName()
        );

        $fileName = $reflection->getFileName();

        self::assertIsString(
            $fileName
        );

        $source = file_get_contents(
            $fileName
        );

        self::assertIsString(
            $source
        );

        self::assertStringContainsString(
            'RegisterFacialPhotoCommand',
            $source
        );

        self::assertStringContainsString(
            'FacialPhotoSubjectType::Visitor',
            $source
        );

        self::assertStringNotContainsString(
            'DB::transaction',
            $source
        );

        self::assertStringNotContainsString(
            'copyMedia(',
            $source
        );

        self::assertStringNotContainsString(
            'FacialPhotoConfirmationConsumptionRecord',
            $source
        );

        self::assertStringNotContainsString(
            'AnalyzeFacialPhotoUseCase',
            $source
        );
    }
}
