@extends($template)

@section('content')
	<ol class="breadcrumb">
	  <li><a href="{{ URL::to($breadcrum['padre']['ruta']) }}">{{ $breadcrum['padre']['titulo'] }}</a></li>
	  <li class="active">{{ $breadcrum['hijo'] }}</li>
	</ol>
	@if(!$data)
		{{ Form::open(array('url'=>URL::to($pathstore . $nuevasVars),'class'=>'form-horizontal','id'=>'frmCrud', 'files'=>'true')) }}
	@else
		{{ Form::open(array('url'=>URL::to($pathstore . $nuevasVars),'class'=>'form-horizontal', 'method'=>'put','id'=>'frmCrud', 'files'=>'true')) }}
	@endif
		@foreach($columnas as $columna)
			<?php 
				$valor = ($data ? $data->{$columna['campoReal']} : $columna['default']); 
				$label = '<label for="' . $columna['campoReal'] . '" class="col-sm-2 control-label">' . $columna['nombre'] . '</label>'; 
				$arr   = array('class'=>'form-control');
				//dd($columnas);
				foreach ($columna['reglas'] as $regla) {
					$arr['data-bv-' . $regla] = 'true';
					$arr['data-bv-' . $regla . '-message'] = $columna['reglasmensaje'];
				}

			?>
			<div class="form-group">
				<!---------------------------- PASSWORD ---------------------------------->
	    	@if($columna['tipo'] == 'password')
	    		{{$label}}
	    		<div class="col-sm-5">
	    			<?php
							$arr['placeholder']               = 'Password';
							$arr['data-bv-identical']         = 'true';
							$arr['data-bv-identical-field']   = $columna['campoReal'] . 'confirm';
							$arr['data-bv-identical-message'] = 'Las passwords no coinciden';

							if (!$data) {
								$arr['data-bv-notempty']         = 'true';
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
						@if($data)
							<p class="help-block">* Dejar en blanco para no cambiar {{ $columna['nombre'] }}</p>
						@endif
					</div>
				<!---------------------------- TEXTAREA ---------------------------------->
				@elseif($columna['tipo'] == 'textarea')
					{{$label}}
					<div class="col-sm-10">
						{{ Form::textarea($columna['campoReal'], $valor, $arr) }}
					</div>
				<!---------------------------- BOOLEAN ---------------------------------->
				@elseif($columna['tipo'] == 'bool')
					<div class="col-sm-2">&nbsp;</div>	
					<div class="col-sm-10">
						<div class="checkbox">
					    <label>
					      {{ Form::checkbox($columna['campoReal'], '1', ($valor == 1? true:false)) }} {{ $columna['nombre'] }}
					    </label>
				    </div>
				  </div>
				<!---------------------------- DATE ---------------------------------->
				@elseif($columna['tipo'] == 'date')
					<?php 
						$datearray = explode('-', $valor); 
						if (count($datearray) == 3) $laFecha = $datearray[2] . '/' . $datearray[1] . '/' . $datearray[0];
						else $laFecha = null;
						$arr['data-date-locale']    = 'es';
						$arr['data-date-language']  = 'es'; //Backwards compatible con datepicker 2
						$arr['data-date-pickTime']  = 'false'; //Backwards compatible con datepicker 2
						$arr['data-date-format']    = 'DD/MM/YYYY';
						$arr['data-bv-date-format'] = 'DD/MM/YYYY';
						$arr['data-bv-date']        = 'true';
					?>
					{{$label}}
					<div class="col-sm-10">
						<div id="div{{$columna['campoReal']}}" class="input-group date catalogoFecha">
							{{ Form::text($columna['campoReal'], $laFecha , $arr) }}
						  <span class="input-group-addon"><span class="glyphicon glyphicon-calendar"></span></span>
						</div>
					</div>
				<!---------------------------- DATETIME ---------------------------------->
				@elseif($columna['tipo'] == 'datetime')
					<?php 
						$datearray2 = explode(' ', $valor); 
						if (count($datearray2)==2) {
							$hora = explode(':', $datearray2[1]);
							$datearray  = explode('-', $datearray2[0]);
							$laFecha    = $datearray[2] . '/' . $datearray[1] . '/' . $datearray[0] . ' ' . $hora[0] . ':' . $hora[1];
						}
						else $laFecha = null;
						$arr['data-date-locale']    = 'es';
						$arr['data-date-language']  = 'es'; //Backwards compatible con datepicker 2
						$arr['data-date-format']    = 'DD/MM/YYYY HH:mm';
						$arr['data-bv-date-format'] = 'DD/MM/YYYY HH:mm';
						$arr['data-bv-date']        = 'true';
					?>
					{{$label}}
					<div class="col-sm-10">
						<div id="div{{$columna['campoReal']}}" class="input-group date catalogoFecha">
							{{ Form::text($columna['campoReal'], $laFecha, $arr) }}
						  <span class="input-group-addon"><span class="glyphicon glyphicon-calendar"></span></span>
						</div>
					</div>
				<!---------------------------- COMBOBOX ---------------------------------->
				@elseif($columna['tipo'] == 'combobox')
					<?php
						$arr['class']      = 'selectpicker form-control';
						$arr['data-width'] = 'auto';
					?>
					{{$label}}
					<div class="col-sm-10">
						<?php $combokey = ($data ? $data->{$columna['combokey']} : '') ?>
						{{ Form::select($columna['combokey'], $combos[$columna['alias']], $combokey, $arr) }}
					</div>
				<!---------------------------- ENUM ---------------------------------->
				@elseif($columna['tipo'] == 'enum')
					<?php
						$arr['class'] = 'selectpicker form-control';
						$arr['data-width'] = 'auto';
					?>
					{{$label}}
					<div class="col-sm-10">
						{{ Form::select($columna['campoReal'], $columna['enumarray'], $valor,$arr) }}
					</div>
				<!---------------------------- FILE/IMAGE ---------------------------------->
				@elseif(($columna['tipo'] == 'file')||($columna['tipo'] == 'image')||($columna['tipo'] == 'securefile'))
					{{$label}}
					<div class="col-sm-10">
						{{ Form::file($columna['campoReal']) }}
						@if($data)
							<p class="help-block">{{ $valor }}</p>
						@endif
					</div>
				<!---------------------------- NUMERIC ---------------------------------->
				@elseif($columna['tipo'] == 'numeric')
					{{$label}}
					<div class="col-sm-3">
	    			{{ Form::text($columna['campoReal'], $valor, $arr) }}
	    		</div>
				<!---------------------------- DEFAULT ---------------------------------->
	    	@else 
	    		{{$label}}
					<div class="col-sm-10">
	    			{{ Form::text($columna['campoReal'], $valor, $arr) }}
	    		</div>
	    	@endif
		   
  		</div>
		@endforeach
		<div class="form-group">
			<div class="col-sm-offset-2 col-sm-10">
				{{ Form::submit('Guardar',  array('class' => 'btn btn-primary')) }}&nbsp;
				<a href="javascript:window.history.back();" class="btn btn-default">Cancelar</a>
			</div>	
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