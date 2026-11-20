<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Usar Encripción
    |--------------------------------------------------------------------------
    |
    | Esta opción cuando en true, envía los IDs de las funciones encriptados
    | y es necesario utilizar decrypt() para obtener el id desencriptado
    | de igual forma, si se pone en false, se mandan los ids sin encriptar
    |
    |
     */

    'usar_encripcion' => true,

    /*
    |--------------------------------------------------------------------------
    | Duración del estado guardado (stateDuration)
    |--------------------------------------------------------------------------
    |
    | Cantidad de segundos que DataTables conserva el estado guardado
    | (ordenamiento, filtros, paginación) en localStorage. Por defecto
    | DataTables lo expira a las 2 horas (7200 segundos). Poniendo este
    | valor en 0 el estado nunca expira.
    |
    |
     */

    'stateDuration' => 0,

    /*
    |--------------------------------------------------------------------------
    | Extensiones permitidas
    |--------------------------------------------------------------------------
    |
    | Lista blanca de extensiones (en minúscula, sin punto) aceptadas al
    | subir archivos con los campos tipo file e image. Por defecto es null,
    | lo que significa que no se aplica ninguna restricción y se preserva
    | el comportamiento actual. Para activar la validación, definir un
    | arreglo, por ejemplo:
    | ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'pdf', 'doc', 'docx',
    |  'xls', 'xlsx', 'csv', 'txt', 'zip']
    |
    |
     */

    'extensiones_permitidas' => null,

    /*
    |--------------------------------------------------------------------------
    | Largo máximo de página (max_page_length)
    |--------------------------------------------------------------------------
    |
    | Cantidad máxima de registros que se pueden solicitar por página en
    | data(). Por defecto es null, lo que significa que no se aplica
    | ningún límite y se preserva el comportamiento actual (el "length"
    | que envía el cliente se usa tal cual). Para activar el límite,
    | definir un número entero, por ejemplo: 500
    |
    |
     */

    'max_page_length' => null,
];
