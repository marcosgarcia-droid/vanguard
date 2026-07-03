<?php

namespace App\Modules\Identity\Application\Organizations\CnpjLookup;

use App\Modules\Identity\Domain\Organizations\ValueObjects\Cnpj;

interface CnpjLookupProvider
{
    public function lookup(Cnpj $cnpj): CnpjLookupResult;
}
