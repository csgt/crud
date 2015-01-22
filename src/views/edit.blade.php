@extends('template/template')

@section('content')
	<ol class="breadcrumb">
	  <li><a href="{{ URL::to($breadcrum['padre']['ruta']) }}">{{ $breadcrum['padre']['titulo'] }}</a></li>
	  <li class="active">{{ $breadcrum['hijo'] }}</li>
	</ol>
	@if(!$data)
		{{ Form::open(array('url'=>URL::to($breadcrum['padre']['ruta'] . $nuevasVars),'class'=>'form-horizontal','id'=>'frmCrud', 'files'=>'true')) }}
	@else
		{{ Form::open(array('url'=>URL::to($breadcrum['padre']['ruta'] . $nuevasVars),'class'=>'form-horizontal', 'method'=>'put','id'=>'frmCrud', 'files'=>'true')) }}
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
					@if($data)
						<div class="col-sm-2">&nbsp;</div>
						<div class="col-sm-10">
							* Dejar en blanco para no cambiar {{ $columna['nombre'] }}
						</div>
					@endif
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
					      {{ Form::checkbox($columna['campoReal'], '1', ($valor == 1? true:false)) }} {{ $columna['nombre'] }}
					    </label>
				    </div>
				  </div>

				@elseif($columna['tipo'] == 'date')
					<?php 
						$datearray = explode('-', $valor); 
						if (count($datearray) == 3) $laFecha = $datearray[2] . '/' . $datearray[1] . '/' . $datearray[0];
						else $laFecha = '';
						$arr['data-date-language']  = 'es';
						$arr['data-date-pickTime']  = 'false';
						$arr['data-bv-date-format'] = 'DD/MM/YYYY';
						$arr['data-bv-date']        = 'true';
						$arr['data-format']         = 'dd/MM/yyyy';
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
						$datearray2 = explode(' ', $valor); 
						if (count($datearray2)==2) {
							$datearray  = explode('-', $datearray2[0]);
							$laFecha    = $datearray[2] . '/' . $datearray[1] . '/' . $datearray[0] . ' ' . $datearray2[1];
						}
						else $laFecha = '';
						$arr['data-date-language'] = 'es';
						$arr['data-bv-date-format'] = 'DD/MM/YYYY';
						$arr['data-bv-date']        = 'true';
						$arr['data-format']         = 'dd/MM/yyyy';
					?>
					{{$label}}
					<div class="col-sm-10">
						<div id="div{{$columna['campoReal']}}" class="input-group date catalogoFecha">
							{{ Form::text($columna['campoReal'], $laFecha, $arr) }}
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
						{{ Form::select($columna['combokey'], $combos[$columna['alias']], $combokey, $arr) }}
					</div>
				@elseif($columna['tipo'] == 'enum')
					<?php
						$arr['class'] = 'selectpicker form-control';
						$arr['data-width'] = 'auto';
					?>
					{{$label}}
					<div class="col-sm-10">
						{{ Form::select($columna['campoReal'], $columna['enumarray'], $valor,$arr) }}
					</div>
				@elseif($columna['tipo'] == 'file')
					{{$label}}
					<div class="col-sm-10">
						{{ Form::file($columna['campoReal']) }}
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