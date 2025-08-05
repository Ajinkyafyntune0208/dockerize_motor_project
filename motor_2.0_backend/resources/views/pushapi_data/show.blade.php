@extends('layout.app')

@section('content')
    <main class="container-fluid">
        <section class="mb-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Configuration
                        <a href="{{ route('admin.push-api.index') }}" class="btn btn-primary btn-sm float-end"><i class="fa fa-arrow-circle-o-left me-1"></i> Back</a>
                    </h5>
                    @if (session('status'))
                        <div class="alert alert-{{ session('class') }}">
                            {{ session('status') }}
                        </div>
                    @endif
                    <div class="row">
                        <div class="col-md-12">
                            <h3 class="mb-4">User Product Journey Id : </h3>
                            {{ customEncrypt($data->user_product_journey_id) }}
                            <hr>
                            <h3 class="my-4">Status : </h3>
                            {{ $data->Status }}
                            <hr>
                            <h3 class="my-4">Token : </h3>
                            {{ $data->token }}
                            <hr>
                            <h3 class="my-4">Request : </h3>
                            <pre>{{ json_encode($data->request, JSON_PRETTY_PRINT) }}</pre>
                            <hr>
                            <h3 class="my-4">Response : </h3>
                            <pre>{{ json_encode($data->response, JSON_PRETTY_PRINT) }}</pre>
                            <hr>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>
@endsection