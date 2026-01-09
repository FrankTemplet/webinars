<?php

use App\Http\Controllers\WebinarController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});


/*
|--------------------------------------------------------------------------
| PRODUCCIÓN
| Subdominio dinámico: {client}.templet.io
|--------------------------------------------------------------------------
|
| Ejemplo válido:
|   - empresa1.templet.io/webinars/mi-webinar
|   - empresa2.templet.io/webinars/lanzamiento
|
| Nota:
| Laravel SOLO soporta un subdominio dinámico con dominio base fijo.
| No soporta {client}.{domain} o wildcard de dominios.
|
*/

Route::domain('{client}.templet.io')->group(function () {

    Route::get('/webinars/{slug}', [WebinarController::class, 'show'])
        ->name('webinar.show');

    Route::post('/webinars/{slug}', [WebinarController::class, 'store'])
        ->name('webinar.store');
});

/*
|--------------------------------------------------------------------------
| DESARROLLO LOCAL (fallback sin subdominios)
|--------------------------------------------------------------------------
|
| Permite usar rutas sin configurar subdominios:
|
|   http://localhost:8000/client/empresa1/webinars/mi-webinar
|
*/

Route::prefix('client/{client}')->group(function () {

    Route::get('/webinars/{slug}', [WebinarController::class, 'show'])
        ->name('webinar.show.local');

    Route::post('/webinars/{slug}', [WebinarController::class, 'store'])
        ->name('webinar.store.local');
});
