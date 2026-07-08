<?php

namespace App\Support;

use Illuminate\Support\Str;

class VanguardText
{
    public static function upper(mixed $value): string
    {
        if (blank($value)) {
            return '-';
        }

        return Str::of((string) $value)
            ->squish()
            ->upper()
            ->toString();
    }
}
