<?php

use App\Modules\Identity\UI\Http\Controllers\ChangeCurrentTenantController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'portal.index')->name('portal');
Route::post('/admin/current-group', ChangeCurrentTenantController::class)
    ->middleware(['auth'])
    ->name('vanguard.current-tenant.change');
