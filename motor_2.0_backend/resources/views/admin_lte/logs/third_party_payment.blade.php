@extends('admin_lte.layout.app', ['activePage' => 'log-third-party-payment', 'titlePage' => __('Third Party Payment Logs')])
@section('content')
    <div class="row">
        <div class="col-lg-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <p class="card-description"></p>
                    <form action="" method="GET">
                        <!-- @csrf -->
                        <div class="row mb-3">
                            <div class="col-sm-3">
                                <div class="form-group">
                                    <label class="active required" for="enquiry_id">Enquiry Id.</label>
                                    <input id="enquiry_id" name="enquiry_id" type="text" value="{{ old('enquiry_id', request()->enquiry_id) }}" class="form-control select2" placeholder="Enquiry Id" required>
                                    @error('enquiry_id')<span class="text-danger">{{ $message }}</span>@enderror
                                </div>
                            </div>
                            <div class="col-sm-3 text-right">
                                <button type="submit" class="btn btn-sm btn-outline-primary" style="margin-top: 32px;"><i class="fa fa-search"></i> Search</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-12 grid-margin stretch-card">
            <!-- @ dd($payment_logs) -->
            @if (!empty($payment_logs))
            <div class="card">
                <div class="card-body">
                    {{--<h4 class="card-title">Logs</h4>
                            <p class="card-description"></p>--}}
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th scope="col">#</th>
                                    <th scope="col">Enquiry Id</th>
                                    <th scope="col">Created At</th>
                                    <th scope="col" class="text-right">View Request Response</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($payment_logs as $log)
                                <tr>
                                    <td scope="row">{{ $loop->iteration }}</td>
                                    <td>{{ $log->enquiry_id }}</td>
                                    <td>{{ Carbon\Carbon::parse($log->created_at)->format('d-M-Y H:i:s A') }}</td>
                                    <td class="text-right">
                                        <div class="btn-group" role="group">
                                            @can('third_party_payment_logs.show')
                                            <!-- @ can('log.show') -->
                                            <!-- <a href="{{ route('admin.log.show', $log->id) }}" target="_blank" class="btn btn-sm btn-primary"><i class="fa fa-eye"></i></a> -->
                                            <button type="button" class="btn btn-sm btn-success" onclick="onClickDownload('{{ $log->enquiry_id }}', '{{json_encode($log->request)}}', '{{json_encode($log->response)}}')"><i class="fa fa-arrow-circle-down"></i></button>
                                            <!-- @ endcan -->
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @else
            <p>No Records or search for records</p>
            @endif
        </div>
    </div>
<!-- content-wrapper ends -->
@endsection
@push('scripts')
<script>
    $(document).ready(function() {  });
        $('#enquiry_id').keyup(function () {
            this.value = this.value.replace(/[^0-9\.]/g,'');
        });
        function onClickDownload(filename, request, response, url) {
            let text = `EnquiryId: \n ${filename} \nRequest : \n${request}\n\n\nResponse : \n${response}`;
            var filename = "third_party_payment_request_response_" + filename + ".txt";
            var element = document.createElement('a');
            element.setAttribute('href', 'data:text/plain;charset=utf-8,' + encodeURIComponent(text));
            element.setAttribute('download', filename);

            element.style.display = 'none';
            document.body.appendChild(element);
            element.click();
            document.body.removeChild(element);
        }
</script>
@endpush
