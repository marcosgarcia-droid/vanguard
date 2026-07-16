<?php

namespace Tests\Unit\Modules\Operations\Application\AccessControl\Events\ManualAssociate;

use App\Modules\Operations\Application\AccessControl\Events\ManualAssociate\ManualAssociateAccessEventRepository;
use App\Modules\Operations\Infrastructure\Persistence\Eloquent\EloquentManualAssociateAccessEventRepository;
use Tests\TestCase;

class ManualAssociateAccessEventRepositoryBindingTest extends TestCase
{
    public function test_it_resolves_the_eloquent_repository(): void
    {
        $this->assertInstanceOf(
            EloquentManualAssociateAccessEventRepository::class,
            app(ManualAssociateAccessEventRepository::class)
        );
    }
}
