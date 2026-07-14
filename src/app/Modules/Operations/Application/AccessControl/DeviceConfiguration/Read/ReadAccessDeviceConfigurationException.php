<?php

namespace App\Modules\Operations\Application\AccessControl\DeviceConfiguration\Read;

use RuntimeException;
use Throwable;

final class ReadAccessDeviceConfigurationException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?string $snapshotId = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            $message,
            previous: $previous
        );
    }
}
