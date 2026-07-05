<?php

namespace App\Modules\Identity\UI\Filament\Resources\OrganizationRecords\Rules;

use App\Modules\Identity\Domain\Organizations\ValueObjects\Cnpj;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;
use Throwable;

final readonly class ValidOrganizationCnpjCorrection implements ValidationRule
{
    public function __construct(
        private ?string $ignoreOrganizationId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        try {
            $cnpj = new Cnpj($digits);
        } catch (Throwable) {
            $fail('Informe um CNPJ válido.');

            return;
        }

        $exists = DB::table('organizations')
            ->where('cnpj', $cnpj->value())
            ->when(
                $this->ignoreOrganizationId !== null,
                fn ($query) => $query->where('id', '!=', $this->ignoreOrganizationId),
            )
            ->exists();

        if ($exists) {
            $fail('Este CNPJ já está cadastrado em outra organização.');
        }
    }
}
