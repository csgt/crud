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
            $.fn.dataTable.ext.errMode = function(settings, helpPage, message) {
                console.log(JSON.stringify(message));
            };
            var oTable = $('.dataTable').dataTable({
                processing: true,
                serverSide: true,
                searchDelay: 500,
                @if ($stateSave)
                    stateSave: true,
                    stateSaveParams: function(settings, data) {
                        data.columns.forEach(function(column) {
                            delete column.visible;
                        });
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
                    url: "/{!! Request::path() !!}/data{{ $queryParameters }}",
                    headers: {
                        'X-CSRF-Token': "{{ csrf_token() }}"
                    },
                    method: "POST",
                    error: function(xhr, error, thrown) {
                        alert(xhr.responseJSON.message)
                    },
                },
                bLengthChange: false,
                sDom: '<"row" @if ($showSearch)<"col-sm-8 pull-left"f>@endif <"col-sm-4"<"btn-toolbar pull-right"  B <"btn-group btn-group-sm btn-group-add">>>>     t<"pull-left"i><"pull-right"p>',
                iDisplayLength: {!! $perPage !!},
                columnDefs: [{
                        targets: -1,
                        class: "text-right text-end",
                        data: null,
                        sortable: false,
                        render: function(data, type, full, meta) {
                            var id = data['DT_RowId'];
                            var html = '';
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
                                    '<div class="btn-group btn-group-sm"><a class="mr-1 btn btn-sm btn-block {{ $extraButton['class'] }}" title="{!! $extraButton['title'] !!}" href="{{ $parte1 }}' +
                                    id +
                                    '{{ $parte2 . $urlVars }}" {{ $target }} {!! $extraButton['confirm'] ? "onclick=\"return confirm(\'" . $extraButton['confirmmessage'] . "\');\"" : '' !!}><i class="{{ $extraButton['icon'] }}"></i></a></div>';
                            @endforeach

                            @if ($permisos['update'])
                                html +=
                                    '<div class="btn-group btn-group-sm"><a class="btn btn-sm btn-block btn-info" title="{{ trans('csgtcrud::crud.editar') }}" href="/{!! Request::path() !!}/' +
                                    id +
                                    '/edit/{!! $queryParameters !!}"><i class="fa fa-pencil-alt"></i></a></div>';
                            @endif ;
                            @if ($permisos['destroy'])
                                html += '<div class="btn-group btn-group-sm">\
                                                        <form action="/{!! Request::path() !!}/' + id + '{!! $queryParameters !!}" class="btn-delete" method="POST">\
                                                        <input type="hidden" name="_method" value="DELETE">\
                                                        <input type="hidden" name="_token" value="{{ csrf_token() }}">\
                                                        <button type="submit" class="btn btn-sm btn-block btn-danger ml-1" title="{{ trans('csgtcrud::crud.eliminar') }}" onclick="return confirm(\'{{ trans('csgtcrud::crud.seguro') }}\')">\
                                                        <i class="fa fa-trash"></i>\
                                                        </button>\
                                                        </form></div>';
                            @endif ;
                            return html;
                        }
                    },
                    @foreach ($columns as $column)
                        {
                            targets: {{ $loop->index }},
                            class: "{!! $column['class'] !!}",
                            searchable: "{!! $column['searchable'] !!}",

                            @if ($column['type'] == 'date' || $column['type'] == 'datetime' || $column['type'] == 'time')
                                data: null,
                                render: function(data) {
                                    var date = moment.utc(data[{{ $loop->index }}]);
                                    if (!date.isValid()) return null

                                    @if ($column['utc'] == true)
                                        date.local()
                                    @endif

                                    @if ($column['type'] == 'date')
                                        return date.format('DD-MM-YYYY')
                                    @else
                                        return date.format('DD-MM-YYYY HH:mm')
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
                                            return '<img width="{!! $column['filewidth'] !!}" src="' + val +
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

            $('.dataTable').on('init.dt', function() {
                console.log('init');
                $('.pagination').addClass('pagination-sm');
                $('.dataTables_info').addClass('small text-muted');
                @if ($permisos['create'])
                    $('.btn-group-add').html(
                        '<a type="button" class="btn btn-dark" href="/{!! Request::path() . '/create/' . $queryParameters !!}">{{ trans('csgtcrud::crud.agregar') }}</a>'
                    );
                @endif
                @foreach ($extraActions as $action)
                    $('.btn-group-add').append(
                        '<a type="button" class="btn btn-dark" href="{!! $action['url'] !!}">{{ $action['title'] }}</a>'
                    );
                @endforeach
                $('.dt-buttons').addClass('btn-group-sm');
                $('div[id$=_filter] input').css('width', '100%').attr('placeholder',
                    '{{ trans('csgtcrud::crud.buscar') }}');
                $('.dataTables_filter label').css('width', '100%');
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
            <div class="{{ $responsive ? 'table-responsive' : '' }}">
                <table class="table table-sm table-striped table-hover dataTable display">
                    <thead>
                        <tr>
                            @foreach ($columns as $column)
                                <th>{!! $column['name'] !!}</th>
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
    @if (isset($extraView))
        @include($extraView)
    @endif
@stop
