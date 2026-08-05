<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Operations\Infrastructure\Persistence\Eloquent;

use App\Modules\Operations\Infrastructure\Persistence\Eloquent\AccessDeviceRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\FacialCredentialSynchronizationRecord;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\VisitorRecord;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tests\TestCase;

final class FacialCredentialSynchronizationRelationshipTest extends TestCase
{
    public function test_visitor_exposes_its_facial_synchronizations(): void
    {
        $relation = (
            new VisitorRecord
        )->facialCredentialSynchronizations();

        self::assertInstanceOf(
            HasMany::class,
            $relation
        );

        self::assertInstanceOf(
            FacialCredentialSynchronizationRecord::class,
            $relation->getRelated()
        );

        self::assertSame(
            'visitor_id',
            $relation->getForeignKeyName()
        );

        self::assertSame(
            'id',
            $relation->getLocalKeyName()
        );
    }

    public function test_device_exposes_its_facial_synchronizations(): void
    {
        $relation = (
            new AccessDeviceRecord
        )->facialCredentialSynchronizations();

        self::assertInstanceOf(
            HasMany::class,
            $relation
        );

        self::assertInstanceOf(
            FacialCredentialSynchronizationRecord::class,
            $relation->getRelated()
        );

        self::assertSame(
            'access_device_id',
            $relation->getForeignKeyName()
        );

        self::assertSame(
            'id',
            $relation->getLocalKeyName()
        );
    }
}
