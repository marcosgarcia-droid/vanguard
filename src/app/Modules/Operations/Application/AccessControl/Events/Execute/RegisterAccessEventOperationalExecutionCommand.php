<?php

namespace App\Modules\Operations\Application\AccessControl\Events\Execute;

use App\Support\Contracts\Command;

final readonly class RegisterAccessEventOperationalExecutionCommand implements Command
{
    public function __construct(
        public string $decisionId,
    ) {}
}
