<?php

use App\Http\Controllers\Api\AttendeeController;
use App\Http\Controllers\Api\StatsController;
use App\Http\Controllers\Api\SubmissionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API pública (solo lectura) — consumida por templet_operator
|--------------------------------------------------------------------------
|
| Autenticación por API key en el header X-Api-Key. Documentación Swagger
| disponible en /api/documentation.
|
*/

Route::prefix('v1')
    ->middleware(['api.key', 'throttle:120,1'])
    ->group(function () {
        Route::get('submissions', [SubmissionController::class, 'index']);
        Route::get('submissions/{id}', [SubmissionController::class, 'show'])->whereNumber('id');

        Route::get('attendees', [AttendeeController::class, 'index']);

        Route::get('stats', [StatsController::class, 'index']);
        Route::get('stats/utm', [StatsController::class, 'utm']);
        Route::get('stats/timeseries', [StatsController::class, 'timeseries']);
    });
