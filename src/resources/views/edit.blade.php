@extends($template)

@section('content')
	<?php
$includefechas     = false;
$includeselect     = false;
$includesummernote = false;

foreach ($columnas as $columna) {
    if (($columna['tipo'] == 'date') || ($columna['tipo'] == 'datetime')) {
        $includefechas = true;
    }

    if (($columna['tipo'] == 'combobox') || ($columna['tipo'] == 'enum')) {
        $includeselect = true;
    }

    if ($columna['tipo'] == 'summernote') {
        $includesummernote = true;
    }

}
?>
  <link type="text/css" rel="stylesheet" href="{!!config('csgtcrud.pathToAssets','/')!!}css/formValidation.min.css">
  <script src="{!!config('csgtcrud.pathToAssets','/')!!}js/formValidation.min.js"></script>
	<script src="{!!config('csgtcrud.pathToAssets','/')!!}js/framework/bootstrap.min.js"></script>

  @if($includefechas)
		<link type="text/css" rel="stylesheet" href="{!!config('csgtcrud.pathToAssets','/')!!}css/bootstrap-datetimepicker.min.css">
		<script src="{!!config('csgtcrud.pathToAssets','/')!!}js/moment-with-locales.min.js"></script>
		<script src="{!!config('csgtcrud.pathToAssets','/')!!}js/bootstrap-datetimepicker.min.js"></script>
	@endif

 	@if($includeselect)
		<link type="text/css" rel="stylesheet" href="{!!config('csgtcrud.pathToAssets','/')!!}css/selectize.css">
		<link type="text/css" rel="stylesheet" href="{!!config('csgtcrud.pathToAssets','/')!!}css/selectize.bootstrap3.css">
		<script src="{!!config('csgtcrud.pathToAssets','/')!!}js/selectize.min.js"></script>
	@endif

	@if($includesummernote)
		<link type="text/css" rel="stylesheet" href="{!!config('csgtcrud.pathToAssets','/')!!}css/summernote.min.css">
		<script src="{!!config('csgtcrud.pathToAssets','/')!!}js/summernote.min.js"></script>
		<script src="{!!config('csgtcrud.pathToAssets','/')!!}js/summernote-es-ES.js"></script>
	@endif

	<ol class="breadcrumb">
	  <li><a href="{!! URL::to($breadcrum['padre']['ruta']) !!}">{!! $breadcrum['padre']['titulo'] !!}</a></li>
	  <li class="active">{!! $breadcrum['hijo'] !!}</li>
	</ol>
	@if(!$data)
		{!! Form::open(['url'=>URL::to($pathstore . $nuevasVars), 'id'=>'frmCrud', 'files'=>'true']) !!}
	@else
		{!! Form::open(['url'=>URL::to($pathstore . $nuevasVars), 'method'=>'put','id'=>'frmCrud', 'files'=>'true']) !!}
	@endif
	<div class="panel panel-default">
		<div class="panel-body">
			<div class="row">
			@foreach($columnas as $columna)
				<?php
$valor = ($data ? $data->{$columna['campoReal']} : $columna['default']);
$label = '<label for="' . $columna['campoReal'] . '" class="control-label">' . $columna['nombre'] . '</label>';
$arr   = ['class' => 'form-control'];
//dd($columnas);
foreach ($columna['reglas'] as $regla) {
    $arr['data-fv-' . $regla]              = 'true';
    $arr['data-fv-' . $regla . '-message'] = $columna['reglasmensaje'];
}
?>
				<div class="{{ $columna['editClass'] }}">
					<div class="form-group">
			    	@if($columna['tipo'] == 'password')
			    		<!---------------------------- PASSWORD ---------------------------------->
			    		{!!$label!!}
			    		<div class="row">
				    		<div class="col-sm-6">
				    			<?php
$arr['placeholder']               = 'Password';
$arr['data-fv-identical']         = 'true';
$arr['data-fv-identical-field']   = $columna['campoReal'] . 'confirm';
$arr['data-fv-identical-message'] = trans('csgtcrud::crud.passnocoinciden');

if (!$data) {
    $arr['data-fv-notempty']         = 'true';
    $arr['data-fv-notempty-message'] = trans('csgtcrud::crud.passrequerida');
}
?>
								{!! Form::password($columna['campoReal'], $arr) !!}
							</div>
							<div class="col-sm-6">
								<?php
$arr['data-fv-identical-field'] = $columna['campoReal'];
?>
								{!! Form::password($columna['campoReal'] . "confirm", $arr) !!}
								@if($data)
									<p class="help-block">* Dejar en blanco para no cambiar {!! $columna['nombre'] !!}</p>
								@endif
							</div>
						</div>
						@elseif($columna['tipo'] == 'textarea')
							<!---------------------------- TEXTAREA ---------------------------------->
							{!!$label!!}
							{!! Form::textarea($columna['campoReal'], $valor, $arr) !!}

						@elseif($columna['tipo'] == 'summernote')
							<!---------------------------- SUMMERNOTE ---------------------------------->
							{!!$label!!}
							<?php $arr = ['class' => 'summernote'];?>
							{!! Form::textarea($columna['campoReal'], $valor, $arr) !!}
						@elseif($columna['tipo'] == 'bool')
							<!---------------------------- BOOLEAN ---------------------------------->
							<div class="checkbox">
							    <label>
							      {!! Form::checkbox($columna['campoReal'], '1', ($valor == 1? true:false)) !!} {!! $columna['nombre'] !!}
							    </label>
						    </div>
						@elseif($columna['tipo'] == 'date')
							<!---------------------------- DATE ---------------------------------->
							<?php
$datearray = explode('-', $valor);
if (count($datearray) == 3) {
    $laFecha = $datearray[2] . '/' . $datearray[1] . '/' . $datearray[0];
} else {
    $laFecha = null;
}

$arr['data-date-locale']    = 'es';
$arr['data-date-language']  = 'es'; //Backwards compatible con datepicker 2
$arr['data-date-pickTime']  = 'false'; //Backwards compatible con datepicker 2
$arr['data-date-format']    = 'DD/MM/YYYY';
$arr['data-fv-date-format'] = 'DD/MM/YYYY';
$arr['data-fv-date']        = 'true';
?>
							{!!$label!!}
							<div id="div{!!$columna['campoReal']!!}" class="input-group date catalogoFecha">
								{!! Form::text($columna['campoReal'], $laFecha , $arr) !!}
							  <span class="input-group-addon"><span class="glyphicon glyphicon-calendar"></span></span>
							</div>
						@elseif($columna['tipo'] == 'datetime')
							<!---------------------------- DATETIME ---------------------------------->
							<?php
$datearray2 = explode(' ', $valor);
if (count($datearray2) == 2) {
    $hora      = explode(':', $datearray2[1]);
    $datearray = explode('-', $datearray2[0]);
    $laFecha   = $datearray[2] . '/' . $datearray[1] . '/' . $datearray[0] . ' ' . $hora[0] . ':' . $hora[1];
} else {
    $laFecha = null;
}

$arr['data-date-locale']    = 'es';
$arr['data-date-language']  = 'es'; //Backwards compatible con datepicker 2
$arr['data-date-format']    = 'DD/MM/YYYY HH:mm';
$arr['data-fv-date-format'] = 'DD/MM/YYYY HH:mm';
$arr['data-fv-date']        = 'true';
?>
							{!!$label!!}
							<div id="div{!!$columna['campoReal']!!}" class="input-group date catalogoFecha">
								{!! Form::text($columna['campoReal'], $laFecha, $arr) !!}
							  <span class="input-group-addon"><span class="glyphicon glyphicon-calendar"></span></span>
							</div>
						@elseif($columna['tipo'] == 'combobox')
							<!---------------------------- COMBOBOX ---------------------------------->
							<?php
$arr['class']      = 'selectpicker form-control';
$arr['data-width'] = 'auto';
?>
							{!!$label!!}
							<?php $combokey = ($data ? $data->{$columna['combokey']} : '')?>
							{!! Form::select($columna['combokey'], $combos[$columna['alias']], $combokey, $arr) !!}
						@elseif($columna['tipo'] == 'enum')
							<!---------------------------- ENUM ---------------------------------->
							<?php
$arr['class']      = 'selectpicker form-control';
$arr['data-width'] = 'auto';
?>
							{!!$label!!}
							{!! Form::select($columna['campoReal'], $columna['enumarray'], $valor,$arr) !!}
						@elseif(($columna['tipo'] == 'file')||($columna['tipo'] == 'image')||($columna['tipo'] == 'securefile'))
							<!---------------------------- FILE/IMAGE/SECUREFILE ---------------------------------->
							{!!$label!!}
							{!! Form::file($columna['campoReal']) !!}
							@if($data)
								<p class="help-block">{!! $valor !!}</p>
							@endif
						@elseif($columna['tipo'] == 'numeric')
							<!---------------------------- NUMERIC ---------------------------------->
							{!!$label!!}
			    			{!! Form::text($columna['campoReal'], $valor, $arr) !!}

			    	@else
			    		<!---------------------------- DEFAULT ---------------------------------->
			    		{!!$label!!}
		    			{!! Form::text($columna['campoReal'], $valor, $arr) !!}
			    	@endif
		  		</div>
		  	</div>
			@endforeach
		</div>
		</div>
		<div class="panel-footer">
			{!! Form::submit(trans('csgtcrud::crud.guardar'),  array('class' => 'btn btn-primary')) !!}&nbsp;
			<a href="javascript:window.history.back();" class="btn btn-default">{{trans('csgtcrud::crud.cancelar')}}</a>
		</div>
	</div>
	{!! Form::close() !!}
	<script>
		$(function() {
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
			$('#frmCrud').formValidation({
				message: '{{trans('csgtcrud::crud.revisarcampo')}}',
				feedbackIcons: {
          valid: 'glyphicon glyphicon-ok',
          invalid: 'glyphicon glyphicon-remove',
          validating: 'glyphicon glyphicon-refresh'
        }
			});
		});
	</script>
@stop
