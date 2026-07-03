<?php

namespace App\Modules\Identity\Application\Organizations\CnpjLookup;

enum CnpjLookupSyncStatus: string
{
    case Success = 'success';
    case Failed = 'failed';
}
