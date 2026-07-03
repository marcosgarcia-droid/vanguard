<?php

namespace App\Support\Contracts;

interface UseCase
{
    public function execute(Command|Query $input): mixed;
}
