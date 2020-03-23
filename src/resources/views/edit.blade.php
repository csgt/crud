@extends($template)
@section('breadcrumb')
    {!! $breadcrumb !!}
@stop
@section('content')
@php
    function arrayToFields($arr) {
        $callback = function ($key, $value) {
            return $key . "=\"" . $value . "\"";
        };
        $fields = implode(" ", array_map($callback, array_keys($arr), $arr));

        return $fields;
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
                            $valor = ($data ? $data->{$column['campoReal']} : $column['default']);
                            $label = '<label for="' . $column['campoReal'] . '" class="control-label">' . $column['name'] . '</label>';
                            $arr = ['class' => 'form-control'];
                            //dd($columns);
                            foreach ($column['validationRules'] as $regla) {
                                $arr['data-fv-' . $regla] = 'true';
                                $arr['data-fv-' . $regla . '-message'] = $column['validationRulesMessage'];
                            }
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
                                                $arr['data-fv-identical'] = 'true';
                                                $arr['data-fv-identical-field'] = $column['campoReal'] . 'confirm';
                                                $arr['data-fv-identical-message'] = trans('csgtcrud::crud.passnocoinciden');

                                                if (!$data) {
                                                    $arr['data-fv-notempty'] = 'true';
                                                    $arr['data-fv-notempty-message'] = trans('csgtcrud::crud.passrequerida');
                                                }
                                            @endphp
                                            <input type="password" name="{{ $column['campoReal'] }}" {!! arrayToFields($arr) !!}>
                                        </div>
                                        <div class="col-sm-6">
                                            @php
                                                $arr['data-fv-identical-field'] = $column['campoReal'];
                                            @endphp
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
                                        <textarea name="{{$column['campoReal']}}" {!! arrayToFields($arr) !!}>{!! $valor !!}</textarea>
                                    </div>
                                @elseif($column['type'] == 'summernote')
                                    <!---------------------------- SUMMERNOTE ---------------------------------->
                                    {!!$label!!}
                                    <div>
                                        <?php $arr = ['class' => 'summernote'];?>
                                        <textarea name="{{$column['campoReal']}}" {!! arrayToFields($arr) !!}>{!! $valor !!}</textarea>
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
                                        $arr['data-date-locale'] = 'es';
                                        $arr['data-date-format'] = 'DD/MM/YYYY';
                                        $arr['data-fv-date-format'] = 'DD/MM/YYYY';
                                        $arr['data-fv-date'] = 'true';
                                    @endphp
                                    {!!$label!!}
                                    <div>
                                        <input id="div{!!$column['campoReal']!!}"
                                            type="text"
                                            class="form-control catalogoFecha"
                                            name="{{ $column['campoReal'] }}"
                                            value="{{ $laFecha }}"
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
                                        $arr['data-date-locale'] = 'es';
                                        $arr['data-date-language'] = 'es'; //Backwards compatible con datepicker 2
                                        $arr['data-date-format'] = 'DD/MM/YYYY HH:mm';
                                        $arr['data-fv-date-format'] = 'DD/MM/YYYY HH:mm';
                                        $arr['data-fv-date'] = 'true';
                                    @endphp
                                    {!!$label!!}

                                    <div>
                                        <input id="div{!!$column['campoReal']!!}"
                                            type="text"
                                            class="form-control catalogoFecha"
                                            name="{{ $column['campoReal'] }}"
                                            value="{{ $laFecha }}"
                                            {!! arrayToFields($arr) !!}>
                                    </div>
                                @elseif($column['type'] == 'time')
                                    <!---------------------------- TIME ---------------------------------->
                                    @php
                                        $arr['data-date-locale'] = 'es';
                                        $arr['data-date-language'] = 'es'; //Backwards compatible con datepicker 2
                                        $arr['data-date-format'] = 'HH:mm';
                                        $arr['data-fv-date-format'] = 'HH:mm';
                                    @endphp
                                    {!!$label!!}
                                    <div>
                                        <input id="div{!!$column['campoReal']!!}"
                                            type="text"
                                            class="form-control catalogoFecha"
                                            name="{{ $column['campoReal'] }}"
                                            value="{{ $laFecha }}"
                                            {!! arrayToFields($arr) !!}>
                                    </div>
                                @elseif($column['type'] == 'combobox')
                                    <!---------------------------- COMBOBOX ---------------------------------->
                                    @php
                                        $arr['class'] = 'selectpicker form-control';
                                        $arr['data-width'] = 'auto';
                                    @endphp
                                    {!!$label!!}
                                    <div>
                                        <?php $campo = ($data ? $data->{$column['field']} : '')?>
                                        <select name="{{ $column['field'] }}" {!! arrayToFields($arr) !!}>
                                            @foreach($combos[$column['alias']] as $id => $opcion)
                                            <option value="{{ $id }}" {{ ($campo == $id ? "selected='selected'" : "") }}>{!! $opcion !!}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @elseif($column['type'] == 'multi')
                                    <!---------------------------- MULTI ---------------------------------->
                                    @php
                                        $arr['class'] = 'selectpicker form-control';
                                        $arr['data-width'] = 'auto';
                                    @endphp
                                    {!!$label!!}
                                    <div>
                                        <?php $campo = ($data ? $data->{$column['field']} : '')?>
                                        <select multiple="multiple" name="{{ $column['field'] }}[]" {!! arrayToFields($arr) !!}>

                                            @foreach($combos[$column['alias']] as $id => $opcion)
                                            <option
                                                value="{{ $id }}"
                                                @if($campo != "")
                                                    {{ ($campo->find($id) ? "selected='selected'" : "") }}
                                                @endif
                                                >
                                            {!! $opcion !!}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @elseif($column['type'] == 'enum')
                                    <!---------------------------- ENUM ---------------------------------->
                                    @php
                                        $arr['class'] = 'selectpicker form-control';
                                        $arr['data-width'] = 'auto';
                                    @endphp
                                    {!!$label!!}
                                    <div>
                                        <select name="{{ $column['campoReal'] }}" {!! arrayToFields($arr) !!}>
                                            @foreach($column['enumarray'] as $id => $opcion)
                                            <option value="{{ $id }}" {{ ($valor == $id ? "selected='selected'" : "") }}>{!! $opcion !!}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @elseif(($column['type'] == 'file')||($column['type'] == 'image')||($column['type'] == 'securefile'))
                                    <!---------------------------- FILE/IMAGE/SECUREFILE ---------------------------------->
                                    {!!$label!!}
                                    <div>
                                        <input type="file" name="{{ $column['campoReal'] }}">
                                        @if($data)
                                            <p class="help-block">{!! $valor !!}</p>
                                        @endif
                                    </div>
                                @elseif($column['type'] == 'numeric')
                                    <!---------------------------- NUMERIC ---------------------------------->
                                    {!!$label!!}
                                    <div class="col-sm-3">
                                        <input type="number" step="any" name="{{ $column['campoReal'] }}" value="{{ $valor }}" {!! arrayToFields    ($arr) !!}>
                                    </div>
                                @else
                                    <!---------------------------- DEFAULT ---------------------------------->
                                    {!!$label!!}
                                    <div>
                                        <input type="text" name="{{ $column['campoReal'] }}" value="{{ $valor }}" {!! arrayToFields($arr) !!}>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="card-footer">
                <input type="submit" value="{{trans('csgtcrud::crud.guardar')}}" class="btn btn-primary">&nbsp;
                <a href="javascript:window.history.back();" class="btn btn-default">{{trans('csgtcrud::crud.cancelar')}}</a>
            </div>
        </div>
    </form>
@endsection

@section ('javascript')
    <script type="text/javascript">
        $(function() {
            @if($uses['dates'])
                $('.catalogoFecha').datetimepicker();
            @endif
            @if($uses['selectize'])
                $('.selectpicker').selectize();
            @endif
            @if($uses['summernote'])
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
