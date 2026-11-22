@extends($layout)
@section('titulo')
    {!! $titulo !!}
@stop
@section('breadcrumb')
    {!! $breadcrumb !!}
@stop

@section('javascript')
    <script>
        $(document).ready(function() {
            $.fn.dataTable.ext.errMode = function(settings, helpPage, message) {
                console.log(JSON.stringify(message));
            };
            var filterColumns = @json($filterColumns);
            var $filterRows = $('#crud-filter-rows');

            function addFilterRow(filter) {
                filter = filter || {};

                var $row = $('<div class="row crud-filter-row" style="margin-bottom: 10px;"></div>');
                var $column = $('<select class="form-control input-sm crud-filter-column"></select>');
                var $value = $('<input type="text" class="form-control input-sm crud-filter-value">')
                    .val(filter.value || '');

                filterColumns.forEach(function(column) {
                    $('<option></option>')
                        .val(column.index)
                        .text(column.label)
                        .prop('selected', String(column.index) === String(filter.column))
                        .appendTo($column);
                });

                $row.append($('<div class="col-sm-4"></div>').append($column));
                $row.append($('<div class="col-sm-6"></div>').append($value));
                $row.append(
                    $('<div class="col-sm-2"></div>').append(
                        $('<button type="button" class="btn btn-sm btn-default crud-filter-add"><i class="fa fa-plus"></i></button>')
                            .attr('aria-label', @json(trans('csgtcrud::crud.agregarfiltro')))
                            .attr('title', @json(trans('csgtcrud::crud.agregarfiltro'))),
                        $('<button type="button" class="btn btn-sm btn-default crud-filter-remove" style="margin-left: 5px;"><i class="fa fa-minus"></i></button>')
                            .attr('aria-label', @json(trans('csgtcrud::crud.quitarfiltro')))
                            .attr('title', @json(trans('csgtcrud::crud.quitarfiltro')))
                    )
                );

                $filterRows.append($row);
                updateRemoveButtons();
            }

            function updateRemoveButtons() {
                $filterRows.find('.crud-filter-remove').prop('disabled', $filterRows.find('.crud-filter-row').length === 1);
            }

            function getFilters() {
                return $filterRows.find('.crud-filter-row').map(function() {
                    return {
                        column: $(this).find('.crud-filter-column').val(),
                        value: $(this).find('.crud-filter-value').val()
                    };
                }).get().filter(function(filter) {
                    return filter.value.trim() !== '';
                });
            }

            function setFilters(filters) {
                $filterRows.empty();
                if (!Array.isArray(filters) || filters.length === 0) {
                    addFilterRow();
                    return;
                }

                filters.forEach(addFilterRow);
            }

            addFilterRow();

            var oTable = $('.tabla-catalogo').dataTable({
                "processing": true,
                "serverSide": true,
                searching: false,
                @if ($stateSave)
                    "stateSave": true,
                    "stateSaveParams": function(settings, data) {
                        data.columns.forEach(function(column) {
                            delete column.visible;
                        });
                        data.crudFilters = getFilters();
                    },
                    "stateLoadParams": function(settings, data) {
                        setFilters(data.crudFilters || []);
                    },
                @endif
                @if ($orders)
                    "order": [
                        @foreach ($orders as $col => $orden)
                            ["{!! $col !!}", "{!! $orden !!}"],
                        @endforeach
                    ],
                @endif
                "ajax": {
                    "url": "/{!! Request::path() !!}/data{!! $nuevasVars !!}",
                    "headers": {
                        "X-CSRF-Token": "{{ csrf_token() }}"
                    },
                    "method": "POST",
                    data: function(data) {
                        data.filters = getFilters();
                    },
                },
                "bLengthChange": false,
                "sDom": '<"row" <"col-sm-8"> <"col-sm-4"<"btn-toolbar pull-right"  B <"btn-group btn-group-sm btn-group-agregar">>>>     t<"pull-left"i><"pull-right"p>',
                "iDisplayLength": {!! $perPage !!},
                "columnDefs": [{
                        "targets": -1,
                        "class": "text-right",
                        "data": null,
                        "sortable": false,
                        "render": function(data, type, full, meta) {
                            var id = data['DT_RowId'];
                            var html = '<div class="btn-toolbar btn-toolbar-flex pull-left">';
                            @foreach ($botonesExtra as $botonExtra)
                                <?php
                                $url = $botonExtra['url'];
                                $urlarr = explode('{id}', $url);
                                $urlVars = '';
                                $parte1 = $urlarr[0];
                                $parte2 = count($urlarr) == 1 ? '' : $urlarr[1];
                                if ($nuevasVars != '') {
                                    $urlVars = (!strpos($url, '?') ? '?' : '&') . substr($nuevasVars, 1);
                                }
                                $target = $botonExtra['target'];
                                if ($target != '') {
                                    $target = 'target="' . $target . '"';
                                }
                                ?>
                                html +=
                                    '<div class="btn-group btn-group-xs"><a class="btn btn-xs btn-{{ $botonExtra['class'] }}" title="{!! $botonExtra['titulo'] !!}" href="{{ $parte1 }}' +
                                    id +
                                    '{{ $parte2 . $urlVars }}" {{ $target }} {!! $botonExtra['confirm'] ? "onclick=\"return confirm(\'" . $botonExtra['confirmmessage'] . "\');\"" : '' !!}><span class="{{ $botonExtra['icon'] }}"></span></a></div>';
                            @endforeach

                            @if ($permisos['edit'])
                                html +=
                                    '<div class="btn-group btn-group-xs"><a class="btn btn-xs btn-primary" title="{{ trans('csgtcrud::crud.editar') }}" href="/{!! Request::path() !!}/' +
                                    id +
                                    '/edit/{!! $nuevasVars !!}"><span class="fa fa-pencil"></span></a></div>';
                            @endif ;
                            @if ($permisos['delete'])
                                html += '<div class="btn-group btn-group-xs">\
                                                								<form action="/{!! Request::path() !!}/' + id + '{!! $nuevasVars !!}" class="btn-delete" method="POST">\
                                                								<input type="hidden" name="_method" value="DELETE">\
                                                								<input type="hidden" name="_token" value="{{ csrf_token() }}">\
                                                								<button type="submit" class="btn btn-xs btn-danger" title="{{ trans('csgtcrud::crud.eliminar') }}" onclick="return confirm(\'{{ trans('csgtcrud::crud.seguro') }}\')">\
                                                								<i class="fa fa-trash"></i>\
                                                								</button>\
                                                								</form></div>';
                            @endif ;
                            html += '</div>';
                            return html;
                        }
                    },
                    @foreach ($columnas as $columna)
                        {
                            "targets": {{ $loop->index }},
                            "class": "{!! $columna['class'] !!}",
                            "searchable": "{!! $columna['searchable'] !!}",

                            @if ($columna['tipo'] == 'date' || $columna['tipo'] == 'datetime')
                                "data": null,
                                "render": function(data) {
                                    var date = moment.utc(data[{{ $loop->index }}]);
                                    if (!date.isValid()) return null

                                    @if ($columna['utc'] == true)
                                        date.local()
                                    @endif

                                    @if ($columna['tipo'] == 'date')
                                        return date.format('DD-MM-YYYY')
                                    @else
                                        return date.format('DD-MM-YYYY HH:mm')
                                    @endif
                                }
                            @elseif ($columna['tipo'] == 'image')
                                "data": null,
                                "render": function(data) {
                                    var val = data[{{ $loop->index }}];
                                    if (val == null) return null;
                                    return '<img width="{!! $columna['filewidth'] !!}" src="{!! $columna['filepath'] !!}' +
                                        val + '">';
                                }
                            @elseif ($columna['tipo'] == 'file')
                                "data": null,
                                "render": function(data) {
                                    var val = data[{{ $loop->index }}];
                                    if (val == null) return null;
                                    return '<a href="{!! $columna['filepath'] !!}' + val +
                                        '" target="_blank"><span class="glyphicon glyphicon-cloud-download"></span>';
                                }
                            @elseif ($columna['tipo'] == 'securefile')
                                "data": null,
                                "render": function(data) {
                                    var val = data[{{ $loop->index }}];
                                    if (val == null) return null;
                                    var valArray = val.split('.')
                                    var extension = valArray[valArray.length - 1]
                                    if (["jpg", "png", "gif"].indexOf(extension)) {
                                        return '<img width="{!! $columna['filewidth'] !!}" src="' + val +
                                            '">';
                                    }
                                    return '<a href="{!! $columna['filepath'] !!}' + val +
                                        '" target="_blank"><span class="glyphicon glyphicon-cloud-download"></span>';
                                }
                            @elseif ($columna['tipo'] == 'numeric')
                                "data": null,
                                "render": function(data) {
                                    var val = data[{{ $loop->index }}];
                                    if (val == null) return null;

                                    val = Number(val);
                                    return val.formatMoney({!! $columna['decimales'] !!});
                                }
                            @elseif ($columna['tipo'] == 'bool')
                                "data": null,
                                "render": function(data) {
                                    var val = data[{{ $loop->index }}];
                                    if (val == null) return null;

                                    var text = (val == 0 ?
                                        '<span class="label label-default" style="display:block; width: 40px; margin: auto;">No</span>' :
                                        '<span class="label label-success" style="display:block; width: 40px; margin:auto;">{{ trans('csgtcrud::crud.si') }}</span>'
                                    );
                                    return text;
                                }
                            @elseif ($columna['tipo'] == 'url')
                                "data": null,
                                "render": function(data) {
                                    var val = data[{{ $loop->index }}];
                                    if (val == null) return null;
                                    return '<a href="' + val + '" target="{!! $columna['target'] !!}">' +
                                        val + '</a>';
                                }
                            @else
                                "render": $.fn.dataTable.render.text()
                            @endif
                        },
                    @endforeach
                ],

                "oLanguage": {
                    "sLengthMenu": "{{ trans('csgtcrud::crud.sLengthMenu') }}",
                    "sZeroRecords": "{{ trans('csgtcrud::crud.sZeroRecords') }}",
                    "sInfo": "{{ trans('csgtcrud::crud.sInfo') }}",
                    "sInfoEmpty": "{{ trans('csgtcrud::crud.sInfoEmpty') }}",
                    "sInfoFiltered": "{{ trans('csgtcrud::crud.sInfoFiltered') }}",
                    "sSearch": "",
                    "sProcessing": "{{ trans('csgtcrud::crud.sProcessing') }}",
                    "oPaginate": {
                        "sPrevious": "{{ trans('csgtcrud::crud.sPrevious') }}",
                        "sNext": "{{ trans('csgtcrud::crud.sNext') }}",
                        "sFirst": "{{ trans('csgtcrud::crud.sFirst') }}",
                        "sLast": "{{ trans('csgtcrud::crud.sLast') }}"
                    }
                },
                @if ($showExport)
                    buttons: [
                        'copy', 'excel', 'pdf'
                    ]
                @endif

            });
            @if (!$permisos['edit'] && !$permisos['delete'] && count($botonesExtra) == 0)
                oTable.fnSetColumnVis(-1, false);
            @endif ;

            $('.tabla-catalogo').on('init.dt', function() {
                $('.pagination').addClass('pagination-sm');
                $('.dataTables_info').addClass('small text-muted');
                @if ($permisos['add'])
                    $('.btn-group-agregar').html(
                        '<a type="button" class="btn btn-success" href="/{!! Request::path() . '/create/' . $nuevasVars !!}">{{ trans('csgtcrud::crud.agregar') }}</a>'
                    );
                @endif
                @foreach ($accionesExtra as $action)
                    $('.btn-group-agregar').append(
                        '<a type="button" class="btn btn-default" href="{!! $action['url'] !!}">{{ $action['titulo'] }}</a>'
                    );
                @endforeach
                $('.dt-buttons').addClass('btn-group-sm');
            });

            $filterRows.on('click', '.crud-filter-add', function() {
                addFilterRow();
                $filterRows.find('.crud-filter-value').last().trigger('focus');
            });

            $filterRows.on('click', '.crud-filter-remove', function() {
                $(this).closest('.crud-filter-row').remove();
                updateRemoveButtons();
                oTable.api().draw();
            });

            $filterRows.on('keydown', '.crud-filter-value', function(event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    oTable.api().draw();
                }
            });

            $('#crud-filter-apply').on('click', function() {
                oTable.api().draw();
            });

            $('#crud-filter-clear').on('click', function() {
                setFilters([]);
                oTable.api().draw();
            });

            $('.tabla-catalogo').on('processing.dt', function(e, settings, processing) {
                if (processing == false)
                    $('#modal-procesando').modal('hide');
                else
                    $('#modal-procesando').modal('show');
            });

            $(oTable.parent()).removeClass('form-inline');
        });

        Number.prototype.formatMoney = function(aDec) {
            var n = this,
                sign = n < 0 ? "-" : "",
                i = parseInt(n = Math.abs(+n || 0).toFixed(aDec)) + "",
                j = (j = i.length) > 3 ? j % 3 : 0;
            return sign + (j ? i.substr(0, j) + "," : "") + i.substr(j).replace(/(\d{3})(?=\d)/g, "$1" + ",") +
                (aDec ? "." + Math.abs(n - i).toFixed(aDec).slice(2) : "");
        };
    </script>
@endsection
@section('css')
    <style>
        .btn-toolbar-flex {
            display: flex;
        }

        .btn-toolbar-flex .btn-group {
            margin-left: 2px;
        }
    </style>
@endsection

@section('content')
    <div class="clearfix"></div>
    <div class="box">
        <div class="box-body">
            @if ($showSearch)
                <div style="margin-bottom: 15px;">
                    <div id="crud-filter-rows"></div>
                    <div class="text-right">
                        <button id="crud-filter-clear" type="button" class="btn btn-sm btn-default">
                            {{ trans('csgtcrud::crud.limpiar') }}
                        </button>
                        <button id="crud-filter-apply" type="button" class="btn btn-sm btn-primary">
                            <i class="fa fa-filter"></i> {{ trans('csgtcrud::crud.filtrar') }}
                        </button>
                    </div>
                </div>
            @endif
            @if ($responsive)
                <div class="table-responsive">
            @endif
            <table class="table table-bordered table-condensed table-hover tabla-catalogo display">
                <thead>
                    <tr>
                        @foreach ($columnas as $columna)
                            <th>{!! $columna['nombre'] !!}</th>
                            @if ($loop->last)
                                <th>&nbsp;</th>
                            @endif
                        @endforeach

                    </tr>
                </thead>
            </table>
            @if ($responsive)
        </div>
        @endif
    </div>
    </div>
    <div class="modal" id="modal-procesando">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-body text-center">
                    <h4>{{ trans('csgtcrud::crud.sProcessing') }}...</h4>
                </div>
            </div>
        </div>
    </div>

    @if (isset($extraView))
        @include($extraView)
    @endif
@stop
