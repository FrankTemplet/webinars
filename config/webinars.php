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

];
