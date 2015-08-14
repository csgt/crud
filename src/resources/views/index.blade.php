@extends($template)

@section('content')
	
  @if($showExport)
  	<script src="{!!config('csgtcrud.pathToAssets','/') . 'js/datatables.min.js'!!}"></script>
  @endif
  <?php
  	foreach ($botonesExtra as $botonExtra) {
  		$fontawesome = false;
  		if( strpos($botonExtra['icon'], 'fa-')) {
  			$fontawesome = true;
  		} 
  	}
  ?>
  @if($fontawesome)
  	<link type="text/css" rel="stylesheet" href="{!!config('csgtcrud.pathToAssets','/') . 'css/font-awesome.min.css'!!}">
  @endif
 	<script>
		$(document).ready(function(){
			var oTable = $('.tabla-catalogo').dataTable({
				"processing" : true,
				"serverSide" : true,
				@if($orders)
					"order": [
						@foreach ($orders as $col=>$orden)
						[ "{!!$col!!}", "{!!$orden!!}" ],
						@endforeach
					],
				@endif
				"ajax" : "/{!!Request::path()!!}/0{!!$nuevasVars!!}",
				"bLengthChange": false,
				"sDom": '<"row" @if($showSearch)<"col-sm-8 pull-left"f>@endif <"col-sm-4"<"btn-toolbar pull-right"  B <"btn-group btn-group-sm btn-group-agregar">>>>     t<"pull-left"i><"pull-right"p>',
				"iDisplayLength": {!!$perPage!!},
				"columnDefs": [{
			    "targets": -1,
			    "class": "text-right",
			    "data": null,
			    "sortable": false,
			    "render": function ( data, type, full, meta ) {
			    	var col = data.length-1;
			    	var id = data[col];	 
			    	var html = '';
			    	@foreach ($botonesExtra as $botonExtra)
			    		<?php 
			    			$url = $botonExtra["url"];
			    			$urlarr = explode('{id}', $url);
			    			$urlVars = '';
			    			$parte1 = $urlarr[0];
			    			$parte2 = (count($urlarr)==1?'':$urlarr[1]);
			    			if ($nuevasVars!='') {
			    				$urlVars = (strpos($url, '?')===false?'?':'&') . substr($nuevasVars,1);
			    			}
			    			$target = $botonExtra["target"];
			    			if ($target<>'') $target='target="' . $target . '"';
			    		?>
							html += '<a class="btn btn-xs btn-{!!$botonExtra["class"]!!}" title="{!!$botonExtra["titulo"]!!}" href="{!!$parte1!!}' + id + '{!!$parte2 . $urlVars!!}" {!!$target!!}><span class="{!!$botonExtra["icon"]!!}"></span></a>';
						@endforeach
			    	@if($permisos['edit'])   	
							html += '<a class="btn btn-xs btn-primary" title="Editar" href="{!! URL::to(Request::url())!!}/' + id + '/edit/{!!$nuevasVars!!}"><span class="glyphicon glyphicon-pencil"></span></a>';
						@endif;
						@if($permisos['delete'])
							html += '<form action="{!! URL::to(Request::url())!!}/' + id + '{!!$nuevasVars!!}" class="btn-delete" method="POST">\
								<input type="hidden" name="_method" value="DELETE">\
								<input type="hidden" name="_token" value="{{csrf_token()}}">\
								<button type="submit" class="btn btn-xs btn-danger" title="Borrar" onclick="return confirm(\'¿Está seguro que desea eliminar este registro?\')">\
								<i class="glyphicon glyphicon-trash"></i>\
								</button>\
								</form>';
						@endif;
			      return html;
			    }
			  }, 
			  <?php $i=0; ?>
			  @foreach ($columnas as $columna) {
			  		"targets" : {!!$i!!},
			  		"class" : "{!!$columna["class"]!!}",
			  		"searchable" : "{!!$columna["searchable"]!!}",

				  @if(($columna["tipo"]=="date") || ($columna["tipo"]=="datetime")) 
				  	"data" : null,
				  	"render" : function(data) {
				  		var fecha = data[{!!$i!!}];
				  		if (fecha==null) return null;
				  		var arrhf = fecha.split(" "); 
				  		var arrf  = arrhf[0].split("-");
				  		var hora  = '';
				  		if (arrhf.length==2) {hora = ' ' + arrhf[1].substring(0,5);}
				  		return arrf[2] + '-' + arrf[1] + '-' + arrf[0] + hora;
				  	}

					@elseif ($columna["tipo"]=="image") 
						"data" : null,
				  	"render" : function(data) {
				  		var val = data[{!!$i!!}];
				  		if (val==null) return null;
				  		return '<img width="{!!$columna["filewidth"]!!}" src="{!!$columna["filepath"]!!}' + val + '">';
				  	}

				  @elseif ($columna["tipo"]=="file") 
						"data" : null,
				  	"render" : function(data) {
				  		var val = data[{!!$i!!}];
				  		if (val==null) return null;
				  		return '<a href="{!!$columna["filepath"]!!}' + val + '" target="_blank"><span class="glyphicon glyphicon-cloud-download"></span>';
				  	}

					@elseif ($columna["tipo"]=="numeric") 
						"data" : null,
				  	"render" : function(data) {
				  		var val = data[{!!$i!!}];
				  		if (val==null) return null;

				  		val = Number(val);
				  		return val.formatMoney({!!$columna["decimales"]!!});
				  	}

			  	@elseif($columna["tipo"]=="bool") 
			  	 	"data" : null,
				  	"render" : function(data) {
				  		var val = data[{!!$i!!}];
							if (val==null) return null;

							var text = (val==0?'<span class="label label-default" style="display:block; width: 40px; margin: auto;">No</span>':'<span class="label label-success" style="display:block; width: 40px; margin:auto;">Si</span>');
				  		return text;
					  }
					@elseif ($columna["tipo"]=="url") 
						"data" : null,
				  	"render" : function(data) {
				  		var val = data[{!!$i!!}];
				  		if (val==null) return null;
				  		return '<a href="' + val + '" target="{!!$columna["target"]!!}">' + val + '</a>';
				  	}
		  		@endif
		  		},
			  	<?php $i++; ?>
			  @endforeach
			  ],

				"oLanguage": {
     			"sLengthMenu": "Mostrar _MENU_ resultados por p&aacute;gina",
          "sZeroRecords": "No se encontraron registros",
          "sInfo": "Mostrando _START_ a _END_ de _TOTAL_ resultados",
          "sInfoEmpty": "Mostrando 0 a 0 de 0 resultados",
          "sInfoFiltered": "(filtrado de _MAX_ resultados totales)",
					"sSearch":"",
					"sProcessing":"Procesando",
					"oPaginate": {
						"sPrevious":"Anterior",
						"sNext":"Siguiente",
						"sFirst":"Primera",
						"sLast":"Ultima"
					}
				},
				@if($showExport)
      		buttons: [
        		'copy', 'excel', 'pdf'
    			]
	    @endif

			});
			@if((!$permisos['edit'])&&(!$permisos['delete'])&&(count($botonesExtra)==0))   	   
				oTable.fnSetColumnVis(-1,false);
			@endif;

			$('.tabla-catalogo').on('init.dt', function(){
				console.log('init');
				$('.pagination').addClass('pagination-sm');
				$('.dataTables_info').addClass('small text-muted');
				@if($permisos['add'])
					$('.btn-group-agregar').html('<button type="button" class="btn btn-success">Agregar</button>');
				@endif
				$('.dt-buttons').addClass('btn-group-sm');
				$('div[id$=_filter] input').css('width','100%').attr('placeholder','Buscar');
				$('.dataTables_filter label').css('width','100%');
			});

			$('.tabla-catalogo').on('processing.dt', function(e, settings, processing){
				console.log('processing');
				console.log(processing);
				if (processing==false)
					$('#modal-procesando').modal('hide');
				else
					$('#modal-procesando').modal('show');
			});

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
	<style>
		.btn { margin-left: 2px; margin-right: 2px; margin-bottom: 1px; margin-top: 1px;}
		.hr-crud {margin-top:0; margin-bottom: 4px;}
		.pagination { margin: 0;}
		.tabla-catalogo { margin-bottom: 5px;}
	</style>
	{!! $titulo !!}
	<div class="clearfix"></div>
	<hr class="hr-crud">
	@if(Session::get('message'))
		<div class="alert alert-{!! Session::get('type') !!} alert-dismissable .mrgn-top">
			<button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
			{!! Session::get('message') !!}
		</div>
	@endif
	<table class="table table-striped table-bordered table-condensed table-hover tabla-catalogo display">
		<thead>
      <tr>
      	@foreach ($columnas as $columna) 
        	<th>{!!$columna["nombre"]!!}</th>
        @endforeach
        <th>&nbsp;</th>
      </tr>
    </thead>
	</table>
	<div class="modal" id="modal-procesando">
	  <div class="modal-dialog modal-sm">
	    <div class="modal-content">
	      <div class="modal-body text-center">
	        <h4>Procesando...</h4>
	      </div>
	    </div><!-- /.modal-content -->
	  </div><!-- /.modal-dialog -->
	</div><!-- /.modal -->

	@if(isset($extraView))
		@include($extraView)
	@endif
@stop