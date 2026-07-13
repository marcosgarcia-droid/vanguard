<?php

namespace App\Support\ActivityLog;

use Illuminate\Contracts\Auth\Authenticatable;

class ActivityLogAuthorization
{
    public function __invoke(?Authenticatable $user): bool
    {
        return $user?->hasRole(config('filament-shield.super_admin.name', 'super_admin')) ?? false;
    }
}
