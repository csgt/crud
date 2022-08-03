@extends($template)
@section('breadcrumb')
	{!! $breadcrumb !!}
@endsection
@section('content')
@php
	$includefechas = false;
	$includeselect = false;
	$includesummernote = false;

	foreach ($columnas as $columna) {
		if (($columna['tipo'] == 'date') || ($columna['tipo'] == 'datetime' || $columna['tipo'] == 'time')) {
			$includefechas = true;
		}

		if (($columna['tipo'] == 'combobox') || ($columna['tipo'] == 'enum') || $columna['tipo'] == 'multi') {
			$includeselect = true;
		}

		if ($columna['tipo'] == 'summernote') {
			$includesummernote = true;
		}
	}
	function arrayToFields($arr) {
		$callback = function ($key, $value) {
			return $key . "=\"" . $value . "\"";
		};
		$fields = implode(" ", array_map($callback, array_keys($arr), $arr));

		return $fields;
	}
@endphp
<form id="frmCrud">
    <div class="card">
        <div class="card-body">
            @foreach($columnas as $columna)
                @php
                    $valor = ($data ? $data->{$columna['campoReal']} : $columna['default']);
                    $label = '<label for="' . $columna['campoReal'] . '" class="col-sm-2 control-label">' . $columna['nombre'] . '</label>';
                    $arr = ['class' => 'form-control'];
                    //dd($columnas);
                    foreach ($columna['reglas'] as $regla) {
                        $arr['data-fv-' . $regla] = 'true';
                        $arr['data-fv-' . $regla . '-message'] = $columna['reglasmensaje'];
                    }
                @endphp
                <div class="form-group">
                    <!---------------------------- PASSWORD ---------------------------------->
                    @if($columna['tipo'] == 'password')
                        {!!$label!!}
                        <div class="col-sm-5">
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
                        </div>
                        <div class="col-sm-5">
                            @php
                                $arr['data-fv-identical-field'] = $columna['campoReal'];
                            @endphp
                            <input type="password" name="{{ $columna['campoReal'] . 'confirm' }}" {!! arrayToFields($arr) !!}>
                            @if($data)
                                <p class="help-block">* Dejar en blanco para no cambiar {!! $columna['nombre'] !!}</p>
                            @endif
                        </div>
                    <!---------------------------- TEXTAREA ---------------------------------->
                    @elseif($columna['tipo'] == 'textarea')
                        {!!$label!!}
                        <div class="col-sm-10">
                            <textarea name="{{$columna['campoReal']}}" {!! arrayToFields($arr) !!}>{!! $valor !!}</textarea>
                        </div>
                    <!---------------------------- SUMMERNOTE ---------------------------------->
                    @elseif($columna['tipo'] == 'summernote')
                        {!!$label!!}
                        <div class="col-sm-10">
                            <?php $arr = ['class' => 'summernote'];?>
                            <textarea name="{{$columna['campoReal']}}" {!! arrayToFields($arr) !!}>{!! $valor !!}</textarea>
                        </div>
                    <!---------------------------- BOOLEAN ---------------------------------->
                    @elseif($columna['tipo'] == 'bool')
                        <div class="col-sm-2">&nbsp;</div>
                        <div class="col-sm-10">
                            <div class="checkbox">
                            <label>
                                <input type="checkbox" name="{{$columna['campoReal']}}" value="1" {{$valor == 1? "checked":""}}>
                                {!! $columna['nombre'] !!}
                            </label>
                            <input class="hiddencheckbox" type='hidden' value='0' name='{{$columna['campoReal']}}'>
                        </div>
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
                        {!!$label!!}
                        <div class="col-sm-10">
                            <div id="div{!!$columna['campoReal']!!}" class="input-group date catalogoFecha">
                                <input type="text" name="{{ $columna['campoReal'] }}" value="{{ $laFecha }}" {!! arrayToFields($arr) !!}>
                                <span class="input-group-addon input-group-append">
                                    <span class="input-group-text">
                                        <i class="fa fa-calendar"></i>
                                    </span>
                                </span>
                            </div>
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
                        {!!$label!!}
                        <div class="col-sm-10">
                            <div id="div{!!$columna['campoReal']!!}" class="input-group date catalogoFecha">
                                <input type="text" name="{{ $columna['campoReal'] }}" value="{{ $laFecha }}" {!! arrayToFields($arr) !!}>
                                <span class="input-group-addon input-group-append">
                                    <span class="input-group-text">
                                        <i class="fa fa-calendar"></i>
                                    </span>
                                </span>
                            </div>
                        </div>
                    <!---------------------------- TIME ---------------------------------->
                    @elseif($columna['tipo'] == 'time')
                        @php
                            $arr['data-date-locale'] = 'es';
                            $arr['data-date-language'] = 'es'; //Backwards compatible con datepicker 2
                            $arr['data-date-format'] = 'HH:mm';
                            $arr['data-fv-date-format'] = 'HH:mm';
                        @endphp
                        {!!$label!!}
                        <div class="col-sm-10">
                            <div id="div{!!$columna['campoReal']!!}" class="input-group date catalogoFecha">
                                <input type="text" name="{{ $columna['campoReal'] }}" value="{{ $valor }}" {!! arrayToFields($arr) !!}>
                                <span class="input-group-addon input-group-append">
                                    <span class="input-group-text">
                                        <i class="fa fa-clock"></i>
                                    </span>
                                </span>
                            </div>
                        </div>
                    <!---------------------------- COMBOBOX ---------------------------------->
                    @elseif($columna['tipo'] == 'combobox')
                        @php
                            $arr['class'] = 'selectpicker form-control';
                            $arr['data-width'] = 'auto';
                        @endphp
                        {!!$label!!}
                        <div class="col-sm-10">
                            <?php $campo = ($data ? $data->{$columna['campo']} : '')?>
                            <select name="{{ $columna['campo'] }}" {!! arrayToFields($arr) !!}>
                                @foreach($combos[$columna['alias']] as $id => $opcion)
                                <option value="{{ $id }}" {{ ($campo == $id ? "selected='selected'" : "") }}>{!! $opcion !!}</option>
                                @endforeach
                            </select>
                        </div>
                    <!---------------------------- MULTI ---------------------------------->
                    @elseif($columna['tipo'] == 'multi')
                        @php
                            $arr['class'] = 'selectpicker form-control';
                            $arr['data-width'] = 'auto';
                        @endphp
                        {!!$label!!}
                        <div class="col-sm-10">
                            <?php $campo = ($data ? $data->{$columna['campo']} : '')?>
                            <select multiple="multiple" name="{{ $columna['campo'] }}[]" {!! arrayToFields($arr) !!}>

                                @foreach($combos[$columna['alias']] as $id => $opcion)
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
                    <!---------------------------- ENUM ---------------------------------->
                    @elseif($columna['tipo'] == 'enum')
                        @php
                            $arr['class'] = 'selectpicker form-control';
                            $arr['data-width'] = 'auto';
                        @endphp
                        {!!$label!!}
                        <div class="col-sm-10">
                            <select name="{{ $columna['campoReal'] }}" {!! arrayToFields($arr) !!}>
                                @foreach($columna['enumarray'] as $id => $opcion)
                                <option value="{{ $id }}" {{ ($valor == $id ? "selected='selected'" : "") }}>{!! $opcion !!}</option>
                                @endforeach
                            </select>
                        </div>
                    <!---------------------------- FILE/IMAGE/SECUREFILE ---------------------------------->
                    @elseif(($columna['tipo'] == 'file')||($columna['tipo'] == 'image')||($columna['tipo'] == 'securefile'))
                        {!!$label!!}
                        <div class="col-sm-10">
                            <input type="file" name="{{ $columna['campoReal'] }}">
                            @if($data)
                                <p class="help-block">{!! $valor !!}</p>
                            @endif
                        </div>
                    <!---------------------------- NUMERIC ---------------------------------->
                    @elseif($columna['tipo'] == 'numeric')
                        {!!$label!!}
                        <div class="col-sm-3">
                            <input type="number" step="any" name="{{ $columna['campoReal'] }}" value="{{ $valor }}" {!! arrayToFields    ($arr) !!}>
                        </div>
                    <!---------------------------- DEFAULT ---------------------------------->
                    @else
                        {!!$label!!}
                        <div class="col-sm-10">
                            <input type="text" name="{{ $columna['campoReal'] }}" value="{{ $valor }}" {!! arrayToFields($arr) !!}>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
        <div class="card-footer">
            <input type="submit" value="{{trans('csgtcrud::crud.guardar')}}" class="btn btn-primary">&nbsp;
            <a href="javascript:window.history.back();" class="btn btn-default">{{trans('csgtcrud::crud.cancelar')}}</a>
        </div>
</form>
@endsection

@section ('javascript')
	<script type="text/javascript">
		$(function() {
            $('#frmCrud').submit(function (evt) {
                evt.preventDefault();
                $.ajax({
                    type: '{{ $data ? 'PATCH' : 'POST'}}',
                    url: '/{{$pathstore . $nuevasVars}}',
                    // contentType: 'application/json',
                    headers: {
                        'Accept' : 'application/json',
                        'X-CSRF-TOKEN' : $('meta[name="csrf-token"]').attr('content')
                    },
                    data:  $('#frmCrud').serialize()
                })
                .done(function(res) {
                    console.log(res)
                    window.location = res.redirect;
                })
                .fail(function(err) {
                    if (err.status == 422) {
                        var errorsHtml = ''
                        $.each( err.responseJSON.errors, function( key, value ) {
                            errorsHtml += value[0] + "\n";
                        });
                        alert(errorsHtml)
                    }
                    else {
                        alert(err.responseJSON.message);
                    }
                });

                return false
            });

			@if($includefechas)
				$('.catalogoFecha').datetimepicker();
			@endif
			@if($includeselect)
				$('.selectpicker').selectize();
			@endif
			@if($includesummernote)
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