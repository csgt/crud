@extends($template)
@section('breadcrumb')
    {!! $breadcrumb !!}
@stop
@section('content')
    @php
        if (!function_exists('arrayToFields')) {
            function arrayToFields($arr)
            {
                $callback = function ($key, $value) {
                    return $key . "=\"" . $value . "\"";
                };
                $fields = implode(' ', array_map($callback, array_keys($arr), $arr));

                return $fields;
            }
        }
    @endphp
    <form method="POST" action="/{{ $pathstore . $queryParameters }}" class="form-horizontal" id="frmCrud"
        enctype="multipart/form-data">
        <input type="hidden" name="__tz__" id="__tz__">
        <div class="card">
            <div class="card-body">
                <div class="row">
                    @if ($data)
                        <input type="hidden" name="_method" value="PUT">
                    @endif
                    {{ csrf_field() }}
                    @foreach ($columns as $column)
                        @php
                            $real = $column['campoReal'];
                            $valor = $data
                                ? $data->{$column['campoReal']}
                                : (old($column['campoReal']) ?:
                                $column['default']);
                            $label =
                                '<label for="' .
                                $column['campoReal'] .
                                '" class="control-label">' .
                                $column['name'] .
                                '</label>';
                            $arr = [
                                'class' => 'form-control' . ($errors->has($real) ? ' is-invalid' : ''),
                            ];
                        @endphp
                        <div class="{{ $column['editClass'] }}">
                            <div class="form-group">
                                @if ($column['type'] == 'password')
                                    <!---------------------------- PASSWORD ---------------------------------->
                                    <div class="row">
                                        {!! $label !!}
                                        <div class="col-sm-6">
                                            @php
                                                $arr['placeholder'] = 'Password';
                                            @endphp
                                            <input type="password" name="{{ $column['campoReal'] }}"
                                                {!! arrayToFields($arr) !!}>
                                        </div>
                                        <div class="col-sm-6">
                                            <input type="password" name="{{ $column['campoReal'] . 'confirm' }}"
                                                {!! arrayToFields($arr) !!}>
                                            @if ($data)
                                                <p class="help-block">* Dejar en blanco para no cambiar
                                                    {!! $column['name'] !!}</p>
                                            @endif
                                        </div>
                                    </div>
                                @elseif($column['type'] == 'textarea')
                                    <!---------------------------- TEXTAREA ---------------------------------->
                                    {!! $label !!}
                                    <textarea name="{{ $column['campoReal'] }}" {!! arrayToFields($arr) !!}>{!! $valor !!}</textarea>
                                @elseif($column['type'] == 'summernote')
                                    <!---------------------------- SUMMERNOTE ---------------------------------->
                                    {!! $label !!}
                                    <?php $arr = ['class' => 'summernote']; ?>
                                    <textarea name="{{ $column['campoReal'] }}" {!! arrayToFields($arr) !!}>{!! $valor !!}</textarea>
                                @elseif($column['type'] == 'bool')
                                    <!---------------------------- BOOLEAN ---------------------------------->
                                    <div>&nbsp;</div>
                                    <div class="checkbox">
                                        <label>
                                            <input type="checkbox" name="{{ $column['campoReal'] }}" value="1"
                                                {{ $valor == 1 ? 'checked' : '' }}>
                                            {!! $column['name'] !!}
                                        </label>
                                        <input class="hiddencheckbox" type='hidden' value='0'
                                            name='{{ $column['campoReal'] }}'>
                                    </div>
                                @elseif($column['type'] == 'date')
                                    <!---------------------------- DATE ---------------------------------->
                                    {!! $label !!}
                                    <input id="div{!! $column['campoReal'] !!}" type="date"
                                        class="{{ $arr['class'] }} {{ $column['utc'] ? 'dputc' : 'dp' }}"
                                        name="{{ $column['campoReal'] }}" data-value="{{ $valor }}"
                                        {!! arrayToFields($arr) !!}>
                                @elseif($column['type'] == 'datetime')
                                    <!---------------------------- DATETIME ---------------------------------->
                                    {!! $label !!}
                                    <input id="div{!! $column['campoReal'] !!}" type="datetime-local"
                                        class="{{ $arr['class'] }} {{ $column['utc'] ? 'dtputc' : 'dtp' }}"
                                        name="{{ $column['campoReal'] }}" data-value="{{ $valor }}"
                                        {!! arrayToFields($arr) !!}>
                                @elseif($column['type'] == 'combobox')
                                    <!---------------------------- COMBOBOX ---------------------------------->
                                    @php
                                        $arr['class'] = 'selectpicker ' . $arr['class'];
                                        $arr['data-width'] = 'auto';
                                    @endphp
                                    {!! $label !!}
                                    <div>
                                        <?php $campo = $data ? $data->{$column['field']} : ''; ?>
                                        <select name="{{ $column['field'] }}" {!! arrayToFields($arr) !!}>
                                            @foreach ($combos[$column['alias']] as $id => $opcion)
                                                <option value="{{ $id }}"
                                                    {{ $campo == $id ? "selected='selected'" : '' }}>
                                                    {!! $opcion !!}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @elseif($column['type'] == 'multi')
                                    <!---------------------------- MULTI ---------------------------------->
                                    @php
                                        $arr['class'] = 'selectpicker ' . $arr['class'];
                                        $arr['data-width'] = 'auto';
                                    @endphp
                                    {!! $label !!}
                                    <div>
                                        <?php $campo = $data ? $data->{$column['field']} : ''; ?>
                                        <select multiple="multiple" name="{{ $column['field'] }}[]"
                                            {!! arrayToFields($arr) !!}>

                                            @foreach ($combos[$column['alias']] as $id => $opcion)
                                                <option value="{{ $id }}"
                                                    @if ($campo != '') {{ $campo->find($id) ? "selected='selected'" : '' }} @endif>
                                                    {!! $opcion !!}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @elseif($column['type'] == 'enum')
                                    <!---------------------------- ENUM ---------------------------------->
                                    @php
                                        $arr['class'] = 'selectpicker ' . $arr['class'];
                                        $arr['data-width'] = 'auto';
                                    @endphp
                                    {!! $label !!}
                                    <select name="{{ $column['campoReal'] }}" {!! arrayToFields($arr) !!}>
                                        @foreach ($column['enumarray'] as $id => $opcion)
                                            <option value="{{ $id }}"
                                                {{ $valor == $id ? "selected='selected'" : '' }}>
                                                {!! $opcion !!}</option>
                                        @endforeach
                                    </select>
                                @elseif($column['type'] == 'file' || $column['type'] == 'image' || $column['type'] == 'securefile')
                                    <!---------------------------- FILE/IMAGE/SECUREFILE ---------------------------------->
                                    {!! $label !!}
                                    <div>
                                        <input type="file" name="{{ $column['campoReal'] }}">
                                        @if ($data)
                                            <p class="help-block">{!! $valor !!}</p>
                                        @endif
                                    </div>
                                @elseif($column['type'] == 'numeric')
                                    <!---------------------------- NUMERIC ---------------------------------->
                                    {!! $label !!}
                                    <input type="number" step="any" name="{{ $column['campoReal'] }}"
                                        value="{{ $valor }}" {!! arrayToFields($arr) !!}>
                                @else
                                    <!---------------------------- DEFAULT ---------------------------------->
                                    {!! $label !!}
                                    <input type="text" name="{{ $column['campoReal'] }}" value="{{ $valor }}"
                                        {!! arrayToFields($arr) !!}>
                                @endif
                                @error($real)
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                        </div>
                    @endforeach
                </div>
            </div>
            <div class="card-footer">
                <input type="submit" value="{{ trans('csgtcrud::crud.guardar') }}" class="btn btn-primary">&nbsp;
                <a href="javascript:window.history.back();"
                    class="btn btn-default btn-light">{{ trans('csgtcrud::crud.cancelar') }}</a>
            </div>
        </div>
    </form>
@endsection

@section('javascript')
    <script type="module">
        $(function() {
            document.querySelectorAll(".dtputc").forEach((item) => {
                item.value = new moment.utc(item.dataset.value).local().format('YYYY-MM-DD\THH:mm');
            });

            document.querySelectorAll(".dtp").forEach((item) => {
                item.value = new moment(item.dataset.value).format('YYYY-MM-DD\THH:mm');
            });

            document.querySelectorAll(".dputc").forEach((item) => {
                item.value = new moment.utc(item.dataset.value).local().format('YYYY-MM-DD');
            });

            document.querySelectorAll(".dp").forEach((item) => {
                item.value = new moment(item.dataset.value).format('YYYY-MM-DD');
            });

            document.querySelectorAll(".tp").forEach((item) => {
                item.value = new moment(item.dataset.value).format('HH:mm');
            });

            document.querySelectorAll(".tputc").forEach((item) => {
                item.value = new moment.utc(item.dataset.value).local().format('HH:mm');
            });

            document.getElementById('__tz__').value = new Date().getTimezoneOffset() / -60;

            @if ($uses['selectize'])
                $('.selectpicker').selectize();
            @endif
            @if ($uses['summernote'])
                $('.summernote').summernote({
                    'lang': 'es-ES',
                });
            @endif
            function makeCheckValidation(checkbox) {
                if ($(checkbox).is(":checked")) {
                    $(checkbox).parent().next().attr('disabled', true);
                } else {
                    $(checkbox).parent().next().attr('disabled', false);
                }
            }
            $('input[type="checkbox"]').each(function() {
                makeCheckValidation(this);
                $(this).change(function() {
                    makeCheckValidation(this);
                })
            });
        });
    </script>
@endsection
