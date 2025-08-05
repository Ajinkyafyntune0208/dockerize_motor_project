@extends('admin_lte.layout.app', ['activePage' => 'job-info', 'titlePage' => __('Job Info')])
@section('content')
<div class="card card-primary">
    <div class="card-body">
        <div class="card-title d-flex justify-content-end w-100">
            <div class="title-right">
                <a class="btn btn-primary btn-sm float-end ms-1" type="button" href="{{ url()->previous() }}"
                    onclick="window.close();"><i class="fa fa-arrow-left"></i> Back</a>
                <button class="btn btn-info me-1 btn-sm float-end download" type="button"><i
                        class="fa fa-arrow-circle-down"></i> Download</button>
            </div>
        </div>
        <p>
            <b>ID :</b> <span id="id">{{ $log['id'] ?? '' }}</span> <br><br>
            <b>UUID :</b> <span id="uuid">{{ $log['uuid'] ?? '' }}</span> <br><br>
            <b>connection :</b> <span id="connection">{{ $log['connection'] ?? '' }}</span> <br><br>
            <b>queue :</b> <span id="queue">{{ $log['queue'] ?? '' }}</span> <br><br>
            <b>payload :</b> <span id="payload">{{ $log['payload'] ?? '' }}</span> <br><br>
            <b>exception :</b> <span id="exception">{{ $log['exception'] ?? '' }}</span> <br><br>
            <b>failed_at :</b><br /> <span id="failed_at">{{ $log['failed_at'] ?? '' }}</span> <br><br>
        </p>
    </div>
</div>
@endsection
@section('scripts')
<script>
    $(document).ready(function() {
        $('.download').click(function() {
            let text = `ID : ` + $('#id').text() + `
            UUID : ` + $('#uuid').text() + `
            Connection : ` + $('#connection').text() + `
            Queue : ` + $('#queue').text() + `
            Payload :
            ` + $('#payload').text() + `
            Exception :
            ` + $('#exception').text() + `
            Failed At :
            ` + $('#failed_at').text() + `
            `;
            var filename = "job_runner_" + $('#uuid').text() + ".txt";
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