<?php

namespace Tests\Unit\Modules\Identity\UI\Filament\Resources\UserRecords;

use App\Models\User;
use App\Modules\Identity\UI\Filament\Resources\UserRecords\UserRecordResource;
use Tests\TestCase;

class UserRecordResourceTest extends TestCase
{
    public function test_it_uses_the_user_model_and_portuguese_labels(): void
    {
        $this->assertSame(User::class, UserRecordResource::getModel());
        $this->assertSame('Usuários', UserRecordResource::getNavigationLabel());
        $this->assertSame('Acesso', UserRecordResource::getNavigationGroup());
        $this->assertSame('usuário', UserRecordResource::getModelLabel());
        $this->assertSame('usuários', UserRecordResource::getPluralModelLabel());
    }
}
