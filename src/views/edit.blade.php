@extends('template/template')

@section('content')
	<ol class="breadcrumb">
	  <li><a href="{{ URL::to($breadcrum['padre']['ruta']) }}">{{ $breadcrum['padre']['titulo'] }}</a></li>
	  <li class="active">{{ $breadcrum['hijo'] }}</li>
	</ol>
	@if(!$data)
		{{ Form::open(array('url'=>URL::to($breadcrum['padre']['ruta']),'class'=>'form-horizontal','id'=>'frmCrud')) }}
	@else
		{{ Form::open(array('url'=>URL::to($breadcrum['padre']['ruta']),'class'=>'form-horizontal', 'method'=>'put','id'=>'frmCrud')) }}
	@endif
		@foreach($columnas as $columna)
			<?php 
				$valor = ($data ? $data->$columna['campoReal'] : ''); 
				$label = '<label for="' . $columna['campoReal'] . '" class="col-sm-2 control-label">' . $columna['nombre'] . '</label>'; 
				$arr   = array('class'=>'form-control');
				//dd($columnas);
				foreach ($columna['reglas'] as $regla) {
					$arr['data-bv-' . $regla] = 'true';
					$arr['data-bv-' . $regla . '-message'] = $columna['reglasmensaje'];
				}

			?>
			<div class="form-group">

	    	@if($columna['tipo'] == 'password')
	    		{{$label}}
	    		<div class="col-sm-5">
	    			<?php
	    				$arr['placeholder'] ='Password';
							$arr['data-bv-identical'] = 'true';
							$arr['data-bv-identical-field'] = $columna['campoReal'] . 'confirm';
							$arr['data-bv-identical-message'] = 'Las passwords no coinciden';

							if (!$data) {
	    					$arr['data-bv-notempty'] = 'true';
	    					$arr['data-bv-notempty-message'] = 'La password es requerida';
      				}
	    			?>
						{{ Form::password($columna['campoReal'], $arr) }}
					</div>
					<div class="col-sm-5">
						<?php
							$arr['data-bv-identical-field'] = $columna['campoReal'];
						?>
						{{ Form::password($columna['campoReal'] . "confirm", $arr) }}
					</div>

				@elseif($columna['tipo'] == 'text')
					{{$label}}
					<div class="col-sm-10">
						{{ Form::textarea($columna['campoReal'], $valor, $arr) }}
					</div>
				
				@elseif($columna['tipo'] == 'bool')
					<div class="col-sm-2">&nbsp;</div>	
					<div class="col-sm-10">
						<div class="checkbox">
					    <label>
					      {{ Form::checkbox($columna['campoReal'], '1', (Input::old($columna['campoReal']) == 1)? true:false) }} {{ $columna['nombre'] }}
					    </label>
				    </div>
				  </div>

				@elseif($columna['tipo'] == 'date')
					<?php 
						$arr = explode('-', $valor); 
						if (count($arr) == 3) $laFecha = $arr[2] . '/' . $arr[1] . '/' . $arr[0];
						else $laFecha = '';
						$arr['data-date-language'] = 'es';
						$arr['data-date-pickTime'] = 'false';
					?>
					{{$label}}
					<div class="col-sm-10">
						<div id="div{{$columna['campoReal']}}" class="input-group date catalogoFecha">
							{{ Form::text($columna['campoReal'], '' , $arr) }}
						  <span class="input-group-addon"><span class="glyphicon glyphicon-calendar"></span></span>
						</div>
					</div>

				@elseif($columna['tipo'] == 'datetime')
					<?php 
						$arr = explode('-', $valor); 
						if (count($arr)==3) {
							$arr2    = explode(' ', $arr[2]);
							$laFecha = $arr2[0] . '/' . $arr[1] . '/' . $arr[0] . ' ' . $arr2[1];
						}
						else $laFecha = '';
						$arr['data-date-language'] = 'es';
					?>
					{{$label}}
					<div class="col-sm-10">
						<div id="div{{$columna['campoReal']}}" class="input-group date catalogoFecha">
							{{ Form::text($columna['campoReal'], $valor, $arr) }}
						  <span class="input-group-addon"><span class="glyphicon glyphicon-calendar"></span></span>
						</div>
					</div>

				@elseif($columna['tipo'] == 'combobox')
					<?php
						$arr['class'] = 'selectpicker form-control';
						$arr['data-width'] = 'auto';
					?>
					{{$label}}
					<div class="col-sm-10">
						<?php $combokey = ($data ? $data->$columna['combokey'] : '') ?>
						{{ Form::select($columna['combokey'], $combos[$columna['campoReal']], $combokey, $arr) }}
					</div>

	    	@else 
	    		{{$label}}
					<div class="col-sm-10">
	    			{{ Form::text($columna['campoReal'], $valor, $arr) }}
	    		</div>
	    	@endif
		   
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
			$('#frmCrud').bootstrapValidator({
				message: 'Revisar campo',
				feedbackIcons: {
          valid: 'glyphicon glyphicon-ok',
          invalid: 'glyphicon glyphicon-remove',
          validating: 'glyphicon glyphicon-refresh'
        }
			});
		});
	</script>
@stop