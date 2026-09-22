<?php

return [
    'datatables'     => [
        'js'  => '/js/datatables.min.js',
        'css' => '/css/datatables.min.css',
    ],
    'font-awesome'   => '/css/font-awesome.min.css',
    'use_encryption' => true,

    /*
     * Extensiones de archivo permitidas al subir campos tipo "file" o "image".
     * Por defecto es null: no se aplica ninguna restriccion, igual que el
     * comportamiento actual. Para activar la whitelist, la aplicacion debe
     * asignarle un arreglo, p.ej.:
     * ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'zip'].
     */
    'extensiones_permitidas' => null,
    /*
     * Cantidad de segundos que DataTables guarda el estado de la tabla (busqueda,
     * orden, pagina, etc) en el localStorage del navegador. 0 significa que el
     * estado nunca expira. Se puede sobreescribir por controlador con
     * setStateDuration().
     */
    'stateDuration' => 0,
];
