@extends($layout)
@section('titulo')
    {!! $titulo !!}
@stop
@section('breadcrumb')
    {!! $breadcrumb !!}
@stop
@section('javascript')
    <script type="module">
        $(document).ready(function() {
            $.fn.dataTable.ext.errMode = function(settings, helpPage, message) {
                console.log(JSON.stringify(message));
            };

            var filterColumns = @json($filterColumns);
            var $filterRows = $('#crud-filter-rows');

            function addFilterRow(filter) {
                filter = filter || {};

                var $row = $('<div class="form-row align-items-center mb-2 crud-filter-row"></div>');
                var $column = $('<select class="form-control form-control-sm crud-filter-column"></select>');
                var $value = $('<input type="text" class="form-control form-control-sm crud-filter-value">')
                    .val(filter.value || '');

                filterColumns.forEach(function(column) {
                    $('<option></option>')
                        .val(column.index)
                        .text(column.label)
                        .prop('selected', String(column.index) === String(filter.column))
                        .appendTo($column);
                });

                $row.append($('<div class="col-sm-4 mb-1 mb-sm-0"></div>').append($column));
                $row.append($('<div class="col"></div>').append($value));
                $row.append(
                    $('<div class="col-auto pl-1"></div>').append(
                        $('<button type="button" class="btn btn-sm btn-light crud-filter-add"><i class="fa fa-plus"></i></button>')
                            .attr('aria-label', @json(trans('csgtcrud::crud.agregarfiltro')))
                            .attr('title', @json(trans('csgtcrud::crud.agregarfiltro'))),
                        $('<button type="button" class="btn btn-sm btn-light ml-1 crud-filter-remove"><i class="fa fa-minus"></i></button>')
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
                processing: true,
                serverSide: true,
                searching: false,
                @if ($stateSave)
                    stateSave: true,
                    stateSaveParams: function(settings, data) {
                        data.columns.forEach(function(column) {
                            delete column.visible;
                        });
                        data.crudFilters = getFilters();
                    },
                    stateLoadParams: function(settings, data) {
                        setFilters(data.crudFilters || []);
                    },
                @endif
                @if ($orders)
                    order: [
                        @foreach ($orders as $col => $orden)
                            ["{!! $col !!}", "{!! $orden !!}"],
                        @endforeach
                    ],
                @endif
                ajax: {
                    url: "/{!! Request::path() !!}/data{{ $nuevasVars }}",
                    headers: {
                        'X-CSRF-Token': "{{ csrf_token() }}"
                    },
                    method: "POST",
                    data: function(data) {
                        data.filters = getFilters();
                    },
                    error: function(xhr, error, thrown) {
                        alert(xhr.responseJSON.message)
                    },
                },
                bLengthChange: false,
                sDom: '<"row" <"col-sm-8"> <"col-sm-4"<"btn-toolbar pull-right" B <"btn-group btn-group-sm btn-group-agregar">>>>t<"d-flex flex-nowrap justify-content-between align-items-center"<"mr-2"i><"ml-2"p>>',
                iDisplayLength: {!! $perPage !!},
                columnDefs: [{
                        targets: -1,
                        class: "text-right text-end",
                        data: null,
                        sortable: false,
                        render: function(data, type, full, meta) {
                            var id = data['DT_RowId'];
                            var html = '<div class="btn-group" role="group">';
                            @foreach ($botonesExtra as $botonExtra)
                                @php
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
                                @endphp
                                html +=
                                    '<a class="btn btn-sm {{ $botonExtra['class'] }}" title="{!! $botonExtra['titulo'] !!}" href="{{ $parte1 }}' +
                                    id +
                                    '{{ $parte2 . $urlVars }}" {{ $target }} {!! $botonExtra['confirm'] ? "onclick=\"return confirm(\'" . $botonExtra['confirmmessage'] . "\');\"" : '' !!}><i class="{{ $botonExtra['icon'] }}"></i></a>';
                            @endforeach

                            @if ($permisos['edit'])
                                html +=
                                    '<a class="btn btn-sm btn-info" title="{{ trans('csgtcrud::crud.editar') }}" href="/{!! Request::path() !!}/' +
                                    id +
                                    '/edit/{!! $nuevasVars !!}"><i class="fas fa-pencil-alt"></i></a>';
                            @endif ;
                            @if ($permisos['delete'])
                                html +=
                                    '\
                                                                                                                                                                    <form action="{!! URL::to(Request::url()) !!}/' +
                                    id +
                                    '{!! $nuevasVars !!}" class="btn-delete" method="POST">\
                                                                                                                                                                                                                        								<input type="hidden" name="_method" value="DELETE">\
                                                                                                                                                                                                                        								<input type="hidden" name="_token" value="{{ csrf_token() }}">\
                                                                                                                                                                                                                        								<button type="submit" class="btn btn-sm btn-danger ml-1" title="{{ trans('csgtcrud::crud.eliminar') }}" onclick="return confirm(\'{{ trans('csgtcrud::crud.seguro') }}\')">\
                                                                                                                                                                                                                        								<i class="fa fa-trash"></i>\
                                                                                                                                                                                                                        								</button>\
                                                                                                                                                                                                                        								</form>';
                            @endif ;
                            html += "</div>"
                            return html;
                        }
                    },
                    @foreach ($columnas as $columna)
                        {
                            targets: {{ $loop->index }},
                            class: "{!! $columna['class'] !!}",
                            searchable: "{!! $columna['searchable'] !!}",

                            @if ($columna['tipo'] == 'date' || $columna['tipo'] == 'datetime')
                                data: null,
                                render: function(data) {
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
                                data: null,
                                    render: function(data) {
                                        var val = data[{{ $loop->index }}];
                                        if (val == null || val == '') return null;
                                        return '<img width="{!! $columna['filewidth'] !!}" src="{!! $columna['filepath'] !!}' +
                                            val + '">';
                                    }
                            @elseif ($columna['tipo'] == 'file')
                                data: null,
                                    render: function(data) {
                                        var val = data[{{ $loop->index }}];
                                        if (val == null || val == '') return null;
                                        return '<a href="{!! $columna['filepath'] !!}' + val +
                                            '" target="_blank"><i class="fa fa-cloud-download-alt"></i>';
                                    }
                            @elseif ($columna['tipo'] == 'numeric')
                                data: null,
                                    render: function(data) {
                                        var val = data[{{ $loop->index }}];
                                        if (val == null || val == '') return null;

                                        val = Number(val);
                                        return val.formatMoney({!! $columna['decimales'] !!});
                                    }
                            @elseif ($columna['tipo'] == 'bool')
                                data: null,
                                    render: function(data) {
                                        var val = data[{{ $loop->index }}];
                                        if (val === null || val === '') return null;

                                        var text = (val == 0 ?
                                            '<i class="text-danger fas fa-times"></i>' :
                                            '<i class="text-success fas fa-check"></i>');
                                        return text;
                                    }
                            @elseif ($columna['tipo'] == 'url')
                                data: null,
                                    render: function(data) {
                                        var val = data[{{ $loop->index }}];
                                        if (val == null || val == '') return null;
                                        return '<a href="' + val +
                                            '" target="{!! $columna['target'] !!}">' +
                                            val + '</a>';
                                    }
                            @else
                                render: $.fn.dataTable.render.text()
                            @endif
                        },
                    @endforeach
                ],
                oLanguage: {
                    sLengthMenu: "{{ trans('csgtcrud::crud.sLengthMenu') }}",
                    sZeroRecords: "{{ trans('csgtcrud::crud.sZeroRecords') }}",
                    sInfo: "{{ trans('csgtcrud::crud.sInfo') }}",
                    sInfoEmpty: "{{ trans('csgtcrud::crud.sInfoEmpty') }}",
                    sInfoFiltered: "{{ trans('csgtcrud::crud.sInfoFiltered') }}",
                    sSearch: "",
                    sProcessing: "{{ trans('csgtcrud::crud.sProcessing') }}",
                    oPaginate: {
                        sPrevious: @json('<i class="fas fa-angle-left" aria-hidden="true" title="'.trans('csgtcrud::crud.sPrevious').'"></i><span class="sr-only">'.trans('csgtcrud::crud.sPrevious').'</span>'),
                        sNext: @json('<i class="fas fa-angle-right" aria-hidden="true" title="'.trans('csgtcrud::crud.sNext').'"></i><span class="sr-only">'.trans('csgtcrud::crud.sNext').'</span>'),
                        sFirst: @json('<i class="fas fa-angle-double-left" aria-hidden="true" title="'.trans('csgtcrud::crud.sFirst').'"></i><span class="sr-only">'.trans('csgtcrud::crud.sFirst').'</span>'),
                        sLast: @json('<i class="fas fa-angle-double-right" aria-hidden="true" title="'.trans('csgtcrud::crud.sLast').'"></i><span class="sr-only">'.trans('csgtcrud::crud.sLast').'</span>')
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
                console.log('init');
                $('.pagination').addClass('pagination-sm');
                $('.dataTables_info').addClass('small text-muted');
                @if ($permisos['add'])
                    $('.btn-group-agregar').html(
                        '<a type="button" class="btn btn-default btn-light" href="{!! URL::to(Request::url() . '/create/' . $nuevasVars) !!}">{{ trans('csgtcrud::crud.agregar') }}</a>'
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
                console.log('processing');
                console.log(processing);
                if (processing == false)
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
            return sign + (j ? i.substr(0, j) + "," : "") + i.substr(j).replace(/(\d{3})(?=\d)/g, "$1" + ",") +
                (aDec ? "." + Math.abs(n - i).toFixed(aDec).slice(2) : "");
        };
    </script>
@stop

@section('content')
    <div class="clearfix"></div>
    <div class="card">
        <div class="card-body">
            @if ($showSearch)
                <div class="mb-3">
                    <div id="crud-filter-rows"></div>
                    <div class="text-right text-end">
                        <button id="crud-filter-clear" type="button" class="btn btn-sm btn-light">
                            {{ trans('csgtcrud::crud.limpiar') }}
                        </button>
                        <button id="crud-filter-apply" type="button" class="btn btn-sm btn-primary">
                            <i class="fa fa-filter"></i> {{ trans('csgtcrud::crud.filtrar') }}
                        </button>
                    </div>
                </div>
            @endif
            <div class="{{ $responsive ? 'table-responsive' : '' }}">
                <table class="table table-sm table-striped table-hover tabla-catalogo display">
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
            </div>
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
