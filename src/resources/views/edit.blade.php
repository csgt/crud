@extends($template)
@section('breadcrumb')
    {!! $breadcrumb !!}
@stop
@section('content')
    @php
        function arrayToFields($arr)
        {
            $callback = function ($key, $value) {
                return $key . "=\"" . $value . "\"";
            };
            $fields = implode(' ', array_map($callback, array_keys($arr), $arr));

            return $fields;
        }
    @endphp
    <form method="POST" action="{{ URL::to($pathstore) . $nuevasVars }}" class="form-horizontal" id="frmCrud"
        enctype="multipart/form-data">
        <div class="card">
            <div class="card-body">
                <div class="row">
                    @if ($data)
                        <input type="hidden" name="_method" value="PUT">
                    @endif
                    {{ csrf_field() }}
                    @foreach ($columnas as $columna)
                        @php
                            $real = $columna['campoReal'];
                            $valor = $data
                                ? $data->{$columna['campoReal']}
                                : (old($columna['campoReal']) ?:
                                $columna['default']);
                            $label =
                                '<label for="' .
                                $columna['campoReal'] .
                                '" class="control-label">' .
                                $columna['nombre'] .
                                '</label>';
                            $arr = [
                                'class' => 'form-control' . ($errors->has($real) ? ' is-invalid' : ''),
                            ];
                        @endphp
                        <div class="{{ $columna['editClass'] }}">
                            <div class="form-group">
                                <!---------------------------- PASSWORD ---------------------------------->
                                @if ($columna['tipo'] == 'password')
                                    {!! $label !!}
                                    @php
                                        $arr['placeholder'] = 'Password';
                                    @endphp
                                    <input type="password" name="{{ $columna['campoReal'] }}" {!! arrayToFields($arr) !!}>
                                    <input type="password" name="{{ $columna['campoReal'] . 'confirm' }}"
                                        {!! arrayToFields($arr) !!}>
                                    @if ($data)
                                        <p class="help-block">* Dejar en blanco para no cambiar {!! $columna['nombre'] !!}</p>
                                    @endif
                                    <!---------------------------- TEXTAREA ---------------------------------->
                                @elseif($columna['tipo'] == 'textarea')
                                    {!! $label !!}
                                    <textarea name="{{ $columna['campoReal'] }}" {!! arrayToFields($arr) !!}>{!! $valor !!}</textarea>
                                    <!---------------------------- SUMMERNOTE ---------------------------------->
                                @elseif($columna['tipo'] == 'summernote')
                                    {!! $label !!}
                                    @php $arr = ['class' => 'summernote']; @endphp
                                    <textarea name="{{ $columna['campoReal'] }}" {!! arrayToFields($arr) !!}>{!! $valor !!}</textarea>
                                    <!---------------------------- BOOLEAN ---------------------------------->
                                @elseif($columna['tipo'] == 'bool')
                                    <div class="checkbox">
                                        <label>
                                            <input type="checkbox" name="{{ $columna['campoReal'] }}"
                                                {{ $valor == 1 ? 'checked' : '' }}>
                                            {!! $columna['nombre'] !!}
                                        </label>
                                    </div>
                                    <!---------------------------- DATE ---------------------------------->
                                @elseif($columna['tipo'] == 'date')
                                    {!! $label !!}
                                    <div id="div{!! $columna['campoReal'] !!}" class="input-group">
                                        <input type="date" name="{{ $columna['campoReal'] }}"
                                            value="{{ $valor }}" {!! arrayToFields($arr) !!}>
                                    </div>
                                    <!---------------------------- DATETIME ---------------------------------->
                                @elseif($columna['tipo'] == 'datetime')
                                    {!! $label !!}
                                    <div id="div{!! $columna['campoReal'] !!}" class="input-group">
                                        <input type="datetime-local" name="{{ $columna['campoReal'] }}"
                                            value="{{ $valor }}" {!! arrayToFields($arr) !!}>
                                    </div>
                                    <!---------------------------- COMBOBOX ---------------------------------->
                                @elseif($columna['tipo'] == 'combobox')
                                    @php
                                        $arr['class'] = 'selectpicker form-control';
                                        $arr['data-width'] = 'auto';
                                    @endphp
                                    {!! $label !!}
                                    @php $campo = $data ? $data->{$columna['campo']} : ''; @endphp
                                    <select name="{{ $columna['campo'] }}" {!! arrayToFields($arr) !!}>
                                        @foreach ($combos[$columna['alias']] as $id => $opcion)
                                            <option value="{{ $id }}"
                                                {{ $campo == $id ? "selected='selected'" : '' }}>
                                                {!! $opcion !!}
                                            </option>
                                        @endforeach
                                    </select>
                                    <!---------------------------- MULTI ---------------------------------->
                                @elseif($columna['tipo'] == 'multi')
                                    @php
                                        $arr['class'] = 'selectpicker form-control';
                                        $arr['data-width'] = 'auto';
                                    @endphp
                                    {!! $label !!}
                                    <?php $campo = $data ? $data->{$columna['campo']} : ''; ?>
                                    <select multiple="multiple" name="{{ $columna['campo'] }}[]" {!! arrayToFields($arr) !!}>

                                        @foreach ($combos[$columna['alias']] as $id => $opcion)
                                            <option value="{{ $id }}"
                                                @if ($campo != '') {{ $campo->find($id) ? "selected='selected'" : '' }} @endif>
                                                {!! $opcion !!}</option>
                                        @endforeach
                                    </select>
                                    <!---------------------------- ENUM ---------------------------------->
                                @elseif($columna['tipo'] == 'enum')
                                    @php
                                        $arr['class'] = 'selectpicker form-control';
                                        $arr['data-width'] = 'auto';
                                    @endphp
                                    {!! $label !!}
                                    <select name="{{ $columna['campoReal'] }}" {!! arrayToFields($arr) !!}>
                                        @foreach ($columna['enumarray'] as $id => $opcion)
                                            <option value="{{ $id }}"
                                                {{ $valor == $id ? "selected='selected'" : '' }}>
                                                {!! $opcion !!}
                                            </option>
                                        @endforeach
                                    </select>
                                    <!---------------------------- FILE/IMAGE/SECUREFILE ---------------------------------->
                                @elseif($columna['tipo'] == 'file' || $columna['tipo'] == 'image' || $columna['tipo'] == 'securefile')
                                    {!! $label !!}
                                    <input type="file" name="{{ $columna['campoReal'] }}">
                                    @if ($data)
                                        <p class="help-block">{!! $valor !!}</p>
                                    @endif
                                    <!---------------------------- NUMERIC ---------------------------------->
                                @elseif($columna['tipo'] == 'numeric')
                                    {!! $label !!}
                                    <input type="text" name="{{ $columna['campoReal'] }}" value="{{ $valor }}"
                                        {!! arrayToFields($arr) !!}>
                                    <!---------------------------- DEFAULT ---------------------------------->
                                @else
                                    {!! $label !!}
                                    <input type="text" name="{{ $columna['campoReal'] }}" value="{{ $valor }}"
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
    <script type="text/javascript">
        $(function() {
            @if ($uses['selectize'])
                $('.selectpicker').selectize();
            @endif
            @if ($uses['summernote'])
                $('.summernote').summernote({
                    'lang': 'es-ES',
                });
            @endif
        });
    </script>
@endsection
