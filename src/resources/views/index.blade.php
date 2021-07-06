@extends($layout)
@section('title')
    {!! $title !!}
@stop
@section('breadcrumb')
    {!! $breadcrumb !!}
@stop
@section('javascript')
@stop
@section('content')
    <crud-index
        @foreach($params as $param => $val)
            @if(is_object($val) || is_array($val))
                :{{$param}} = "{{ json_encode($val) }}"
            @else
                {{$param}} = "{{$val}}"
            @endif
        @endforeach
    />
@stop
