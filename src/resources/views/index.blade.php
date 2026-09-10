@extends($layout)
@section('title')
    {!! $title !!}
@stop
@section('breadcrumb')
    {!! $breadcrumb !!}
@stop
@section('javascript')
    <script type="module">
        $(document).ready(function() {
            $.fn.dataTable.defaults.stateDuration = 0;
            $.fn.dataTable.ext.errMode = function(settings, helpPage, message) {
                console.log(JSON.stringify(message));
            };
            var filterColumns = @json($filterColumns);
            var $filterRows = $('#crud-filter-rows');
            var filterRowTemplate = $filterRows.html();

            function addFilterRow(filter) {
                filter = filter || {};

                var $row = $(filterRowTemplate);
                var $column = $row.find('.crud-filter-column');
                if (filter.column !== undefined) {
                    $column.val(String(filter.column));
                }

                var selectedColumn = filterColumns.find(function(column) {
                    return String(column.index) === String($column.val());
                });
                var $value = $row.find('.crud-filter-value')
                    .attr('type', selectedColumn && selectedColumn.type === 'date' ? 'date' : 'text')
                    .val(filter.value || '');

                $filterRows.append($row);
                updateRemoveButtons();
            }

            $filterRows.on('change', '.crud-filter-column', function() {
                var selectedColumn = filterColumns.find(function(column) {
                    return String(column.index) === String($(this).val());
                }.bind(this));
                var $value = $(this).closest('.crud-filter-row').find('.crud-filter-value');
                var value = $value.val();

                $value.attr('type', selectedColumn && selectedColumn.type === 'date' ? 'date' : 'text').val(value);
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

            if ($filterRows.find('.crud-filter-row').length === 0) {
                addFilterRow();
            } else {
                $filterRows.find('.crud-filter-column').trigger('change');
                updateRemoveButtons();
            }

            var oTable = $('.dataTable').dataTable({
                processing: true,
                serverSide: true,
                searching: false,
                stateSave: true,
                stateSaveParams: function(settings, data) {
                    data.crudFilters = getFilters();
                },
                stateLoadParams: function(settings, data) {
                    setFilters(data.crudFilters || []);
                },

                @if ($orders)
                    order: [
                        @foreach ($orders as $col => $orden)
                            ["{!! $col !!}", "{!! $orden !!}"],
                        @endforeach
                    ],
                @endif
                ajax: {
                    url: "/{!! Request::path() !!}/data{{ $queryParameters }}",
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
                layout: {
                    topEnd: function() {
                        let toolbar = document.createElement('div');
                        var html =
                            '<div class="btn-group btn-group-sm" role="group" aria-label="Actions">';
                        @if ($permisos['create'])
                            html +=
                                '<a type="button" class="btn btn-sm btn-dark" href = "/{!! Request::path() . '/create/' . $queryParameters !!}"> {{ trans('csgtcrud::crud.agregar') }} </a>';
                        @endif
                        @foreach ($extraActions as $action)
                            html +=
                                '<a type="button" class="btn btn-sm btn-dark" href="{!! $action['url'] !!}"> {{ $action['title'] }} </a>';
                        @endforeach
                        html += '</div>';

                        toolbar.innerHTML = html;
                        return toolbar;
                    },

                    topStart: null,
                    bottom: {
                        div: {
                            className: 'mt-2'
                        }
                    },
                    bottomStart: 'info',
                    bottomEnd: 'paging'
                },
                iDisplayLength: {!! $perPage !!},
                columnDefs: [{
                        targets: {{ count($columns) }},
                        className: "text-right text-end",
                        data: null,
                        orderable: false,
                        render: function(data, type, full, meta) {
                            var id = data['DT_RowId'];
                            var html = '<div class="btn-group btn-group-sm">';
                            @foreach ($extraButtons as $extraButton)
                                @php
                                    $url = $extraButton['url'];
                                    $urlarr = explode('{id}', $url);
                                    $urlVars = '';
                                    $parte1 = $urlarr[0];
                                    $parte2 = count($urlarr) == 1 ? '' : $urlarr[1];
                                    if ($queryParameters != '') {
                                        $urlVars = (!strpos($url, '?') ? '?' : '&') . substr($queryParameters, 1);
                                    }
                                    $target = $extraButton['target'];
                                    if ($target != '') {
                                        $target = 'target="' . $target . '"';
                                    }
                                @endphp
                                html +=
                                    '<a class="mr-1 btn btn-sm  {{ $extraButton['class'] }}" title="{!! $extraButton['title'] !!}" href="{{ $parte1 }}' +
                                    id +
                                    '{{ $parte2 . $urlVars }}" {{ $target }} {!! $extraButton['confirm'] ? "onclick=\"return confirm(\'" . $extraButton['confirmmessage'] . "\');\"" : '' !!}><i class="{{ $extraButton['icon'] }}"></i></a>';
                            @endforeach

                            @if ($permisos['update'])
                                html +=
                                    '<a class="btn btn-sm  btn-info" title="{{ trans('csgtcrud::crud.editar') }}" href="/{!! Request::path() !!}/' +
                                    id +
                                    '/edit/{!! $queryParameters !!}"><i class="fa fa-pencil-alt"></i></a>';
                            @endif ;
                            @if ($permisos['destroy'])
                                html +=
                                    '\
                                                                                                                                                                                                                                                                            <form action="/{!! Request::path() !!}/' +
                                    id +
                                    '{!! $queryParameters !!}" method="POST">\
                                                                                                                                                                                                                                                                            <input type="hidden" name="_method" value="DELETE">\
                                                                                                                                                                                                                                                                            <input type="hidden" name="_token" value="{{ csrf_token() }}">\
                                                                                                                                                                                                                                                                            <button type="submit" class="btn btn-sm  btn-danger ml-1" title="{{ trans('csgtcrud::crud.eliminar') }}" onclick="return confirm(\'{{ trans('csgtcrud::crud.seguro') }}\')">\
                                                                                                                                                                                                                                                                            <i class="fa fa-trash"></i>\
                                                                                                                                                                                                                                                                            </button>\
                                                                                                                                                                                                                                                                            </form>';
                            @endif ;
                            html += '</div>';
                            return html;
                        }
                    },
                    @foreach ($columns as $column)
                        {
                            targets: {{ $loop->index }},
                            className: "{!! $column['class'] !!}",
                            searchable: "{!! $column['searchable'] !!}",
                            @if ($column['type'] == 'string')
                                type: 'string',
                            @endif

                            @if ($column['type'] == 'date' || $column['type'] == 'datetime' || $column['type'] == 'time')
                                data: null,
                                render: function(data) {
                                    let raw = data[{{ $loop->index }}];
                                    if (raw == null || raw === "") return null;
                                    raw = raw.replace(/Z$/, "");
                                    @if ($column['utc'] == false)
                                        let date = new Date(raw + "Z");
                                    @else
                                        let date = new Date(raw);
                                    @endif


                                    if (isNaN(date.getTime())) return null;

                                    function formatDate(d) {
                                        const dd = String(d.getDate()).padStart(2, "0");
                                        const mm = String(d.getMonth() + 1).padStart(2, "0");
                                        const yyyy = d.getFullYear();
                                        return `${dd}-${mm}-${yyyy}`;
                                    }

                                    function formatDateTime(d) {
                                        const ddmmyyyy = formatDate(d);
                                        const hh = String(d.getHours()).padStart(2, "0");
                                        const min = String(d.getMinutes()).padStart(2, "0");
                                        return `${ddmmyyyy} ${hh}:${min}`;
                                    }

                                    @if ($column['type'] == 'date')
                                        return formatDate(date);
                                    @else
                                        return formatDateTime(date);
                                    @endif
                                }
                            @elseif ($column['type'] == 'image')
                                data: null,
                                    render: function(data) {
                                        var val = data[{{ $loop->index }}];
                                        if (val == null || val == '') return null;
                                        return '<img width="{!! $column['filewidth'] !!}" src="{!! $column['filepath'] !!}' +
                                            val + '">';
                                    }
                            @elseif ($column['type'] == 'file')
                                data: null,
                                    render: function(data) {
                                        var val = data[{{ $loop->index }}];
                                        if (val == null || val == '') return null;
                                        return '<a href="{!! $column['filepath'] !!}' + val +
                                            '" target="_blank"><span class="fa fa-cloud-download-alt"></span>';
                                    }
                            @elseif ($column['type'] == 'securefile')
                                data: null,
                                    render: function(data) {
                                        var val = data[{{ $loop->index }}];
                                        if (val == null || val == '') return null;
                                        var valArray = val.split('.')
                                        var extension = valArray[valArray.length - 1]
                                        if (["jpg", "png", "gif"].indexOf(extension)) {
                                            return '<img width="{!! $column['filewidth'] !!}" src="' +
                                                val +
                                                '">';
                                        }
                                        return '<a href="{!! $column['filepath'] !!}' + val +
                                            '" target="_blank"><span class="fa fa-cloud-download-alt"></span>';
                                    }
                            @elseif ($column['type'] == 'numeric')
                                data: null,
                                    render: function(data) {
                                        var val = data[{{ $loop->index }}];
                                        if (val == null) return null;

                                        val = Number(val);
                                        return val.formatMoney({!! $column['decimals'] !!});
                                    }
                            @elseif ($column['type'] == 'bool')
                                data: null,
                                    render: function(data) {
                                        var val = data[{{ $loop->index }}];
                                        if (val == null) return null;

                                        var text = (val == 0 ?
                                            '<i class="text-danger fa fa-times"></i>' :
                                            '<i class="text-success fa fa-check"></i>');
                                        return text;
                                    }
                            @elseif ($column['type'] == 'url')
                                data: null,
                                    render: function(data) {
                                        var val = data[{{ $loop->index }}];
                                        if (val == null) return null;
                                        return '<a href="' + val +
                                            '" target="{!! $column['target'] !!}">' + val + '</a>';
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
                        sPrevious: "{{ trans('csgtcrud::crud.sPrevious') }}",
                        sNext: "{{ trans('csgtcrud::crud.sNext') }}",
                        sFirst: "{{ trans('csgtcrud::crud.sFirst') }}",
                        sLast: "{{ trans('csgtcrud::crud.sLast') }}"
                    }
                },
                @if ($showExport)
                    buttons: [
                        'copy', 'excel', 'pdf'
                    ]
                @endif

            });
            @if (!$permisos['update'] && !$permisos['destroy'] && count($extraButtons) == 0)
                oTable.fnSetColumnVis(-1, false);
            @endif ;

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
    <div class="card">
        <div class="card-body">
            @if ($showSearch)
                <div class="row flex-nowrap align-items-end mb-3">
                    <div id="crud-filter-rows" class="col">
                        <div class="row align-items-center mb-2 crud-filter-row">
                            <div class="col-sm-4 mb-1 mb-sm-0">
                                <select class="form-control form-select form-control-sm form-select-sm crud-filter-column">
                                    @foreach ($filterColumns as $filterColumn)
                                        <option value="{{ $filterColumn['index'] }}">{{ $filterColumn['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col">
                                <input type="text" class="form-control form-control-sm crud-filter-value">
                            </div>
                            <div class="col-auto pl-1 ps-1">
                                <button type="button" class="btn btn-sm btn-light crud-filter-add"
                                    aria-label="{{ trans('csgtcrud::crud.agregarfiltro') }}"
                                    title="{{ trans('csgtcrud::crud.agregarfiltro') }}">
                                    <i class="fa fas fa-plus"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-light ml-1 ms-1 crud-filter-remove" disabled
                                    aria-label="{{ trans('csgtcrud::crud.quitarfiltro') }}"
                                    title="{{ trans('csgtcrud::crud.quitarfiltro') }}">
                                    <i class="fa fas fa-minus"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="col-auto d-flex flex-nowrap align-items-center text-right text-end pb-2">
                        <button id="crud-filter-clear" type="button" class="btn btn-sm btn-light">
                            {{ trans('csgtcrud::crud.limpiar') }}
                        </button>
                        <button id="crud-filter-apply" type="button" class="btn btn-sm btn-primary ml-1 ms-1">
                            <i class="fa fas fa-filter"></i> {{ trans('csgtcrud::crud.filtrar') }}
                        </button>
                    </div>
                </div>
                <hr />
            @endif
            <div class="{{ $responsive ? 'table-responsive' : '' }}">
                <table class="table table-sm table-striped table-hover dataTable display">
                    <thead>
                        <tr>
                            @foreach ($columns as $column)
                                <th>{!! $column['name'] !!}</th>
                                @if ($loop->last)
                                    <th class="text-right text-end">&nbsp;</th>
                                @endif
                            @endforeach
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
    @if (isset($extraView))
        @include($extraView)
    @endif
@stop
