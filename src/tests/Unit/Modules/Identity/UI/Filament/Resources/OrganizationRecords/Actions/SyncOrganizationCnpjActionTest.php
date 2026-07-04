<?php

namespace Tests\Unit\Modules\Identity\UI\Filament\Resources\OrganizationRecords\Actions;

use App\Modules\Identity\UI\Filament\Resources\OrganizationRecords\Actions\SyncOrganizationCnpjAction;
use Filament\Actions\Action;
use PHPUnit\Framework\TestCase;

class SyncOrganizationCnpjActionTest extends TestCase
{
    public function test_it_creates_the_sync_organization_cnpj_action(): void
    {
        $action = SyncOrganizationCnpjAction::make();

        $this->assertInstanceOf(Action::class, $action);
    }
}
