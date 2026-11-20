@extends($template)
@section('breadcrumb')
    {!! $breadcrumb !!}
@stop
@section('content')
    @php
        if (!function_exists('arrayToFields')) {
            function arrayToFields($arr)
            {
                $callback = function ($key, $value) {
                    return $key . "=\"" . $value . "\"";
                };
                $fields = implode(' ', array_map($callback, array_keys($arr), $arr));

                return $fields;
            }
    }
    @endphp
    <form method="POST" action="{{ URL::to($pathstore) . $nuevasVars }}" class="form-horizontal" id="frmCrud"
        enctype="multipart/form-data">
        <div class="card">
            <div class="card-body">
                <div class="row">
                    @if ($data)
                        <input type="hidden" name="_method" value="PUT">
                    @endif
                    {{ csrf_field() }}
                    @foreach ($columnas as $columna)
                        @php
                            $real = $columna['campoReal'];
                            $valor = $data
                                ? $data->{$columna['campoReal']}
                                : (old($columna['campoReal']) ?:
                                $columna['default']);
                            $label =
                                '<label for="' .
                                $columna['campoReal'] .
                                '" class="control-label">' .
                                $columna['nombre'] .
                                '</label>';
                            $arr = [
                                'class' => 'form-control' . ($errors->has($real) ? ' is-invalid' : ''),
                            ];
                        @endphp
                        <div class="{{ $columna['editClass'] }}">
                            <div class="form-group">
                                <!---------------------------- PASSWORD ---------------------------------->
                                @if ($columna['tipo'] == 'password')
                                    {!! $label !!}
                                    @php
                                        $arr['placeholder'] = 'Password';
                                    @endphp
                                    <input type="password" name="{{ $columna['campoReal'] }}" {!! arrayToFields($arr) !!}>
                                    <input type="password" name="{{ $columna['campoReal'] . 'confirm' }}"
                                        {!! arrayToFields($arr) !!}>
                                    @if ($data)
                                        <p class="help-block">* Dejar en blanco para no cambiar {!! $columna['nombre'] !!}</p>
                                    @endif
                                    <!---------------------------- TEXTAREA ---------------------------------->
                                @elseif($columna['tipo'] == 'textarea')
                                    {!! $label !!}
                                    <textarea name="{{ $columna['campoReal'] }}" {!! arrayToFields($arr) !!}>{!! $valor !!}</textarea>
                                    <!---------------------------- SUMMERNOTE ---------------------------------->
                                @elseif($columna['tipo'] == 'summernote')
                                    {!! $label !!}
                                    @php $arr = ['class' => 'summernote']; @endphp
                                    <textarea name="{{ $columna['campoReal'] }}" {!! arrayToFields($arr) !!}>{!! $valor !!}</textarea>
                                    <!---------------------------- BOOLEAN ---------------------------------->
                                @elseif($columna['tipo'] == 'bool')
                                    <div class="checkbox">
                                        <label>
                                            <input type="checkbox" name="{{ $columna['campoReal'] }}"
                                                {{ $valor == 1 ? 'checked' : '' }}>
                                            {!! $columna['nombre'] !!}
                                        </label>
                                    </div>
                                    <!---------------------------- DATE ---------------------------------->
                                @elseif($columna['tipo'] == 'date')
                                    {!! $label !!}
                                    <div id="div{!! $columna['campoReal'] !!}" class="input-group">
                                        <input type="date" name="{{ $columna['campoReal'] }}"
                                            value="{{ $valor }}" {!! arrayToFields($arr) !!}>
                                    </div>
                                    <!---------------------------- DATETIME ---------------------------------->
                                @elseif($columna['tipo'] == 'datetime')
                                    {!! $label !!}
                                    <div id="div{!! $columna['campoReal'] !!}" class="input-group">
                                        <input type="datetime-local" name="{{ $columna['campoReal'] }}"
                                            value="{{ $valor }}" {!! arrayToFields($arr) !!}>
                                    </div>
                                    <!---------------------------- COMBOBOX ---------------------------------->
                                @elseif($columna['tipo'] == 'combobox')
                                    @php
                                        $arr['class'] = 'selectpicker form-control';
                                        $arr['data-width'] = 'auto';
                                    @endphp
                                    {!! $label !!}
                                    @php $campo = $data ? $data->{$columna['campo']} : ''; @endphp
                                    <select name="{{ $columna['campo'] }}" {!! arrayToFields($arr) !!}>
                                        {{-- Sin esta opcion el navegador selecciona la primera de la lista
                                             cuando el registro no tiene valor, y al guardar la escribe como
                                             si el usuario la hubiera elegido. --}}
                                        <option value=""
                                            {{ $campo === null || $campo === '' ? "selected='selected'" : '' }}>
                                        </option>
                                        @foreach ($combos[$columna['alias']] as $id => $opcion)
                                            <option value="{{ $id }}"
                                                {{ $campo == $id ? "selected='selected'" : '' }}>
                                                {!! $opcion !!}
                                            </option>
                                        @endforeach
                                    </select>
                                    <!---------------------------- MULTI ---------------------------------->
                                @elseif($columna['tipo'] == 'multi')
                                    @php
                                        $arr['class'] = 'selectpicker form-control';
                                        $arr['data-width'] = 'auto';
                                    @endphp
                                    {!! $label !!}
                                    <?php $campo = $data ? $data->{$columna['campo']} : ''; ?>
                                    <select multiple="multiple" name="{{ $columna['campo'] }}[]" {!! arrayToFields($arr) !!}>

                                        @foreach ($combos[$columna['alias']] as $id => $opcion)
                                            <option value="{{ $id }}"
                                                @if ($campo != '') {{ $campo->find($id) ? "selected='selected'" : '' }} @endif>
                                                {!! $opcion !!}</option>
                                        @endforeach
                                    </select>
                                    <!---------------------------- ENUM ---------------------------------->
                                @elseif($columna['tipo'] == 'enum')
                                    @php
                                        $arr['class'] = 'selectpicker form-control';
                                        $arr['data-width'] = 'auto';
                                    @endphp
                                    {!! $label !!}
                                    <select name="{{ $columna['campoReal'] }}" {!! arrayToFields($arr) !!}>
                                        {{-- Ver el comentario del tipo combobox: sin opcion vacia se guarda
                                             la primera opcion sin que nadie la haya elegido. --}}
                                        <option value=""
                                            {{ $valor === null || $valor === '' ? "selected='selected'" : '' }}>
                                        </option>
                                        @foreach ($columna['enumarray'] as $id => $opcion)
                                            <option value="{{ $id }}"
                                                {{ $valor == $id ? "selected='selected'" : '' }}>
                                                {!! $opcion !!}
                                            </option>
                                        @endforeach
                                    </select>
                                    <!---------------------------- FILE/IMAGE/SECUREFILE ---------------------------------->
                                @elseif($columna['tipo'] == 'file' || $columna['tipo'] == 'image' || $columna['tipo'] == 'securefile')
                                    {!! $label !!}
                                    <input type="file" name="{{ $columna['campoReal'] }}">
                                    @if ($data)
                                        <p class="help-block">{!! $valor !!}</p>
                                    @endif
                                    <!---------------------------- NUMERIC ---------------------------------->
                                @elseif($columna['tipo'] == 'numeric')
                                    {!! $label !!}
                                    <input type="text" name="{{ $columna['campoReal'] }}" value="{{ $valor }}"
                                        {!! arrayToFields($arr) !!}>
                                    <!---------------------------- DEFAULT ---------------------------------->
                                @else
                                    {!! $label !!}
                                    <input type="text" name="{{ $columna['campoReal'] }}" value="{{ $valor }}"
                                        {!! arrayToFields($arr) !!}>
                                @endif
                                @error($real)
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="card-footer">
                <input type="submit" value="{{ trans('csgtcrud::crud.guardar') }}" class="btn btn-primary">&nbsp;
                <a href="javascript:window.history.back();"
                    class="btn btn-default btn-light">{{ trans('csgtcrud::crud.cancelar') }}</a>
            </div>

        </div>
    </form>
    {{-- Este bloque vive en la seccion de contenido, que siempre se renderiza
         porque aqui esta la tabla. Si el layout de la aplicacion no rinde
         @@yield('javascript'), el bloque de scripts nunca corre y la vista queda
         muerta sin decir por que: el guard de dependencias esta alla adentro y
         tampoco llega a ejecutarse. --}}
    <script>
        window.addEventListener('load', function() {
            if (window.csgtCrudBooted) {
                return;
            }
            var message = typeof window.jQuery === 'undefined' ?
                'csgt/crud: falta la libreria jQuery, que esta vista necesita.' :
                'csgt/crud: el bloque de scripts de esta vista no llego a ejecutarse. Revise que el layout rinda @@yield(\'javascript\').';
            var banner = document.createElement('div');
            banner.setAttribute('style',
                'background:#b00020;color:#fff;padding:12px;font-family:sans-serif;font-size:14px');
            banner.textContent = message;
            document.body.insertBefore(banner, document.body.firstChild);
            console.error(message);
        });
    </script>
@endsection

@section('javascript')
    <script type="text/javascript">
        // Estas vistas no traen sus propias dependencias: las carga la aplicacion.
        // Cuando faltaba alguna, la pantalla quedaba en blanco sin decir por que.
        window.csgtCrudRequire = window.csgtCrudRequire || function(name, present, version, min, max) {
            if (!present) {
                var message = 'csgt/crud: falta la libreria ' + name + ', que esta vista necesita.';
                if (document.body) {
                    var banner = document.createElement('div');
                    banner.setAttribute('style',
                        'background:#b00020;color:#fff;padding:12px;font-family:sans-serif;font-size:14px');
                    banner.textContent = message;
                    document.body.insertBefore(banner, document.body.firstChild);
                }
                throw new Error(message);
            }
            if (!version) {
                return;
            }
            var cmp = function(a, b) {
                var x = String(a).split('.'),
                    y = String(b).split('.');
                for (var i = 0; i < 3; i++) {
                    var d = (parseInt(x[i], 10) || 0) - (parseInt(y[i], 10) || 0);
                    if (d) {
                        return d < 0 ? -1 : 1;
                    }
                }
                return 0;
            };
            // Fuera del rango probado avisa, pero no corta: ahi la libreria esta y
            // la vista probablemente funcione.
            if (min && cmp(version, min) < 0) {
                console.warn('csgt/crud: ' + name + ' ' + version + ' es anterior a la minima probada (' + min + ').');
            }
            if (max && cmp(version, max) >= 0) {
                console.warn('csgt/crud: ' + name + ' ' + version + ' es igual o posterior a ' + max + ', la primera no probada.');
            }
        };
        csgtCrudRequire('jQuery', typeof window.jQuery !== 'undefined',
            window.jQuery && window.jQuery.fn && window.jQuery.fn.jquery, '1.7', '');
        $(function() {
            @if ($uses['selectize'])
                csgtCrudRequire('selectize', !!(window.jQuery && jQuery.fn && jQuery.fn.selectize), null, '', '');
                $('.selectpicker').selectize();
            @endif
            @if ($uses['summernote'])
                csgtCrudRequire('summernote', !!(window.jQuery && jQuery.summernote),
                  window.jQuery && jQuery.summernote && jQuery.summernote.version, '0.8', '');
                $('.summernote').summernote({
                    'lang': 'es-ES',
                });
            @endif
        });
        // Lo ultimo del bloque: si algo de arriba corto, la bandera no se setea
        // y el backstop de la seccion de contenido avisa.
        window.csgtCrudBooted = true;
    </script>
@endsection
