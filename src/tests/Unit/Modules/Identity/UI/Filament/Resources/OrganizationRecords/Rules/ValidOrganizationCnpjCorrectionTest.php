<?php

namespace Tests\Unit\Modules\Identity\UI\Filament\Resources\OrganizationRecords\Rules;

use App\Modules\Identity\UI\Filament\Resources\OrganizationRecords\Rules\ValidOrganizationCnpjCorrection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ValidOrganizationCnpjCorrectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_rejects_an_incomplete_cnpj(): void
    {
        $messages = $this->validateRule(
            new ValidOrganizationCnpjCorrection,
            '13.291.693/0003-2',
        );

        $this->assertSame(['Informe um CNPJ válido.'], $messages);
    }

    public function test_it_rejects_an_invalid_cnpj_check_digit(): void
    {
        $messages = $this->validateRule(
            new ValidOrganizationCnpjCorrection,
            '13.291.693/0003-23',
        );

        $this->assertSame(['Informe um CNPJ válido.'], $messages);
    }

    public function test_it_rejects_a_cnpj_from_another_organization(): void
    {
        DB::table('organizations')->insert([
            'id' => (string) Str::uuid(),
            'status' => 'active',
            'legal_name' => 'Organização existente',
            'cnpj' => '13291693000241',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $messages = $this->validateRule(
            new ValidOrganizationCnpjCorrection(ignoreOrganizationId: (string) Str::uuid()),
            '13.291.693/0002-41',
        );

        $this->assertSame(['Este CNPJ já está cadastrado em outra organização.'], $messages);
    }

    public function test_it_allows_the_current_organization_cnpj(): void
    {
        $organizationId = (string) Str::uuid();

        DB::table('organizations')->insert([
            'id' => $organizationId,
            'status' => 'active',
            'legal_name' => 'Organização atual',
            'cnpj' => '13291693000241',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $messages = $this->validateRule(
            new ValidOrganizationCnpjCorrection(ignoreOrganizationId: $organizationId),
            '13.291.693/0002-41',
        );

        $this->assertSame([], $messages);
    }

    /**
     * @return list<string>
     */
    private function validateRule(ValidOrganizationCnpjCorrection $rule, mixed $value): array
    {
        $messages = [];

        $rule->validate(
            attribute: 'new_cnpj',
            value: $value,
            fail: function (string $message) use (&$messages): void {
                $messages[] = $message;
            },
        );

        return $messages;
    }
}
