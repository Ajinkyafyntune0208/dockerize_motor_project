@extends('admin_lte.layout.app', ['activePage' => 'commission-api-logs', 'titlePage' => __('Commission Api Logs')])
@section('content')
<div class="card card-primary">
    <div class="card-body">
        <div class="card-title d-flex justify-content-end w-100">
            <div class="title-right">
                <a class="btn btn-primary btn-sm float-end ms-1" type="button" href="#{{ url()->previous() }}"
                    onclick="window.close();"><i class="fa fa-arrow-left"></i> Back</a>
                <button class="btn btn-info me-1 btn-sm float-end download" type="button"><i
                        class="fa fa-arrow-circle-down"></i> Download</button>
            </div>
        </div>
        <p>
            <b>Trace Id :</b> <span id="trace_id">{{ $traceId}}</span> <br><br>

            <b>Request URL :</b><br /> <span id="url">{{$log->url}}</span> <br><br>

            <b>Request :</b><br /> <span id="request">{{json_encode($log->request)}}</span> <br><br>

            <b>Response :</b><br /> <span id="response">{{json_encode($log->response)}}</span> <br><br>
            <b>Created At :</b> <span id="created_at">{{date('d-M-Y h:i:s A', strtotime($log->updated_at))}}</span>
        </p>
    </div>
</div>
@endsection
@section('scripts')
<script>
    $(document).ready(function() {
            $('.download').click(function() {
                let text = `
Trace Id : ` + $('#trace_id').text() + `
Company : ` + $('#company_alias').text() + `

Request URL :
` + $('#url').text() + `

Request :
` + $('#request').text() + `

Response :
` + $('#response').text() + `

Created At :
` + $('#created_at').text() + `
`;
                var filename = "commission-api-logs-" + $('#company_alias').text() + '-' + $('#trace_id').text() + ".txt";
                var element = document.createElement('a');
                element.setAttribute('href', 'data:text/plain;charset=utf-8,' + encodeURIComponent(text));
                element.setAttribute('download', filename);

                element.style.display = 'none';
                document.body.appendChild(element);
                element.click();
                document.body.removeChild(element);
            });
        });
</script>
@endsection