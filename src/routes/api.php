<?php

use App\Modules\Operations\UI\Http\Controllers\Integrations\Intelbras\ReceiveIntelbrasAccessEventController;
use Illuminate\Support\Facades\Route;

Route::post(
    '/integrations/intelbras/access-events/{device}/{token}',
    ReceiveIntelbrasAccessEventController::class
)
    ->whereUuid('device')
    ->where('token', '[A-Za-z0-9]{48,128}')
    ->middleware('throttle:120,1')
    ->name('integrations.intelbras.access-events.store');
