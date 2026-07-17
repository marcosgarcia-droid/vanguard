<?php

namespace Tests\Unit\Modules\Operations\Application\AccessControl\Events\ContinueManualAssociation;

use App\Modules\Operations\Application\AccessControl\Events\ContinueManualAssociation\ContinueManuallyAssociatedAccessEventFlowRepository;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\EloquentContinueManuallyAssociatedAccessEventFlowRepository;
use Tests\TestCase;

class ContinueManuallyAssociatedAccessEventFlowRepositoryBindingTest extends TestCase
{
    public function test_it_resolves_the_eloquent_repository(): void
    {
        $this->assertInstanceOf(
            EloquentContinueManuallyAssociatedAccessEventFlowRepository::class,
            app(
                ContinueManuallyAssociatedAccessEventFlowRepository::class
            )
        );
    }
}
