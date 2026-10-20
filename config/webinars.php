<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Dominios internos propios
    |--------------------------------------------------------------------------
    |
    | Correos de nuestra gente. Nunca son leads: se excluyen de los conteos
    | "externos" de registros y asistencia. Cada cliente declara además sus
    | propios dominios en `clients.internal_email_domains`.
    |
    | El match es por subcadena después de la arroba, igual que el filtro que
    | tenía el dashboard: 'templet' cubre templet.io y templet.com.
    |
    */

    'internal_domains' => [
        'templet',
        'cwc',
    ],

    /*
    |--------------------------------------------------------------------------
    | Dominio público de los clientes
    |--------------------------------------------------------------------------
    |
    | Cada cliente publica sus páginas en {slug}.{client_domain}. Se usa para
    | armar el enlace "Open live" del panel; el preview siempre apunta a esta
    | misma aplicación.
    |
    */

    'client_domain' => env('WEBINARS_CLIENT_DOMAIN', 'templet.io'),

    'client_scheme' => env('WEBINARS_CLIENT_SCHEME', 'https'),

];
