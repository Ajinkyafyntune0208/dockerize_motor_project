@extends('layout.app', ['activePage' => 'Kafka Logs', 'titlePage' => __('Kafka Logs Request Details')])

@section('content')
    <div class="content-wrapper">
        <div class="row">
            <div class="col-lg-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">

                        <h4 class="card-title">Kafka Logs Request Details</h4>

                        @if(!empty($logsDetails) && $logsDetails->enquiryId != "")
                        <a class="btn btn-danger" title='Back' href='{{ url('admin/kafka-logs') . '?enquiryId=' . $logsDetails->enquiryId }}'>
                            Back</a>
                        @endif

                        @if(!empty($logsDetails) && $logsDetails->request != "")
                        <p class="card-description">
                            <pre>{{ json_encode(json_decode($logsDetails->request),JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)   }}</pre>
                        </p>
                        @else
                        <p style="font-size: 1rem;" class="m-3 text-danger">
                            No Data Found
                        </p>
                        @endif

                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
