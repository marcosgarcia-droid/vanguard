<?php

namespace Tests\Unit\Modules\Operations\Application\AccessControl\Events\Reprocess;

use App\Modules\Operations\Application\AccessControl\Events\Reprocess\ReprocessAccessEventFlowRepository;
use App\Modules\Operations\Application\AccessControl\Events\Reprocess\ReprocessAccessEventFlowUseCase;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\EloquentReprocessAccessEventFlowRepository;
use Tests\TestCase;

class ReprocessAccessEventFlowRepositoryBindingTest extends TestCase
{
    public function test_it_resolves_the_repository_and_use_case(): void
    {
        $this->assertInstanceOf(
            EloquentReprocessAccessEventFlowRepository::class,
            app(ReprocessAccessEventFlowRepository::class)
        );

        $this->assertInstanceOf(
            ReprocessAccessEventFlowUseCase::class,
            app(ReprocessAccessEventFlowUseCase::class)
        );
    }
}
