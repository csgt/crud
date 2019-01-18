@extends($template)
@section('breadcrumb')
	{!! $breadcrumb !!}
@stop
@section('content')
@php
	$includeDates = false;
	$includeSelecize = false;
	$includeSummernote = false;

	foreach ($columns as $column) {
		if (($column['type'] == 'date') || ($column['type'] == 'datetime' || $column['type'] == 'time')) {
			$includeDates = true;
		}

		if (($column['type'] == 'combobox') || ($column['type'] == 'enum') || $column['type'] == 'multi') {
			$includeSelecize = true;
		}

		if ($column['type'] == 'summernote') {
			$includeSummernote = true;
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
  	<link type="text/css" rel="stylesheet" href="{!!config('csgtcrud.pathToAssets','/')!!}css/formValidation.min.css">

  	@if($includeDates)
		<link type="text/css" rel="stylesheet" href="{!!config('csgtcrud.pathToAssets','/')!!}css/bootstrap-datetimepicker.min.css">
	@endif

 	@if($includeSelecize)
		<link type="text/css" rel="stylesheet" href="{!!config('csgtcrud.pathToAssets','/')!!}css/selectize.css">
		<link type="text/css" rel="stylesheet" href="{!!config('csgtcrud.pathToAssets','/')!!}css/selectize.bootstrap3.css">
	@endif

	@if($includeSummernote)
		<link type="text/css" rel="stylesheet" href="{!!config('csgtcrud.pathToAssets','/')!!}css/summernote.min.css">
	@endif
	<div class="card">
		<div class="card-body">
			<form method="POST" action="/{{$pathstore . $queryParameters}}" class="form-horizontal" id="frmCrud" enctype="multipart/form-data">
				@if($data)
					<input type="hidden" name="_method" value="PUT">
				@endif
				{{csrf_field()}}
				@foreach($columns as $column)
					@php
						$valor = ($data ? $data->{$column['campoReal']} : $column['default']);
						$label = '<label for="' . $column['campoReal'] . '" class="col-sm-2 control-label">' . $column['name'] . '</label>';
						$arr = ['class' => 'form-control'];
						//dd($columns);
						foreach ($column['validationRules'] as $regla) {
							$arr['data-fv-' . $regla] = 'true';
							$arr['data-fv-' . $regla . '-message'] = $column['validationRulesMessage'];
						}
					@endphp
					<div class="form-group">
						<!---------------------------- PASSWORD ---------------------------------->
			    		@if($column['type'] == 'password')
			    			{!!$label!!}
			    			<div class="col-sm-5">
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
							<div class="col-sm-5">
								@php
									$arr['data-fv-identical-field'] = $column['campoReal'];
								@endphp
								<input type="password" name="{{ $column['campoReal'] . 'confirm' }}" {!! arrayToFields($arr) !!}>
								@if($data)
									<p class="help-block">* Dejar en blanco para no cambiar {!! $column['name'] !!}</p>
								@endif
							</div>
						<!---------------------------- TEXTAREA ---------------------------------->
						@elseif($column['type'] == 'textarea')
							{!!$label!!}
							<div class="col-sm-10">
								<textarea name="{{$column['campoReal']}}" {!! arrayToFields($arr) !!}>{!! $valor !!}</textarea>
							</div>
						<!---------------------------- SUMMERNOTE ---------------------------------->
						@elseif($column['type'] == 'summernote')
							{!!$label!!}
							<div class="col-sm-10">
								<?php $arr = ['class' => 'summernote'];?>
								<textarea name="{{$column['campoReal']}}" {!! arrayToFields($arr) !!}>{!! $valor !!}</textarea>
							</div>
						<!---------------------------- BOOLEAN ---------------------------------->
						@elseif($column['type'] == 'bool')
							<div class="col-sm-2">&nbsp;</div>
							<div class="col-sm-10">
								<div class="checkbox">
							    <label>
							    	<input type="checkbox" name="{{$column['campoReal']}}" value="1" {{$valor == 1? "checked":""}}>
							    	{!! $column['name'] !!}
							    </label>
							    <input class="hiddencheckbox" type='hidden' value='0' name='{{$column['campoReal']}}'>
						    </div>
						  </div>
						<!---------------------------- DATE ---------------------------------->
						@elseif($column['type'] == 'date')
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
								<div id="div{!!$column['campoReal']!!}" class="input-group date catalogoFecha">
									<input type="text" name="{{ $column['campoReal'] }}" value="{{ $laFecha }}" {!! arrayToFields($arr) !!}>
								  	<span class="input-group-addon"><span class="glyphicon glyphicon-calendar"></span></span>
								</div>
							</div>
						<!---------------------------- DATETIME ---------------------------------->
						@elseif($column['type'] == 'datetime')
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
								<div id="div{!!$column['campoReal']!!}" class="input-group date catalogoFecha">
									<input type="text" name="{{ $column['campoReal'] }}" value="{{ $laFecha }}" {!! arrayToFields($arr) !!}>
								  	<span class="input-group-addon"><span class="glyphicon glyphicon-calendar"></span></span>
								</div>
							</div>
						<!---------------------------- TIME ---------------------------------->
						@elseif($column['type'] == 'time')
							@php
								$arr['data-date-locale'] = 'es';
								$arr['data-date-language'] = 'es'; //Backwards compatible con datepicker 2
								$arr['data-date-format'] = 'HH:mm';
								$arr['data-fv-date-format'] = 'HH:mm';
							@endphp
							{!!$label!!}
							<div class="col-sm-10">
								<div id="div{!!$column['campoReal']!!}" class="input-group date catalogoFecha">
									<input type="text" name="{{ $column['campoReal'] }}" value="{{ $valor }}" {!! arrayToFields($arr) !!}>
								  	<span class="input-group-addon"><span class="glyphicon glyphicon-calendar"></span></span>
								</div>
							</div>
						<!---------------------------- COMBOBOX ---------------------------------->
						@elseif($column['type'] == 'combobox')
							@php
								$arr['class'] = 'selectpicker form-control';
								$arr['data-width'] = 'auto';
							@endphp
							{!!$label!!}
							<div class="col-sm-10">
								<?php $campo = ($data ? $data->{$column['field']} : '')?>
								<select name="{{ $column['field'] }}" {!! arrayToFields($arr) !!}>
									@foreach($combos[$column['alias']] as $id => $opcion)
									<option value="{{ $id }}" {{ ($campo == $id ? "selected='selected'" : "") }}>{!! $opcion !!}</option>
									@endforeach
								</select>
							</div>
						<!---------------------------- MULTI ---------------------------------->
						@elseif($column['type'] == 'multi')
							@php
								$arr['class'] = 'selectpicker form-control';
								$arr['data-width'] = 'auto';
							@endphp
							{!!$label!!}
							<div class="col-sm-10">
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
						<!---------------------------- ENUM ---------------------------------->
						@elseif($column['type'] == 'enum')
							@php
								$arr['class'] = 'selectpicker form-control';
								$arr['data-width'] = 'auto';
							@endphp
							{!!$label!!}
							<div class="col-sm-10">
								<select name="{{ $column['campoReal'] }}" {!! arrayToFields($arr) !!}>
									@foreach($column['enumarray'] as $id => $opcion)
									<option value="{{ $id }}" {{ ($valor == $id ? "selected='selected'" : "") }}>{!! $opcion !!}</option>
									@endforeach
								</select>
							</div>
						<!---------------------------- FILE/IMAGE/SECUREFILE ---------------------------------->
						@elseif(($column['type'] == 'file')||($column['type'] == 'image')||($column['type'] == 'securefile'))
							{!!$label!!}
							<div class="col-sm-10">
								<input type="file" name="{{ $column['campoReal'] }}">
								@if($data)
									<p class="help-block">{!! $valor !!}</p>
								@endif
							</div>
						<!---------------------------- NUMERIC ---------------------------------->
						@elseif($column['type'] == 'numeric')
							{!!$label!!}
							<div class="col-sm-3">
			    				<input type="number" step="any" name="{{ $column['campoReal'] }}" value="{{ $valor }}" {!! arrayToFields    ($arr) !!}>
			    			</div>
						<!---------------------------- DEFAULT ---------------------------------->
				    	@else
				    		{!!$label!!}
							<div class="col-sm-10">
								<input type="text" name="{{ $column['campoReal'] }}" value="{{ $valor }}" {!! arrayToFields($arr) !!}>
				    		</div>
				    	@endif
		  			</div>
				@endforeach
				<div class="form-group">
					<div class="col-sm-offset-2 col-sm-10">
						<input type="submit" value="{{trans('csgtcrud::crud.guardar')}}" class="btn btn-primary">&nbsp;
						<a href="javascript:window.history.back();" class="btn btn-default">{{trans('csgtcrud::crud.cancelar')}}</a>
					</div>
				</div>
			</form>
		</div>
	</div>
@endsection

@section ('javascript')
  <script src="{!!config('csgtcrud.pathToAssets','/')!!}js/formValidation.min.js"></script>
	<script src="{!!config('csgtcrud.pathToAssets','/')!!}js/framework/bootstrap.min.js"></script>

  @if($includeDates)
		<script src="{!!config('csgtcrud.pathToAssets','/')!!}js/moment-with-locales.min.js"></script>
		<script src="{!!config('csgtcrud.pathToAssets','/')!!}js/bootstrap-datetimepicker.min.js"></script>
	@endif

 	@if($includeSelecize)
		<script src="{!!config('csgtcrud.pathToAssets','/')!!}js/selectize.min.js"></script>
	@endif

	@if($includeSummernote)
		<script src="{!!config('csgtcrud.pathToAssets','/')!!}js/summernote.min.js"></script>
		<script src="{!!config('csgtcrud.pathToAssets','/')!!}js/summernote-es-ES.js"></script>
	@endif

	<script type="text/javascript">
		$(function() {
			@if($includeDates)
				$('.catalogoFecha').datetimepicker();
			@endif
			@if($includeSelecize)
				$('.selectpicker').selectize();
			@endif
			@if($includeSummernote)
				$('.summernote').summernote({
					'lang'   : 'es-ES',
				});
			@endif
			$('#frmCrud').formValidation({
				message: '{{trans('csgtcrud::crud.revisarcampo')}}',
				feedbackIcons: {
          valid: 'glyphicon glyphicon-ok',
          invalid: 'glyphicon glyphicon-remove',
          validating: 'glyphicon glyphicon-refresh'
        }
			});
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
