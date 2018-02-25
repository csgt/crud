@extends($template)
@section('breadcrumb')
	{!! $breadcrumb !!}
@stop
@section('content')
	<?php
        $includefechas     = false;
        $includeselect     = false;
        $includesummernote = false;

        foreach ($columnas as $columna) {
            if (($columna['tipo'] == 'date')||($columna['tipo']=='datetime')) {
                $includefechas = true;
            }

            if (($columna['tipo'] == 'combobox')||($columna['tipo']=='enum')) {
                $includeselect = true;
            }

            if ($columna['tipo'] == 'summernote') {
                $includesummernote = true;
            }
        }
        function arrayToFields($arr)
        {
            $callback = function ($key, $value) {
                return $key . "=\"" . $value . "\"";
            };
            $fields = implode(" ", array_map($callback, array_keys($arr), $arr));
            return $fields;
        }
    ?>
  	<link type="text/css" rel="stylesheet" href="{!!config('csgtcrud.pathToAssets','/')!!}css/formValidation.min.css">

  	@if($includefechas)
		<link type="text/css" rel="stylesheet" href="{!!config('csgtcrud.pathToAssets','/')!!}css/bootstrap-datetimepicker.min.css">
	@endif

 	@if($includeselect)
		<link type="text/css" rel="stylesheet" href="{!!config('csgtcrud.pathToAssets','/')!!}css/selectize.css">
		<link type="text/css" rel="stylesheet" href="{!!config('csgtcrud.pathToAssets','/')!!}css/selectize.bootstrap3.css">
	@endif

	@if($includesummernote)
		<link type="text/css" rel="stylesheet" href="{!!config('csgtcrud.pathToAssets','/')!!}css/summernote.min.css">
	@endif
	<div class="box">
		<div class="box-body">
			<form method="POST" action="/{{$pathstore . $nuevasVars}}" class="form-horizontal" id="frmCrud" enctype="multipart/form-data">
				@if($data)
					<input type="hidden" name="_method" value="PUT">
				@endif
				{{csrf_field()}}
				@foreach($columnas as $columna)
					<?php
                        $valor = ($data ? $data->{$columna['campoReal']} : $columna['default']);
                        $label = '<label for="' . $columna['campoReal'] . '" class="col-sm-2 control-label">' . $columna['nombre'] . '</label>';
                        $arr   = ['class'=>'form-control'];
                        //dd($columnas);
                        foreach ($columna['reglas'] as $regla) {
                            $arr['data-fv-' . $regla] = 'true';
                            $arr['data-fv-' . $regla . '-message'] = $columna['reglasmensaje'];
                        }

                    ?>
					<div class="form-group">
						<!---------------------------- PASSWORD ---------------------------------->
			    		@if($columna['tipo'] == 'password')
			    			{!!$label!!}
			    			<div class="col-sm-5">
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
			    				<input type="password" name="{{ $columna['campoReal'] }}" {!! arrayToFields($arr) !!}>
							</div>
							<div class="col-sm-5">
								<?php
                                    $arr['data-fv-identical-field'] = $columna['campoReal'];
                                ?>
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
								<?php $arr   = ['class'=>'summernote']; ?>
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
							<div class="col-sm-10">
								<div id="div{!!$columna['campoReal']!!}" class="input-group date catalogoFecha">
									<input type="text" name="{{ $columna['campoReal'] }}" value="{{ $laFecha }}" {!! arrayToFields($arr) !!}>
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
							<div class="col-sm-10">
								<div id="div{!!$columna['campoReal']!!}" class="input-group date catalogoFecha">
									<input type="text" name="{{ $columna['campoReal'] }}" value="{{ $laFecha }}" {!! arrayToFields($arr) !!}>
								  	<span class="input-group-addon"><span class="glyphicon glyphicon-calendar"></span></span>
								</div>
							</div>
						<!---------------------------- COMBOBOX ---------------------------------->
						@elseif($columna['tipo'] == 'combobox')
							<?php
                                $arr['class']      = 'selectpicker form-control';
                                $arr['data-width'] = 'auto';
                            ?>
							{!!$label!!}
							<div class="col-sm-10">
								<?php $campo = ($data ? $data->{$columna['campo']} : '') ?>
								<select name="{{ $columna['campo'] }}" {!! arrayToFields($arr) !!}>
									@foreach($combos[$columna['alias']] as $id => $opcion)
									<option value="{{ $id }}" {{ ($campo == $id ? "selected='selected'" : "") }}>{!! $opcion !!}</option>
									@endforeach
								</select>
							</div>
						<!---------------------------- ENUM ---------------------------------->
						@elseif($columna['tipo'] == 'enum')
							<?php
                                $arr['class'] = 'selectpicker form-control';
                                $arr['data-width'] = 'auto';
                            ?>
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
			    				<input type="text" name="{{ $columna['campoReal'] }}" value="{{ $valor }}" {!! arrayToFields	($arr) !!}>
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

  @if($includefechas)
		<script src="{!!config('csgtcrud.pathToAssets','/')!!}js/moment-with-locales.min.js"></script>
		<script src="{!!config('csgtcrud.pathToAssets','/')!!}js/bootstrap-datetimepicker.min.js"></script>
	@endif

 	@if($includeselect)
		<script src="{!!config('csgtcrud.pathToAssets','/')!!}js/selectize.min.js"></script>
	@endif

	@if($includesummernote)
		<script src="{!!config('csgtcrud.pathToAssets','/')!!}js/summernote.min.js"></script>
		<script src="{!!config('csgtcrud.pathToAssets','/')!!}js/summernote-es-ES.js"></script>
	@endif

	<script type="text/javascript">
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
