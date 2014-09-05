@extends('template/template')

@section('content')
	<ol class="breadcrumb">
	  <li><a href="{{ URL::to($breadcrum['padre']['ruta']) }}">{{ $breadcrum['padre']['titulo'] }}</a></li>
	  <li class="active">{{ $breadcrum['hijo'] }}</li>
	</ol>
	@if(!$data)
		{{ Form::open(array('url'=>URL::to($breadcrum['padre']['ruta']),'class'=>'form-horizontal')) }}
	@else
		{{ Form::open(array('url'=>URL::to($breadcrum['padre']['ruta']),'class'=>'form-horizontal', 'method'=>'put')) }}
	@endif
		@foreach($columnas as $columna)
			<?php $valor = ($data ? $data->$columna['campoReal'] : ''); ?>
			<div class="form-group">
				@if($columna['tipo'] != 'bool')
		    	<label for="{{ $columna['campoReal'] }}" class="col-sm-2 control-label">{{ $columna['nombre'] }}</label>
		    @else
		    	<div class="col-sm-2">&nbsp;</div>
		    @endif
		    <div class="col-sm-10">
		    	@if($columna['tipo'] == 'password')
						{{ Form::password($columna['campoReal'], array('class' => 'form-control')) }}
					@elseif($columna['tipo'] == 'text')
						{{ Form::textarea($columna['campoReal'], $valor, array('class' => 'form-control')) }}
					@elseif($columna['tipo'] == 'bool')
						
						<div class="checkbox">
					    <label>
					      {{ Form::checkbox($columna['campoReal'], '1', (Input::old($columna['campoReal']) == 1)? true:false) }} {{ $columna['nombre'] }}
					    </label>
					  </div>
					@elseif($columna['tipo'] == 'date')
						<?php 
							$arr = explode('-', $valor); 
							if (count($arr) == 3) $laFecha = $arr[2] . '/' . $arr[1] . '/' . $arr[0];
							else $laFecha = '';
						?>
						<div id="div{{$columna['campoReal']}}" class="input-group date catalogoFecha">
							{{ Form::text($columna['campoReal'], '' , array('class' => 'form-control', data-date-language=>'es', data-date-pickTime=>false)) }}
						  <span class="input-group-addon"><span class="glyphicon glyphicon-calendar"></span></span>
						</div>
					@elseif($columna['tipo'] == 'datetime')
						<?php 
							$arr = explode('-', $valor); 
							if (count($arr)==3) {
								$arr2    = explode(' ', $arr[2]);
								$laFecha = $arr2[0] . '/' . $arr[1] . '/' . $arr[0] . ' ' . $arr2[1];
							}
							else $laFecha = '';
						?>
						<div id="div{{$columna['campoReal']}}" class="input-group date catalogoFecha">
							{{ Form::text($columna['campoReal'], $valor, array('class' => 'form-control', 'data-date-language'=>'es')) }}
						  <span class="input-group-addon"><span class="glyphicon glyphicon-calendar"></span></span>
						</div>
					@elseif($columna['tipo'] == 'combobox')
						<?php $combokey = ($data ? $data->$columna['combokey'] : '') ?>
						{{ Form::select($columna['combokey'], $combos[$columna['campoReal']], $combokey, array('class' => 'selectpicker form-control')) }}
		    	@else 
		    		{{ Form::text($columna['campoReal'], $valor, array('class' => 'form-control')) }}
		    	@endif
		    </div>
  		</div>
		@endforeach
		<div class="form-group">
			<div class="col-sm-2">&nbsp;</div>
			<div class="col-sm-10">{{ Form::submit('Guardar', array('class' => 'btn btn-primary')) }}</div>	
		</div>
	{{ Form::close() }}
	<script type="text/javascript">
		$(function() {
			$('.catalogoFecha').datetimepicker();
			$('.selectpicker').selectpicker();
		});
	</script>
@stop