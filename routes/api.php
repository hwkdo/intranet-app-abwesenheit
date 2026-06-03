<?php

declare(strict_types=1);

use Hwkdo\IntranetAppAbwesenheit\Http\Controllers\Api\AbwesenheitController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')
    ->middleware(['auth:api', 'can:api-manage-out-of-office'])
    ->group(function (): void {
        Route::post('/abwesenheit/{username}', [AbwesenheitController::class, 'store']);
        Route::get('/abwesenheit/{username}', [AbwesenheitController::class, 'show']);
        Route::delete('/abwesenheit/{username}', [AbwesenheitController::class, 'destroy']);
    });
