<?php

namespace Tests\Unit\Modules\Identity\Infrastructure\Persistence\Eloquent;

use App\Modules\Identity\Application\Organizations\CnpjLookup\CnpjLookupSyncRepository;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EloquentCnpjLookupSyncRepository;
use Tests\TestCase;

class EloquentCnpjLookupSyncRepositoryBindingTest extends TestCase
{
    public function test_it_resolves_the_cnpj_lookup_sync_repository_contract(): void
    {
        $repository = $this->app->make(CnpjLookupSyncRepository::class);

        $this->assertInstanceOf(EloquentCnpjLookupSyncRepository::class, $repository);
    }
}
