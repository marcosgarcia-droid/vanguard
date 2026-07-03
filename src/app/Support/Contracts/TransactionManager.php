<?php

namespace App\Support\Contracts;

interface TransactionManager
{
    public function run(callable $callback): mixed;
}
