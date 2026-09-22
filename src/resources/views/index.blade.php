@extends($layout)
@section('titulo')
    {!! $titulo !!}
@stop
@section('breadcrumb')
    {!! $breadcrumb !!}
@stop
@section('javascript')
    <script type="module">
        // Estas vistas no traen sus propias dependencias: las carga la aplicacion.
        // Cuando faltaba alguna, la pantalla quedaba en blanco sin decir por que.
        window.csgtCrudRequire = window.csgtCrudRequire || function(name, present, version, min, max) {
            if (!present) {
                var message = 'csgt/crud: falta la libreria ' + name + ', que esta vista necesita.';
                if (document.body) {
                    var banner = document.createElement('div');
                    banner.setAttribute('style',
                        'background:#b00020;color:#fff;padding:12px;font-family:sans-serif;font-size:14px');
                    banner.textContent = message;
                    document.body.insertBefore(banner, document.body.firstChild);
                }
                throw new Error(message);
            }
            if (!version) {
                return;
            }
            var cmp = function(a, b) {
                var x = String(a).split('.'),
                    y = String(b).split('.');
                for (var i = 0; i < 3; i++) {
                    var d = (parseInt(x[i], 10) || 0) - (parseInt(y[i], 10) || 0);
                    if (d) {
                        return d < 0 ? -1 : 1;
                    }
                }
                return 0;
            };
            // Fuera del rango probado avisa, pero no corta: ahi la libreria esta y
            // la vista probablemente funcione.
            if (min && cmp(version, min) < 0) {
                console.warn('csgt/crud: ' + name + ' ' + version + ' es anterior a la minima probada (' + min + ').');
            }
            if (max && cmp(version, max) >= 0) {
                console.warn('csgt/crud: ' + name + ' ' + version + ' es igual o posterior a ' + max + ', la primera no probada.');
            }
        };
        csgtCrudRequire('jQuery', typeof window.jQuery !== 'undefined',
            window.jQuery && window.jQuery.fn && window.jQuery.fn.jquery, '1.7', '');
        csgtCrudRequire('DataTables', !!(window.jQuery && jQuery.fn && jQuery.fn.dataTable),
            window.jQuery && jQuery.fn && jQuery.fn.dataTable && jQuery.fn.dataTable.version, '1.10', '2.0');
        csgtCrudRequire('moment', typeof window.moment !== 'undefined',
            window.moment && window.moment.version, '2.0', '');
        $(document).ready(function() {
            $.fn.dataTable.ext.errMode = function(settings, helpPage, message) {
                console.log(JSON.stringify(message));
            };

            var filterColumns = @json($filterColumns);
            var $filterRows = $('#crud-filter-rows');

            // Una columna con lista cerrada (enum, bool) se filtra eligiendo de un
            // desplegable en lugar de escribiendo el valor exacto. El resto sigue
            // siendo texto libre, o un selector de fecha cuando corresponde.
            function buildValueControl(column, value) {
                if (column && column.options && column.options.length) {
                    var $select = $('<select class="form-control form-control-sm crud-filter-value"></select>');
                    $('<option></option>')
                        .val('')
                        .text(@json(trans('csgtcrud::crud.todos')))
                        .appendTo($select);
                    column.options.forEach(function(option) {
                        $('<option></option>')
                            .val(option.value)
                            .text(option.label)
                            .prop('selected', String(option.value) === String(value))
                            .appendTo($select);
                    });

                    return $select;
                }

                return $('<input class="form-control form-control-sm crud-filter-value">')
                    .attr('type', column && column.type === 'date' ? 'date' : 'text')
                    .val(value);
            }

            function addFilterRow(filter) {
                filter = filter || {};

                var $row = $('<div class="form-row d-flex flex-nowrap align-items-center mb-2 crud-filter-row"></div>');
                var $column = $('<select class="form-control form-select form-select-sm crud-filter-column"></select>');

                filterColumns.forEach(function(column) {
                    $('<option></option>')
                        .val(column.index)
                        .text(column.label)
                        .prop('selected', String(column.index) === String(filter.column))
                        .appendTo($column);
                });

                var selectedColumn = filterColumns.find(function(column) {
                    return String(column.index) === String($column.val());
                });
                var $value = buildValueControl(selectedColumn, filter.value || '');

                $row.append($('<div class="col-4 mb-1 mb-sm-0"></div>').append($column));
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

            $filterRows.on('change', '.crud-filter-column', function() {
                var selectedColumn = filterColumns.find(function(column) {
                    return String(column.index) === String($(this).val());
                }.bind(this));
                var $value = $(this).closest('.crud-filter-row').find('.crud-filter-value');

                $value.replaceWith(buildValueControl(selectedColumn, $value.val()));
            });

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

            var oTable = $('#{!! $tableId !!}').dataTable({
                processing: true,
                serverSide: true,
                searching: false,
                @if ($stateSave)
                    stateSave: true,
                    stateDuration: {!! (int) $stateDuration !!},
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

            $('#{!! $tableId !!}').on('init.dt', function() {
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

            $('#{!! $tableId !!}').on('processing.dt', function(e, settings, processing) {
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
        // Lo ultimo del bloque: si algo de arriba corto, la bandera no se setea
        // y el backstop de la seccion de contenido avisa.
        window.csgtCrudBooted = true;
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
                <table id="{{ $tableId }}" class="table table-sm table-striped table-hover tabla-catalogo display">
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
    {{-- Este bloque vive en la seccion de contenido, que siempre se renderiza
         porque aqui esta la tabla. Si el layout de la aplicacion no rinde
         @@yield('javascript'), el bloque de scripts nunca corre y la vista queda
         muerta sin decir por que: el guard de dependencias esta alla adentro y
         tampoco llega a ejecutarse. --}}
    <script>
        window.addEventListener('load', function() {
            if (window.csgtCrudBooted) {
                return;
            }
            var message = typeof window.jQuery === 'undefined' ?
                'csgt/crud: falta la libreria jQuery, que esta vista necesita.' :
                'csgt/crud: el bloque de scripts de esta vista no llego a ejecutarse. Revise que el layout rinda @@yield(\'javascript\').';
            var banner = document.createElement('div');
            banner.setAttribute('style',
                'background:#b00020;color:#fff;padding:12px;font-family:sans-serif;font-size:14px');
            banner.textContent = message;
            document.body.insertBefore(banner, document.body.firstChild);
            console.error(message);
        });
    </script>
@stop
