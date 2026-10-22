@extends($template)
@section('breadcrumb')
    {!! $breadcrumb !!}
@stop
@section('content')
@php
    if (!function_exists('arrayToFields')) {
        function arrayToFields($arr) {
            $callback = function ($key, $value) {
                return $key . "=\"" . $value . "\"";
            };
            $fields = implode(" ", array_map($callback, array_keys($arr), $arr));

            return $fields;
        }
    }
@endphp
    <form method="POST" action="/{{$pathstore . $queryParameters}}" class="form-horizontal" id="frmCrud" enctype="multipart/form-data">
        <div class="card">
            <div class="card-body">
                <div class="row">
                    @if($data)
                        <input type="hidden" name="_method" value="PUT">
                    @endif
                    {{ csrf_field() }}
                    @foreach($columns as $column)
                        @php
                            $real = $column['campoReal'];
                            $valor = old($real, $data ? $data->{$real} : $column['default']);
                            $label = '<label for="' . $column['campoReal'] . '" class="control-label">' . $column['name'] . '</label>';
                            $arr = ['class' => 'form-control' . ($errors->has($real) ? ' is-invalid' : '')];
                        @endphp
                        <div class="{{ $column['editClass'] }}">
                            <div class="form-group">
                                @if($column['type'] == 'password')
                                    <!---------------------------- PASSWORD ---------------------------------->
                                    <div class="row">
                                        {!!$label!!}
                                        <div class="col-sm-6">
                                            @php
                                                $arr['placeholder'] = 'Password';
                                            @endphp
                                            <input type="password" name="{{ $column['campoReal'] }}" {!! arrayToFields($arr) !!}>
                                        </div>
                                        <div class="col-sm-6">
                                            <input type="password" name="{{ $column['campoReal'] . 'confirm' }}" {!! arrayToFields($arr) !!}>
                                            @if($data)
                                                <p class="help-block">* Dejar en blanco para no cambiar {!! $column['name'] !!}</p>
                                            @endif
                                        </div>
                                    </div>
                                @elseif($column['type'] == 'textarea')
                                    <!---------------------------- TEXTAREA ---------------------------------->
                                    {!!$label!!}
                                    <div>
                                        <textarea name="{{$column['campoReal']}}" {!! arrayToFields($arr) !!}>{{ $valor }}</textarea>
                                    </div>
                                @elseif($column['type'] == 'summernote')
                                    <!---------------------------- SUMMERNOTE ---------------------------------->
                                    {!!$label!!}
                                    <div>
                                        <?php $arr['class'] .= ' summernote';?>
                                        <textarea name="{{$column['campoReal']}}" {!! arrayToFields($arr) !!}>{{ $valor }}</textarea>
                                    </div>
                                @elseif($column['type'] == 'bool')
                                    <!---------------------------- BOOLEAN ---------------------------------->
                                    <div>&nbsp;</div>
                                    <div>
                                        <div class="checkbox">
                                        <label>
                                            <input type="checkbox" name="{{$column['campoReal']}}" value="1" {{$valor == 1? "checked":""}}>
                                            {!! $column['name'] !!}
                                        </label>
                                        <input class="hiddencheckbox" type='hidden' value='0' name='{{$column['campoReal']}}'>
                                    </div>
                                  </div>
                                @elseif($column['type'] == 'date')
                                    <!---------------------------- DATE ---------------------------------->
                                    @php
                                        $datearray = explode('-', $valor);
                                        if (count($datearray) == 3) {
                                            $laFecha = $datearray[2] . '/' . $datearray[1] . '/' . $datearray[0];
                                        } else {
                                            $laFecha = null;
                                        }
                                        $arr['class'] .= ' catalogoFecha';
                                        $arr['data-date-locale'] = 'es';
                                        $arr['data-date-format'] = 'DD/MM/YYYY';
                                    @endphp
                                    {!!$label!!}
                                    <div>
                                        <input id="div{!!$column['campoReal']!!}"
                                            type="text"

                                            name="{{ $column['campoReal'] }}"
                                            value="{{ old($real, $laFecha) }}"
                                            {!! arrayToFields($arr) !!}>
                                    </div>
                                @elseif($column['type'] == 'datetime')
                                    <!---------------------------- DATETIME ---------------------------------->
                                    @php
                                        $datearray2 = explode(' ', $valor);
                                        if (count($datearray2) == 2) {
                                            $hora = explode(':', $datearray2[1]);
                                            $datearray = explode('-', $datearray2[0]);
                                            $laFecha = $datearray[2] . '/' . $datearray[1] . '/' . $datearray[0] . ' ' . $hora[0] . ':' . $hora[1];
                                        } else {
                                            $laFecha = null;
                                        }
                                        $arr['class'] .= ' catalogoFecha';
                                        $arr['data-date-locale'] = 'es';
                                        $arr['data-date-language'] = 'es'; //Backwards compatible con datepicker 2
                                        $arr['data-date-format'] = 'DD/MM/YYYY HH:mm';
                                    @endphp
                                    {!!$label!!}

                                    <div>
                                        <input id="div{!!$column['campoReal']!!}"
                                            type="text"

                                            name="{{ $column['campoReal'] }}"
                                            value="{{ old($real, $laFecha) }}"
                                            {!! arrayToFields($arr) !!}>
                                    </div>
                                @elseif($column['type'] == 'time')
                                    <!---------------------------- TIME ---------------------------------->
                                    @php
                                        $arr['class'] .= ' catalogoFecha';
                                        $arr['data-date-locale'] = 'es';
                                        $arr['data-date-language'] = 'es'; //Backwards compatible con datepicker 2
                                        $arr['data-date-format'] = 'HH:mm';
                                    @endphp
                                    {!!$label!!}
                                    <div>
                                        <input id="div{!!$column['campoReal']!!}"
                                            type="text"

                                            name="{{ $column['campoReal'] }}"
                                            value="{{ $valor }}"
                                            {!! arrayToFields($arr) !!}>
                                    </div>
                                @elseif($column['type'] == 'combobox')
                                    <!---------------------------- COMBOBOX ---------------------------------->
                                    @php
                                        $arr['class'] .= ' selectpicker';
                                        $arr['data-width'] = 'auto';
                                    @endphp
                                    {!!$label!!}
                                    <div>
                                        <?php $campo = old($column['field'], $data ? $data->{$column['field']} : $column['default']); ?>
                                        <select name="{{ $column['field'] }}" {!! arrayToFields($arr) !!}>
                                            {{-- Sin esta opcion el navegador selecciona la primera de la lista
                                                 cuando el registro no tiene valor, y al guardar la escribe como
                                                 si el usuario la hubiera elegido. --}}
                                            <option value="" {{ ($campo === null || $campo === '' ? "selected='selected'" : "") }}></option>
                                            @foreach($combos[$column['alias']] as $id => $opcion)
                                            <option value="{{ $id }}" {{ ($campo == $id ? "selected='selected'" : "") }}>{!! $opcion !!}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @elseif($column['type'] == 'multi')
                                    <!---------------------------- MULTI ---------------------------------->
                                    @php
                                        $arr['class'] .= ' selectpicker';
                                        $arr['data-width'] = 'auto';
                                    @endphp
                                    {!!$label!!}
                                    <div>
                                        @php
                                            $campo = session()->hasOldInput()
                                                ? old($column['field'], [])
                                                : ($data ? $data->{$column['field']}->modelKeys() : (array) $column['default']);
                                        @endphp
                                        <select multiple="multiple" name="{{ $column['field'] }}[]" {!! arrayToFields($arr) !!}>

                                            @foreach($combos[$column['alias']] as $id => $opcion)
                                            <option
                                                value="{{ $id }}"
                                                {{ in_array($id, (array) $campo) ? "selected='selected'" : "" }}
                                                >
                                            {!! $opcion !!}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @elseif($column['type'] == 'enum')
                                    <!---------------------------- ENUM ---------------------------------->
                                    @php
                                        $arr['class'] .= ' selectpicker';
                                        $arr['data-width'] = 'auto';
                                    @endphp
                                    {!!$label!!}
                                    <div>
                                        <select name="{{ $column['campoReal'] }}" {!! arrayToFields($arr) !!}>
                                            {{-- Ver el comentario del tipo combobox: sin opcion vacia se guarda
                                                 la primera opcion sin que nadie la haya elegido. --}}
                                            <option value="" {{ ($valor === null || $valor === '' ? "selected='selected'" : "") }}></option>
                                            @foreach($column['enumarray'] as $id => $opcion)
                                            <option value="{{ $id }}" {{ ($valor == $id ? "selected='selected'" : "") }}>{!! $opcion !!}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @elseif(($column['type'] == 'file')||($column['type'] == 'image')||($column['type'] == 'securefile'))
                                    <!---------------------------- FILE/IMAGE/SECUREFILE ---------------------------------->
                                    {!!$label!!}
                                    <div>
                                        <input type="file" name="{{ $column['campoReal'] }}" {!! arrayToFields($arr) !!}>
                                        @if($data)
                                            <p class="help-block">{!! $valor !!}</p>
                                        @endif
                                    </div>
                                @elseif($column['type'] == 'numeric')
                                    <!---------------------------- NUMERIC ---------------------------------->
                                    {!!$label!!}
                                    <input type="number" step="any" name="{{ $column['campoReal'] }}" value="{{ $valor }}" {!! arrayToFields    ($arr) !!}>
                                @else
                                    <!---------------------------- DEFAULT ---------------------------------->
                                    {!!$label!!}
                                    <div>
                                        <input type="text" name="{{ $column['campoReal'] }}" value="{{ $valor }}" {!! arrayToFields($arr) !!}>
                                    </div>
                                @endif
                                @if ($errors->has($real))
                                    <div class="invalid-feedback d-block">{{ $errors->first($real) }}</div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="card-footer">
                <input type="submit" value="{{trans('csgtcrud::crud.guardar')}}" class="btn btn-primary">&nbsp;
                <a href="javascript:window.history.back();" class="btn btn-default btn-light">{{trans('csgtcrud::crud.cancelar')}}</a>
            </div>
        </div>
    </form>
@endsection

@section ('javascript')
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
            @if($uses['dates'])
                $('.catalogoFecha').datetimepicker();
            @endif
            @if($uses['selectize'])
                csgtCrudRequire('selectize', !!(window.jQuery && jQuery.fn && jQuery.fn.selectize), null, '', '');
                $('.selectpicker').selectize();
            @endif
            @if($uses['summernote'])
                csgtCrudRequire('summernote', !!(window.jQuery && jQuery.summernote),
                  window.jQuery && jQuery.summernote && jQuery.summernote.version, '0.8', '');
                $('.summernote').summernote({
                    'lang'   : 'es-ES',
                });
            @endif
            function makeCheckValidation(checkbox){
                if($(checkbox).is(":checked")){
                    $(checkbox).parent().next().attr('disabled', true);
                }else{
                    $(checkbox).parent().next().attr('disabled', false);
                }
            }
            $('input[type="checkbox"]').each(function(){
                makeCheckValidation(this);
                $(this).change(function(){
                    makeCheckValidation(this);
                })
            });
        });
    </script>
@endsection
