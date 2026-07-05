<?php

namespace Tests\Unit\Modules\Identity\UI\Filament\Resources\OrganizationRecords\Actions;

use App\Modules\Identity\UI\Filament\Resources\OrganizationRecords\Actions\CorrectOrganizationCnpjAction;
use Filament\Actions\Action;
use PHPUnit\Framework\TestCase;

class CorrectOrganizationCnpjActionTest extends TestCase
{
    public function test_it_creates_the_correct_organization_cnpj_action(): void
    {
        $action = CorrectOrganizationCnpjAction::make();

        $this->assertInstanceOf(Action::class, $action);
        $this->assertSame('correctOrganizationCnpj', $action->getName());
    }
}
