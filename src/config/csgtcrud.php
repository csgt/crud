<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Duracion del estado guardado de las tablas (stateDuration)
    |--------------------------------------------------------------------------
    |
    | Cantidad de segundos que DataTables debe recordar el estado de la tabla
    | (busqueda, filtros, orden, pagina) en el localStorage del navegador.
    | Un valor de 0 significa que el estado se recuerda por siempre, mientras
    | la sesion del navegador este activa (equivalente al comportamiento
    | previo, donde este valor estaba fijo en el codigo).
    |
    */
    'stateDuration' => 0,

    /*
    |--------------------------------------------------------------------------
    | Extensiones de archivo permitidas (extensiones_permitidas)
    |--------------------------------------------------------------------------
    |
    | Lista blanca opcional de extensiones que se aceptan al subir archivos a
    | traves de los campos de tipo "file" o "image". El valor por defecto es
    | `null`, que preserva el comportamiento historico de este paquete: no se
    | rechaza ninguna extension. Una aplicacion que quiera restringir las
    | subidas (por ejemplo para evitar archivos ejecutables como .php en un
    | directorio servido publicamente) puede publicar este archivo y asignarle
    | un arreglo de extensiones. La comparacion se hace sin distinguir
    | mayusculas de minusculas.
    |
    */
    'extensiones_permitidas' => null,

    /*
    |--------------------------------------------------------------------------
    | Largo maximo de pagina (max_page_length)
    |--------------------------------------------------------------------------
    |
    | Limite opcional para el parametro "length" que DataTables envia en cada
    | consulta de data(). El valor por defecto es `null`, que preserva el
    | comportamiento historico: se usa el valor recibido del cliente tal cual.
    | Una aplicacion que quiera evitar que un cliente pida la tabla completa de
    | una sola vez (por ejemplo con length=9999999) puede asignarle un entero;
    | en ese caso, un length recibido menor o igual a 0 se trata como
    | perPage(), y cualquier valor mayor se recorta a este maximo.
    |
    */
    'max_page_length' => null,

];
