@extends('layout.app', ['activePage' => 'rc-report', 'titlePage' => __('Proposal Validation Report')])
@section('content')
    <!-- partial -->
    <div class="content-wrapper">
        <div class="row">
            <div class="col-sm-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Rc Report</h5>
                        @if (session('status'))
                            <div class="alert alert-{{ session('class') }}">
                                {{ session('status') }}
                            </div>
                        @endif
                        <div class="form-group">
                            <form action="" method="get">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <Label>From Date</Label>
                                            <input type="text" name="from"
                                                value="{{ old('from', request()->from) }}" required id=""
                                                class="datepickers form-control" placeholder="From" autocomplete="off">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <Label>To Date</Label>
                                            <input type="text" name="to" value="{{ old('to', request()->to) }}"
                                                required id="" class="datepickers form-control" placeholder="To"
                                                autocomplete="off">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <Label>To Date</Label>
                                            <select name="type" class="form-control">
                                                <option {{ old('type', request()->type) == 'view' ? 'selected' : '' }}>
                                                    {{ 'view' }}</option>
                                                <option {{ old('type', request()->type) == 'excel' ? 'selected' : '' }}>
                                                    {{ 'excel' }}</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="d-flex align-items-center h-100">
                                            <input type="submit" class="btn btn-outline-info btn-sm w-100">
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-striped" id="policy_reports">
                                <tbody>
                                    @foreach ($data as $index => $record)
                                        <tr>
                                            @foreach ($record as $item)
                                                <td>{{ $item }}</td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
@push('scripts')
    <script>
        $(document).ready(function() {
            // $('#policy_reports').DataTable();
            $('.datepickers').datepicker({
                todayBtn: "linked",
                autoclose: true,
                clearBtn: true,
                todayHighlight: true,
                toggleActive: true,
                format: "yyyy-mm-dd"
            });
        })

        function onClickDownload(filename, request, response, url) {
            let text = `URL :
${url}


Request :
${request}


Response :
${response}`;


            var filename = "request_response " + filename + ".txt";
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
