@extends($template)
@section('breadcrumb')
    {!! $breadcrumb !!}
@stop
@section('content')
    @php
        function arrayToFields($arr)
        {
            $callback = function ($key, $value) {
                return $key . "=\"" . $value . "\"";
            };
            $fields = implode(' ', array_map($callback, array_keys($arr), $arr));

            return $fields;
        }
    @endphp
    <form method="POST" action="{{ URL::to($pathstore) . $nuevasVars }}" class="form-horizontal" id="frmCrud"
        enctype="multipart/form-data">
        <div class="card">
            <div class="card-body">
                @if ($data)
                    <input type="hidden" name="_method" value="PUT">
                @endif
                {{ csrf_field() }}
                @foreach ($columnas as $columna)
                    @php
                        $valor = $data ? $data->{$columna['campoReal']} : $columna['default'];
                        $label = '<label for="' . $columna['campoReal'] . '" class="control-label">' . $columna['nombre'] . '</label>';
                        $arr = ['class' => 'form-control'];
                        //dd($columnas);
                        foreach ($columna['reglas'] as $regla) {
                            $arr['data-fv-' . $regla] = 'true';
                            $arr['data-fv-' . $regla . '-message'] = $columna['reglasmensaje'];
                        }
                    @endphp
                    <div class="form-group">
                        <!---------------------------- PASSWORD ---------------------------------->
                        @if ($columna['tipo'] == 'password')
                            {!! $label !!}
                            @php
                                $arr['placeholder'] = 'Password';
                                $arr['data-fv-identical'] = 'true';
                                $arr['data-fv-identical-field'] = $columna['campoReal'] . 'confirm';
                                $arr['data-fv-identical-message'] = trans('csgtcrud::crud.passnocoinciden');

                                if (!$data) {
                                    $arr['data-fv-notempty'] = 'true';
                                    $arr['data-fv-notempty-message'] = trans('csgtcrud::crud.passrequerida');
                                }
                            @endphp
                            <input type="password" name="{{ $columna['campoReal'] }}" {!! arrayToFields($arr) !!}>
                            @php
                                $arr['data-fv-identical-field'] = $columna['campoReal'];
                            @endphp
                            <input type="password" name="{{ $columna['campoReal'] . 'confirm' }}" {!! arrayToFields($arr) !!}>
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
                                    <input type="checkbox" name="{{ $columna['campoReal'] }}" value="1"
                                        {{ $valor == 1 ? 'checked' : '' }}>
                                    {!! $columna['nombre'] !!}
                                </label>
                                <input class="hiddencheckbox" type='hidden' value='0'
                                    name='{{ $columna['campoReal'] }}'>
                            </div>
                            <!---------------------------- DATE ---------------------------------->
                        @elseif($columna['tipo'] == 'date')
                            @php
                                $datearray = explode('-', $valor);
                                if (count($datearray) == 3) {
                                    $laFecha = $datearray[2] . '/' . $datearray[1] . '/' . $datearray[0];
                                } else {
                                    $laFecha = null;
                                }
                                $arr['data-date-locale'] = 'es';
                                $arr['data-date-language'] = 'es'; //Backwards compatible con datepicker 2
                                $arr['data-date-pickTime'] = 'false'; //Backwards compatible con datepicker 2
                                $arr['data-date-format'] = 'DD/MM/YYYY';
                                $arr['data-fv-date-format'] = 'DD/MM/YYYY';
                                $arr['data-fv-date'] = 'true';
                            @endphp
                            {!! $label !!}
                            <div id="div{!! $columna['campoReal'] !!}" class="input-group date catalogoFecha">
                                <input type="text" name="{{ $columna['campoReal'] }}" value="{{ $laFecha }}"
                                    {!! arrayToFields($arr) !!}>
                                <span class="input-group-addon"><span class="glyphicon glyphicon-calendar"></span></span>
                            </div>
                            <!---------------------------- DATETIME ---------------------------------->
                        @elseif($columna['tipo'] == 'datetime')
                            @php
                                $datearray2 = explode(' ', $valor);
                                if (count($datearray2) == 2) {
                                    $hora = explode(':', $datearray2[1]);
                                    $datearray = explode('-', $datearray2[0]);
                                    $laFecha = $datearray[2] . '/' . $datearray[1] . '/' . $datearray[0] . ' ' . $hora[0] . ':' . $hora[1];
                                } else {
                                    $laFecha = null;
                                }
                                $arr['data-date-locale'] = 'es';
                                $arr['data-date-language'] = 'es'; //Backwards compatible con datepicker 2
                                $arr['data-date-format'] = 'DD/MM/YYYY HH:mm';
                                $arr['data-fv-date-format'] = 'DD/MM/YYYY HH:mm';
                                $arr['data-fv-date'] = 'true';
                            @endphp
                            {!! $label !!}
                            <div id="div{!! $columna['campoReal'] !!}" class="input-group date catalogoFecha">
                                <input type="text" name="{{ $columna['campoReal'] }}" value="{{ $laFecha }}"
                                    {!! arrayToFields($arr) !!}>
                                <span class="input-group-addon"><span class="glyphicon glyphicon-calendar"></span></span>
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
                                @foreach ($combos[$columna['alias']] as $id => $opcion)
                                    <option value="{{ $id }}" {{ $campo == $id ? "selected='selected'" : '' }}>
                                        {!! $opcion !!}
                                    </option>
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
                                @foreach ($columna['enumarray'] as $id => $opcion)
                                    <option value="{{ $id }}" {{ $valor == $id ? "selected='selected'" : '' }}>
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
                    </div>
                @endforeach
            </div>
            <div class="card-footer">
                <input type="submit" value="{{ trans('csgtcrud::crud.guardar') }}" class="btn btn-primary">&nbsp;
                <a href="javascript:window.history.back();"
                    class="btn btn-default btn-light">{{ trans('csgtcrud::crud.cancelar') }}</a>
            </div>
        </div>
    </form>
@endsection

@section('javascript')
    <script type="text/javascript">
        $(function() {
            @if ($uses['dates'])
                $('.catalogoFecha').datetimepicker();
            @endif
            @if ($uses['selectize'])
                $('.selectpicker').selectize();
            @endif
            @if ($uses['summernote'])
                $('.summernote').summernote({
                    'lang': 'es-ES',
                });
            @endif
            function makeCheckValidation(checkbox) {
                if ($(checkbox).is(":checked")) {
                    $(checkbox).parent().next().attr('disabled', true);
                } else {
                    $(checkbox).parent().next().attr('disabled', false);
                }
            }
            $('input[type="checkbox"]').each(function() {
                makeCheckValidation(this);
                $(this).change(function() {
                    makeCheckValidation(this);
                })
            });
        });
    </script>
@endsection
