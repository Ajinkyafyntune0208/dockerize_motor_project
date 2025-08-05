@extends('layout.app', ['activePage' => 'Dashboard Mongo Logs', 'titlePage' => __('Dashboard Mongo Logs')])

@section('content')
    <div>
        <h4 class="card-title">Dashboard Mongo Logs</h4>
    
        @php
            $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        @endphp

        <pre>{{ $json }}</pre>
    </div>
@endsection
