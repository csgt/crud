@extends($layout)
@section('title')
    @if(isset($title))
        {!! $title !!}
    @endif
@stop
@section('subtitle')
    @if(isset($subtitle))
        {!! $subtitle !!}
    @endif
@stop
@section('breadcrumb')
    @if(isset($breadcrumb))
        {!! $breadcrumb !!}
    @endif
@stop
@section('content')
    <{{ $component }}
        @if(isset($props))
            @foreach($props as $prop => $val)
                @if(is_object($val) || is_array($val))
                    :{{$prop}} = "{{ json_encode($val) }}"
                @else
                    {{$prop}} = "{{$val}}"
                @endif
            @endforeach
        @endif
    />
    {{-- Un elemento que Vue nunca procesa se queda en el DOM sin renderizar nada
         y sin un solo mensaje en consola: el navegador ignora las etiquetas que
         no conoce. Cuando Vue si lo monta, reemplaza la etiqueta por el DOM del
         componente, asi que encontrarla todavia aqui significa que no paso nada. --}}
    <script>
        window.addEventListener('load', function() {
            // Margen para un componente registrado de forma asincrona.
            setTimeout(function() {
                var problems = [];

                if (document.querySelector('{{ $component }}')) {
                    problems.push(
                        'Vue no monto el componente <{{ $component }}>. Revise que Vue este cargado, ' +
                        'que el componente este registrado, y que la aplicacion use el build de Vue con ' +
                        'compilador: el build runtime-only no compila plantillas que llegan en el HTML.');
                }
                if (!problems.length) {
                    return;
                }

                var message = 'csgt/crud: ' + problems.join(' ');
                var banner = document.createElement('div');
                banner.setAttribute('style',
                    'background:#b00020;color:#fff;padding:12px;font-family:sans-serif;font-size:14px');
                banner.textContent = message;
                document.body.insertBefore(banner, document.body.firstChild);
                console.error(message);
            }, 300);
        });
    </script>
@stop
@section('prejavascript')
    @if(isset($state))
    <script>
        // El estado vive bajo un namespace propio. Publicarlo como un global
        // llamado "state" lo dejaba a merced de cualquier otra cosa en la pagina
        // que use ese nombre, en las dos direcciones: nos lo pisaban y lo
        // pisabamos.
        window.csgtCrud = window.csgtCrud || {};
        window.csgtCrud.state = {!! json_encode($state, JSON_PRETTY_PRINT) !!};

        // Alias para los componentes que todavia leen el global suelto. Solo se
        // define si nadie lo ocupo antes, para no pisar codigo ajeno. Deprecado:
        // lea window.csgtCrud.state.
        if (typeof window.state === 'undefined') {
            window.state = window.csgtCrud.state;
        }
    </script>
    @endif
@stop
