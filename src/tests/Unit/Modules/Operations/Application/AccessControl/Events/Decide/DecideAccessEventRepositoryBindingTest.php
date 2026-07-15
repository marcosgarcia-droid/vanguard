<?php

namespace Tests\Unit\Modules\Operations\Application\AccessControl\Events\Decide;

use App\Modules\Operations\Application\AccessControl\Events\Decide\DecideAccessEventRepository;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\EloquentDecideAccessEventRepository;
use Tests\TestCase;

class DecideAccessEventRepositoryBindingTest extends TestCase
{
    public function test_it_resolves_the_eloquent_repository(): void
    {
        $this->assertInstanceOf(
            EloquentDecideAccessEventRepository::class,
            app(
                DecideAccessEventRepository::class
            )
        );
    }
}
