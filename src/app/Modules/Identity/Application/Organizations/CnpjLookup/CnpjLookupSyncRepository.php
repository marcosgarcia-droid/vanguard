<?php

namespace App\Modules\Identity\Application\Organizations\CnpjLookup;

interface CnpjLookupSyncRepository
{
    public function save(CnpjLookupSync $sync): void;
}
