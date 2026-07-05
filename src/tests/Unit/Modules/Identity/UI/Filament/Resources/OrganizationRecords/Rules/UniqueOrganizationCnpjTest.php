<?php

namespace Tests\Unit\Modules\Identity\UI\Filament\Resources\OrganizationRecords\Rules;

use App\Modules\Identity\UI\Filament\Resources\OrganizationRecords\Rules\UniqueOrganizationCnpj;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class UniqueOrganizationCnpjTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_rejects_an_existing_cnpj_even_when_input_is_formatted(): void
    {
        DB::table('organizations')->insert([
            'id' => (string) Str::uuid(),
            'display_name' => 'AGRONORTE TOCANTINÓPOLIS',
            'legal_name' => 'AGRONORTE NUTRICAO ANIMAL LTDA',
            'cnpj' => '13291693000241',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $message = null;

        (new UniqueOrganizationCnpj)->validate(
            attribute: 'cnpj',
            value: '13.291.693/0002-41',
            fail: function (string $error) use (&$message): void {
                $message = $error;
            },
        );

        $this->assertSame('Este CNPJ já está cadastrado.', $message);
    }

    public function test_it_allows_the_same_cnpj_when_ignoring_the_current_organization(): void
    {
        $organizationId = (string) Str::uuid();

        DB::table('organizations')->insert([
            'id' => $organizationId,
            'display_name' => 'AGRONORTE TOCANTINÓPOLIS',
            'legal_name' => 'AGRONORTE NUTRICAO ANIMAL LTDA',
            'cnpj' => '13291693000241',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $message = null;

        (new UniqueOrganizationCnpj($organizationId))->validate(
            attribute: 'cnpj',
            value: '13.291.693/0002-41',
            fail: function (string $error) use (&$message): void {
                $message = $error;
            },
        );

        $this->assertNull($message);
    }
}
