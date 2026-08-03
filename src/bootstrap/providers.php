<?php

use App\Providers\AppServiceProvider;
use App\Providers\ArchitectureServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\IntelbrasFacialSynchronizationServiceProvider;

return [
    AppServiceProvider::class,
    ArchitectureServiceProvider::class,
    AdminPanelProvider::class,
    IntelbrasFacialSynchronizationServiceProvider::class,
];
