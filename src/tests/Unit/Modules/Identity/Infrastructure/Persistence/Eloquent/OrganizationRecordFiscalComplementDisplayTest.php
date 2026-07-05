<?php

namespace Tests\Unit\Modules\Identity\Infrastructure\Persistence\Eloquent;

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationCnaeActivityRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationMemberRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationTaxRegimeRecord;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\TestCase;

class OrganizationRecordFiscalComplementDisplayTest extends TestCase
{
    public function test_it_exposes_cnae_members_and_tax_regime_display_fields(): void
    {
        $primaryCnae = new OrganizationCnaeActivityRecord;
        $primaryCnae->code = '1066000';
        $primaryCnae->description = 'Fabricação de alimentos para animais';
        $primaryCnae->is_primary = true;

        $secondaryCnae = new OrganizationCnaeActivityRecord;
        $secondaryCnae->code = '4623109';
        $secondaryCnae->description = 'Comércio atacadista de alimentos para animais';
        $secondaryCnae->is_primary = false;

        $member = new OrganizationMemberRecord;
        $member->name = 'Sócio Teste';
        $member->qualification_name = 'Administrador';
        $member->is_legal_representative = true;

        $taxRegime = new OrganizationTaxRegimeRecord;
        $taxRegime->is_current = true;
        $taxRegime->is_simples_nacional = false;
        $taxRegime->is_mei = false;
        $taxRegime->tax_regime = 'Lucro Presumido';

        $organization = new OrganizationRecord;
        $organization->setRelation('cnaeActivities', new Collection([
            $primaryCnae,
            $secondaryCnae,
        ]));
        $organization->setRelation('members', new Collection([
            $member,
        ]));
        $organization->setRelation('taxRegimes', new Collection([
            $taxRegime,
        ]));

        $this->assertSame(
            '1066-0/00 - Fabricação de alimentos para animais',
            $organization->primary_cnae_display,
        );

        $this->assertSame(
            '4623-1/09 - Comércio atacadista de alimentos para animais',
            $organization->secondary_cnaes_display,
        );

        $this->assertSame(
            'Sócio Teste - Administrador - Representante legal',
            $organization->members_display,
        );

        $this->assertSame(
            'Simples Nacional: Não; MEI: Não; Regime: Lucro Presumido',
            $organization->current_tax_regime_display,
        );
    }
}
