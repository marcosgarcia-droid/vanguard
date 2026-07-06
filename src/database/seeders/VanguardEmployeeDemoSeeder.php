<?php

namespace Database\Seeders;

use App\Modules\Identity\Infrastructure\Persistence\Eloquent\EmployeeRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\OrganizationRecord;
use App\Modules\Identity\Infrastructure\Persistence\Eloquent\TenantRecord;
use Faker\Factory as FakerFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VanguardEmployeeDemoSeeder extends Seeder
{
    public function run(): void
    {
        $faker = FakerFactory::create('pt_BR');

        $tenant = TenantRecord::query()
            ->where('name', 'AGRONORTE')
            ->first();

        if (! $tenant) {
            $tenant = TenantRecord::query()->create([
                'id' => (string) Str::uuid(),
                'name' => 'AGRONORTE',
                'status' => 'active',
            ]);
        }

        $organizations = OrganizationRecord::query()
            ->where('tenant_id', $tenant->id)
            ->orderBy('display_name')
            ->get();

        if ($organizations->isEmpty()) {
            $organizations = collect([
                OrganizationRecord::query()->create([
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenant->id,
                    'status' => 'active',
                    'legal_name' => 'AGRONORTE DEMONSTRACAO LTDA',
                    'display_name' => 'AGRONORTE DEMO',
                    'unit_code' => 'DEMO-01',
                ]),
            ]);
        }

        DB::transaction(function () use ($faker, $tenant, $organizations): void {
            EmployeeRecord::withTrashed()
                ->where('tenant_id', $tenant->id)
                ->where('employee_code', 'like', 'DEMO-%')
                ->get()
                ->each(fn (EmployeeRecord $employee): ?bool => $employee->forceDelete());

            $departments = [
                'Administrativo',
                'Operações',
                'Logística',
                'Comercial',
                'Manutenção',
                'Segurança',
                'Facilities',
                'Gestão',
            ];

            $positions = [
                'Auxiliar Administrativo',
                'Assistente Operacional',
                'Analista Administrativo',
                'Operador',
                'Motorista',
                'Técnico de Manutenção',
                'Supervisor Operacional',
                'Gerente de Unidade',
                'Porteiro',
                'Encarregado',
            ];

            $managers = [];

            for ($i = 1; $i <= 5; $i++) {
                $managers[] = $this->createEmployee(
                    faker: $faker,
                    tenant: $tenant,
                    organization: $organizations->random(),
                    code: sprintf('DEMO-%04d', $i),
                    department: 'Gestão',
                    position: 'Gestor Responsável',
                    manager: null,
                    isManager: true,
                );
            }

            for ($i = 6; $i <= 50; $i++) {
                $this->createEmployee(
                    faker: $faker,
                    tenant: $tenant,
                    organization: $organizations->random(),
                    code: sprintf('DEMO-%04d', $i),
                    department: $faker->randomElement($departments),
                    position: $faker->randomElement($positions),
                    manager: $faker->randomElement($managers),
                    isManager: false,
                );
            }
        });
    }

    private function createEmployee(
        mixed $faker,
        TenantRecord $tenant,
        OrganizationRecord $organization,
        string $code,
        string $department,
        string $position,
        ?EmployeeRecord $manager,
        bool $isManager,
    ): EmployeeRecord {
        $gender = $faker->randomElement(['female', 'male']);

        $employee = EmployeeRecord::query()->create([
            'tenant_id' => $tenant->id,
            'organization_id' => $organization->id,
            'manager_employee_id' => $manager?->id,
            'employee_code' => $code,
            'full_name' => $gender === 'female'
                ? $faker->name('female')
                : $faker->name('male'),
            'preferred_name' => null,
            'gender' => $gender,
            'birth_date' => $faker->dateTimeBetween('-55 years', '-20 years')->format('Y-m-d'),
            'photo_disk' => 'local',
            'photo_path' => null,
            'department' => $department,
            'position' => $position,
            'employment_type' => $isManager ? 'employee' : $faker->randomElement(['employee', 'employee', 'employee', 'contractor', 'intern']),
            'status' => 'active',
            'hired_at' => $faker->dateTimeBetween('-8 years', '-1 month')->format('Y-m-d'),
            'terminated_at' => null,
            'notes' => 'Registro sintético gerado para demonstração do Vanguard.',
        ]);

        $employee->documents()->create([
            'type' => 'cpf',
            'number' => $this->validCpf(),
            'is_primary' => true,
        ]);

        $employee->documents()->create([
            'type' => 'rg',
            'number' => (string) $faker->numberBetween(1000000, 99999999),
            'issuing_authority' => $faker->randomElement(['SSP/MG', 'SSP/BA', 'SSP/TO', 'SSP/GO']),
            'issued_at' => $faker->dateTimeBetween('-20 years', '-2 years')->format('Y-m-d'),
            'is_primary' => false,
        ]);

        $employee->contacts()->create([
            'type' => 'mobile',
            'label' => 'Celular principal',
            'value' => $this->mobilePhone(),
            'is_primary' => true,
        ]);

        $employee->contacts()->create([
            'type' => 'phone',
            'label' => 'Telefone',
            'value' => $this->landlinePhone(),
            'is_primary' => false,
        ]);

        $employee->contacts()->create([
            'type' => 'email',
            'label' => 'E-mail profissional',
            'value' => strtolower(Str::slug($employee->full_name, '.')).'@vanguard.test',
            'is_primary' => true,
        ]);

        $employee->addresses()->create([
            'type' => 'residential',
            'postal_code' => $this->postalCode(),
            'street' => $faker->streetName(),
            'number' => (string) $faker->numberBetween(10, 9999),
            'complement' => $faker->optional(0.25)->randomElement(['Casa', 'Apartamento', 'Fundos']),
            'district' => $faker->word(),
            'city' => $faker->randomElement(['Montes Claros', 'Barreiras', 'Três Corações', 'Tocantinópolis', 'Goiânia']),
            'state' => $faker->randomElement(['MG', 'BA', 'TO', 'GO']),
            'country' => 'BR',
            'is_primary' => true,
        ]);

        $schedule = $employee->workSchedules()->create([
            'name' => 'Jornada administrativa',
            'type' => 'fixed',
            'weekly_workload_minutes' => 2640,
            'daily_workload_minutes' => 528,
            'tolerance_before_start_minutes' => 30,
            'tolerance_after_end_minutes' => 0,
            'valid_from' => now()->startOfYear()->toDateString(),
            'valid_until' => null,
            'is_active' => true,
            'notes' => 'Jornada sintética para demonstração.',
        ]);

        foreach ([1, 2, 3, 4, 5] as $weekday) {
            $schedule->days()->create([
                'weekday' => $weekday,
                'sequence' => 1,
                'is_working_day' => true,
                'work_starts_at' => '08:00:00',
                'work_ends_at' => '18:00:00',
                'ends_next_day' => false,
                'break_starts_at' => '12:00:00',
                'break_ends_at' => '13:12:00',
            ]);
        }

        return $employee;
    }

    private function validCpf(): string
    {
        $base = '';

        for ($i = 0; $i < 9; $i++) {
            $base .= (string) random_int(0, 9);
        }

        $firstDigit = $this->cpfDigit($base, 10);
        $secondDigit = $this->cpfDigit($base.$firstDigit, 11);

        return $base.$firstDigit.$secondDigit;
    }

    private function cpfDigit(string $base, int $weight): int
    {
        $sum = 0;

        foreach (str_split($base) as $digit) {
            $sum += ((int) $digit) * $weight;
            $weight--;
        }

        $remainder = $sum % 11;

        return $remainder < 2 ? 0 : 11 - $remainder;
    }

    private function mobilePhone(): string
    {
        return '38'.'9'.str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
    }

    private function landlinePhone(): string
    {
        return '38'.str_pad((string) random_int(30000000, 39999999), 8, '0', STR_PAD_LEFT);
    }

    private function postalCode(): string
    {
        return str_pad((string) random_int(1000000, 99999999), 8, '0', STR_PAD_LEFT);
    }
}
