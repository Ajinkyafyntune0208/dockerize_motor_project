@extends('admin_lte.layout.app', ['activePage' => 'ckyc-logs', 'titlePage' => __('CKYC Logs')])
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
            <b>Trace Id :</b> <span id="trace_id">{{ $log['trace_id'] ?? '' }}</span> <br><br>
            <b>Company :</b> <span id="company_alias">{{ $log['company_alias'] ?? '' }}</span> <br><br>

            <b>Request URL :</b><br /> <span id="url">{{ $log['url'] ?? '' }}</span> <br><br>

            <b>Request Headers :</b><br /> <span id="request_headers">{{ $request_headers ?? '' }}</span>
            <br><br>

            <b>Request :</b><br /> <span id="request">{{ $log['request'] ?? '' }}</span> <br><br>

            <b>Response Headers :</b><br /> <span id="response_headers">{{ $response_headers ?? '' }}</span>
            <br><br>

            <b>Response :</b><br /> <span id="response">{{ $log['response'] ?? '' }}</span> <br><br>

            <b>Response Time :</b><br /> <span id="response_time">{{ $log['response_time'] ?? '' }}</span>
            <br><br>

            <b>Status :</b> <span id="status_code">{{ $log['status_code'] ?? '' }}</span> <br><br>

            <b>Created At :</b> <span id="created_at">{{
                Carbon\Carbon::parse($log['created_at'])->format('d-M-Y H:i:s A') ?? '' }}</span>
        </p>
    </div>
</div>
@endsection
@section('scripts')
<script>
    $(document).ready(function() {
            $('.download').click(function() {
                let text = `Trace Id : ` + $('#trace_id').text() + `
                Company : ` + $('#company_alias').text() + `

                Request URL :
                ` + $('#url').text() + `

                Request Headers :
                ` + $('#request_headers').text() + `

                Request :
                ` + $('#request').text() + `

                Response Headers :
                ` + $('#response_headers').text() + `

                Response :
                ` + $('#response').text() + `

                Response Time :
                ` + $('#response_time').text() + `

                Status :
                ` + $('#status_code').text() + `

                Created At :
                ` + $('#created_at').text() + `
                `;
                var filename = "ckyc_" + $('#company_alias').text() + '_' + $('#trace_id').text() + ".txt";
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