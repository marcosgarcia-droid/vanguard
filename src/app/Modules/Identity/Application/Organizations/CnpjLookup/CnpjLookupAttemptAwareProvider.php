<?php

namespace App\Modules\Identity\Application\Organizations\CnpjLookup;

interface CnpjLookupAttemptAwareProvider extends CnpjLookupProvider
{
    /**
     * @return list<CnpjLookupAttempt>
     */
    public function attempts(): array;
}
