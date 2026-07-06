<?php

namespace Tests\Unit\Modules\Identity\Infrastructure\Persistence\Eloquent;

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\ClassificationOptionRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassificationOptionRecordTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_normalizes_category_and_code_and_relates_to_tenant(): void
    {
        $tenant = TenantRecord::query()->create([
            'name' => 'AGRONORTE',
            'status' => 'active',
        ]);

        $classification = ClassificationOptionRecord::query()->create([
            'tenant_id' => $tenant->id,
            'category' => 'Perfil de Parceiro',
            'code' => 'Prestador de Serviço',
            'name' => 'Prestador de serviço',
            'status' => 'active',
            'sort_order' => 10,
            'is_system' => true,
        ]);

        $loaded = ClassificationOptionRecord::query()
            ->with('tenant')
            ->findOrFail($classification->id);

        $this->assertSame($tenant->id, $loaded->tenant->id);
        $this->assertSame('perfil_de_parceiro', $loaded->category);
        $this->assertSame('prestador_de_servico', $loaded->code);
        $this->assertSame('Ativa', $loaded->status_display);
        $this->assertTrue($loaded->is_system);
    }
}
