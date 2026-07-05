<?php

namespace App\Modules\Identity\UI\Filament\Resources\OrganizationRecords\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

final readonly class UniqueOrganizationCnpj implements ValidationRule
{
    public function __construct(
        private ?string $ignoreOrganizationId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $cnpj = preg_replace('/\D+/', '', (string) $value);

        if ($cnpj === '') {
            return;
        }

        $exists = DB::table('organizations')
            ->where('cnpj', $cnpj)
            ->when(
                $this->ignoreOrganizationId !== null,
                fn ($query) => $query->where('id', '!=', $this->ignoreOrganizationId),
            )
            ->exists();

        if (! $exists) {
            return;
        }

        $fail('Este CNPJ já está cadastrado.');
    }
}
