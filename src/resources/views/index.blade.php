@extends($layout)
@section('titulo')
	{!! $titulo !!}
@stop
@section('breadcrumb')
	{!! $breadcrumb !!}
@stop
@section('javascript')
 	<script>
		$(document).ready(function(){
			$.fn.dataTable.ext.errMode = function ( settings, helpPage, message ) {
				console.log(JSON.stringify(message));
			};
			var oTable = $('.tabla-catalogo').dataTable({
                ...{!! json_encode($options, JSON_PRETTY_PRINT) !!},
				...{
                    @if($stateSave)
				"stateSave"  : true,
				"stateSaveParams": function(settings, data) {
					data.columns.forEach(function(column) {
						delete column.visible;
					});
				},
				@endif
				"columnDefs": [{
                    "targets": -1,
                    "class": "text-right",
                    "data": null,
                    "sortable": false,
                    "render": function ( data, type, full, meta ) {
                        var id = data['DT_RowId'];
                        var html = '<div class="btn-group float-left">';
                        @foreach ($botonesExtra as $botonExtra)
			    		<?php
$url     = $botonExtra["url"];
$urlarr  = explode('{id}', $url);
$urlVars = '';
$parte1  = $urlarr[0];
$parte2  = (count($urlarr) == 1 ? '' : $urlarr[1]);
if ($nuevasVars != '') {
    $urlVars = (!strpos($url, '?') ? '?' : '&') . substr($nuevasVars, 1);
}

$target = $botonExtra["target"];
if ($target != '') {
    $target = 'target="' . $target . '"';
}
?>
							html += '<a class="btn btn-xs btn-{{$botonExtra["class"]}}" title="{!! $botonExtra["titulo"] !!}" href="{{$parte1}}' + id + '{{$parte2 . $urlVars}}" {{$target}} {!! $botonExtra["confirm"] ? "onclick=\"return confirm(\'".$botonExtra["confirmmessage"]."\');\"" : "" !!}><i class="{{$botonExtra["icon"]}} fa-fw"></i></a>';
						@endforeach

			    	@if($permisos['edit'])
							html += '<a class="btn btn-xs btn-primary" title="{{trans('csgtcrud::crud.editar')}}" href="/{!! Request::path() !!}/' + id + '/edit/{!!$nuevasVars!!}"><i class="fas fa-pencil-alt fa-fw"></i></a>';
						@endif;
						@if($permisos['delete'])
							html += '<div class="btn btn-xs btn-danger">\
								<form action="/{!! Request::path() !!}/' + id + '{!!$nuevasVars!!}" class="btn-delete" method="POST" onsubmit="return confirm(\'{{trans('csgtcrud::crud.seguro')}}\')">\
								<input type="hidden" name="_method" value="DELETE">\
								<input type="hidden" name="_token" value="{{csrf_token()}}">\
								<a type="submit" title="{{trans('csgtcrud::crud.eliminar')}}" onclick="$(this).closest(\'form\').submit();">\
								    <i class="fa fa-trash fa-fw"></i>\
								</a>\
								</form></div>';
						@endif;
						html += '</div>';
			      return html;
			    }
			  },
			  	@foreach ($columnas as $columna) {
			  		"targets" : {{ $loop->index }},
			  		"class" : "{!!$columna["class"]!!}",
			  		"searchable" : "{!!$columna["searchable"]!!}",

					@if(($columna["tipo"]=="date") || ($columna["tipo"]=="datetime"))
				  		"data" : null,
				  		"render" : function(data) {
                            var date = moment.utc(data[{{$loop->index}}]);
                            if (!date.isValid()) return null
                            @if($column['utc'] == true)
                               date.local()
                            @endif
                            @if($column['type'] == "date")
                                return date.format('DD-MM-YYYY')
                            @else
                                return date.format('DD-MM-YYYY HH:mm')
                            @endif
					  	}

					@elseif ($columna["tipo"]=="image")
						"data" : null,
				  		"render" : function(data) {
				  		var val = data[{{$loop->index}}];
				  		if (val==null) return null;
				  		return '<img width="{!!$columna["filewidth"]!!}" src="{!!$columna["filepath"]!!}' + val + '">';
				  	}

				  	@elseif ($columna["tipo"]=="file")
						"data" : null,
				  		"render" : function(data) {
				  		var val = data[{{$loop->index}}];
				  		if (val==null) return null;
				  		return '<a href="{!!$columna["filepath"]!!}' + val + '" target="_blank"><span class="glyphicon glyphicon-cloud-download"></span>';
				  	}
				  	@elseif ($columna["tipo"]=="securefile")
						"data" : null,
				  		"render" : function(data) {
				  		var val = data[{{$loop->index}}];
				  		if (val==null) return null;
				  		var valArray = val.split('.')
				  		var extension = valArray[valArray.length - 1]
				  		if(["jpg", "png", "gif"].indexOf(extension)) {
				  			return '<img width="{!!$columna["filewidth"]!!}" src="' + val + '">';
				  		}
				  		return '<a href="{!!$columna["filepath"]!!}' + val + '" target="_blank"><span class="glyphicon glyphicon-cloud-download"></span>';
				  	}

					@elseif ($columna["tipo"]=="numeric")
						"data" : null,
				  		"render" : function(data) {
				  		var val = data[{{$loop->index}}];
				  		if (val==null) return null;

				  		val = Number(val);
				  		return val.formatMoney({!!$columna["decimales"]!!});
				  	}

			  		@elseif($columna["tipo"]=="bool")
			  	 		"data" : null,
				  		"render" : function(data) {
				  			var val = data[{{$loop->index}}];
							if (val==null) return null;

							var text = (val==0?'<span class="badge badge-danger w-50">No</span>':'<span class="badge bg-success w-50">{{trans('csgtcrud::crud.si')}}</span>');
				  			return text;
					  	}
					@elseif ($columna["tipo"]=="url")
						"data" : null,
				  		"render" : function(data) {
				  			var val = data[{{$loop->index}}];
				  			if (val==null) return null;
				  			return '<a href="' + val + '" target="{!!$columna["target"]!!}">' + val + '</a>';
				  		}
				  	@else
				  		"render" : $.fn.dataTable.render.text()
		  			@endif
		  		},
			  	@endforeach
			  ],

			}});


			@if((!$permisos['edit'])&&(!$permisos['delete'])&&(count($botonesExtra)==0))
				oTable.fnSetColumnVis(-1,false);
			@endif

			$('.tabla-catalogo').on('init.dt', function(){
				console.log('init');
				$('.pagination').addClass('pagination-sm');
				$('.dataTables_info').addClass('small text-muted');
				@if($permisos['add'])
					$('.btn-group-agregar').html('<a type="button" class="btn btn-success" href="/{!! Request::path() . '/create/' . $nuevasVars !!}">{{trans('csgtcrud::crud.agregar')}}</a>');
				@endif
                @foreach($accionesExtra as $action)
                    $('.btn-group-agregar').append('<a type="button" class="btn btn-default" href="{!! $action['url'] !!}">{{ $action['titulo'] }}</a>');
                @endforeach
				$('.dt-buttons').addClass('btn-group-sm');
				$('div[id$=_filter] input').css('width','100%').attr('placeholder','{{trans('csgtcrud::crud.buscar')}}');
				$('.dataTables_filter label').css('width','100%');
			});

			// $('.tabla-catalogo').on('processing.dt', function(e, settings, processing){
			// 	console.log('processing');
			// 	console.log(processing);
			// 	if (processing==false)
			// 		$('#modal-procesando').modal('hide');
			// 	else
			// 		$('#modal-procesando').modal('show');
			// });

			$(oTable.parent()).removeClass('form-inline' );
		});

		Number.prototype.formatMoney = function(aDec) {
      var n = this,
      sign = n < 0 ? "-" : "",
      i = parseInt(n = Math.abs(+n || 0).toFixed(aDec)) + "",
      j = (j = i.length) > 3 ? j % 3 : 0;
        return sign + (j ? i.substr(0, j) + "," : "") + i.substr(j).replace(/(\d{3})(?=\d)/g, "$1" + ",")
          + (aDec ? "." + Math.abs(n - i).toFixed(aDec).slice(2) : "");
    };
	</script>
@stop

@section('content')
	<div class="w-100"></div>
	<div class="card">
		<div class="card-body">
			<table class="table table-striped table-sm table-hover tabla-catalogo display dt-responsive nowrap dt-responsive nowrap">
				<thead>
                    <tr>
                        @foreach ($columnas as $columna)
                            <th>{!! $columna["nombre"] !!}</th>
                            @if ($loop->last)
                                <th>&nbsp;</th>
                            @endif
                        @endforeach
                    </tr>
                </thead>
			</table>
		</div>
	</div>

	@if(isset($extraView))
		@include($extraView)
	@endif
@stop
@section('css')
<style>
    div.dataTables_processing {
        z-index: 1;
    }
  	.btn-toolbar-flex {
        display: flex;
    }
    .btn-toolbar-flex .btn-group {
        margin-left: 2px;
    }
</style>
@stop
