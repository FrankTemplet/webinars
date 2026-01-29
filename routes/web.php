<?php

use App\Http\Controllers\WebinarController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

/*
|--------------------------------------------------------------------------
| PRODUCCIÓN - Subdominios de Clientes
|--------------------------------------------------------------------------
|
| Rutas para webinars accesibles desde subdominios de clientes:
|   - escala.templet.io/webinars/mi-webinar
|   - libertynet.templet.io/webinars/lanzamiento
|
| El middleware DetectClientFromDomain detecta automáticamente el cliente
| basándose en el subdominio.
|
| IMPORTANTE: Cada cliente debe tener configurado en su sitio web un
| .htaccess que redirija /webinars/* a esta aplicación Laravel.
|
*/

Route::middleware(['web', \App\Http\Middleware\DetectClientFromDomain::class])
    ->group(function () {
        Route::get('/webinars/{slug}', [WebinarController::class, 'show'])
            ->name('webinar.show');

        Route::post('/webinars/{slug}', [WebinarController::class, 'store'])
            ->name('webinar.store');
    });

/*
|--------------------------------------------------------------------------
| DESARROLLO LOCAL (fallback)
|--------------------------------------------------------------------------
|
| Permite usar rutas sin configurar subdominios:
|   http://localhost:8000/client/escala/webinars/mi-webinar
|
*/

Route::prefix('{client}')->group(function () {
    Route::get('/{slug}', [WebinarController::class, 'show'])
        ->name('webinar.show.local');

    Route::post('/{slug}', [WebinarController::class, 'store'])
        ->name('webinar.store.local');
});
