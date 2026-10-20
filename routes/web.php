<?php

use App\Http\Controllers\PagePreviewController;
use App\Http\Controllers\SurveyController;
use App\Http\Controllers\WebinarController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

/*
|--------------------------------------------------------------------------
| PREVIEW DEL PANEL
|--------------------------------------------------------------------------
|
| Renderizan la página pública dentro del iframe del formulario. Van antes
| del grupo con el catch-all /{client}/{slug} para que no se las trague, y
| exigen sesión porque muestran contenido todavía sin publicar.
|
*/

Route::middleware(['web', 'auth'])
    ->prefix('previews')
    ->name('preview.')
    ->group(function () {
        Route::get('/webinars/{webinar?}', [PagePreviewController::class, 'webinar'])->name('webinar');
        Route::get('/surveys/{survey?}', [PagePreviewController::class, 'survey'])->name('survey');
        Route::get('/clients/{client}', [PagePreviewController::class, 'client'])->name('client');
    });

/*
|--------------------------------------------------------------------------
| Clientes que sirven las páginas desde su propio dominio
|--------------------------------------------------------------------------
|
| Solo para clientes que proxean /webinars/* desde su sitio hacia esta
| aplicación con un .htaccess:
|   - escala.templet.io/webinars/mi-webinar
|
| El middleware DetectClientFromDomain saca el cliente del subdominio. NO es
| la dirección con la que se publican las páginas: esa es la de abajo.
|
*/

Route::middleware(['web', \App\Http\Middleware\DetectClientFromDomain::class])
    ->group(function () {
        Route::get('/webinars/{slug}', [WebinarController::class, 'show'])
            ->name('webinar.show');

        Route::post('/webinars/{slug}', [WebinarController::class, 'store'])
            ->name('webinar.store');

        Route::get('/webinars/{slug}/thank-you', [WebinarController::class, 'thankYou'])
            ->name('webinar.thankyou');

        Route::get('/surveys/{slug}', [SurveyController::class, 'show'])
            ->name('survey.show');

        Route::post('/surveys/{slug}', [SurveyController::class, 'store'])
            ->name('survey.store');
    });

/*
|--------------------------------------------------------------------------
| Direcciones publicadas
|--------------------------------------------------------------------------
|
| Es la forma con la que se comparten las páginas y la que arma el panel:
|   https://webinars.templet.io/libertynet/identidad-como-perimetro-ia
|   https://webinars.templet.io/libertynet/surveys/satisfaccion
|
| El sufijo `.local` de los nombres es herencia de cuando se creyó que esto
| era solo un atajo de desarrollo; son las rutas de verdad.
|
| Van al final porque /{client}/{slug} se traga cualquier par de segmentos.
|
*/

Route::prefix('{client}')->group(function () {
    Route::get('/surveys/{slug}', [SurveyController::class, 'show'])
        ->name('survey.show.local');

    Route::post('/surveys/{slug}', [SurveyController::class, 'store'])
        ->name('survey.store.local');

    Route::get('/{slug}', [WebinarController::class, 'show'])
        ->name('webinar.show.local');

    Route::post('/{slug}', [WebinarController::class, 'store'])
        ->name('webinar.store.local');

    Route::get('/{slug}/thank-you', [WebinarController::class, 'thankYou'])
        ->name('webinar.thankyou.local');
});
