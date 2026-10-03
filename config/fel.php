<?php

return [
    'tipos_identificacion_locales' => [
        '1' => 'NIT',
    ],
    'tipos_receptor_ainnova' => [
        'NIT' => '4',
        'CF' => '4',
        'CUI' => '2',
        'PASAPORTE' => '3',
        'EXTRANJERO' => '3',
    ],
    'ainnova' => [
        'endpoint' => env('FEL_AINNOVA_ENDPOINT'),
        'basic_usuario' => env('FEL_AINNOVA_BASIC_USUARIO'),
        'basic_password' => env('FEL_AINNOVA_BASIC_PASSWORD'),
        'ws_usuario' => env('FEL_AINNOVA_WS_USUARIO'),
        'ws_password' => env('FEL_AINNOVA_WS_PASSWORD'),
        'nit_emisor' => env('FEL_AINNOVA_NIT_EMISOR'),
        'establecimiento' => env('FEL_AINNOVA_ESTABLECIMIENTO'),
        'id_maquina' => env('FEL_AINNOVA_ID_MAQUINA'),
        'timeout' => env('FEL_AINNOVA_TIMEOUT', 60),
        'connect_timeout' => env('FEL_AINNOVA_CONNECT_TIMEOUT', 10),
        'verificar_ssl' => env('FEL_AINNOVA_VERIFICAR_SSL', true),
    ],
];
