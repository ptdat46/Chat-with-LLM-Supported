@extends('layouts.app')

@section('content')
    <div class="container">
        <div id="main"
             data-user="{{ json_encode($user) }}"
             data-base-url="{{ $base_url }}"
        ></div>
    </div>
@endsection
