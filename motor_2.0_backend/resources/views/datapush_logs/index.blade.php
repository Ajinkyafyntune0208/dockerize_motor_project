@extends('layout.app', ['activePage' => 'user', 'titlePage' => __('Data Push Logs')])
@section('content')

<style>
    :root{
    --primary: #1f3bb3;
    }
    .cus-cell {
        max-width: 200px; /* tweak me please */
        white-space : nowrap;
        overflow : hidden;
        text-align: center;
    }
    .cell-exp:hover {
        max-width : 600px;
        text-overflow: ellipsis;
    }

    /* .cell-exp:hover {
        max-width : initial;
    } */
    .btn-cus{
        height: 40px;
        border-radius: 10px;
        background: transparent;
        border: 1px solid var(--primary);
        color: var(--primary);

        display: flex;
        align-items: center;
        justify-content: center;
    }
    .btn-cus:hover{
        background-color: var(--primary);
        color: white;
    }
    .btn-cus.active{
        background-color: var(--primary);
        color: white;
    }
</style>
<div class="content-wrapper">

    <div class="row">
        <div class="col-sm-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">

                    <div id="successMessage" class="alert alert-success" style="display: none;">Proposal Validation submitted successfully!</div>
                    <div id="failMessage" class="alert alert-danger" style="display: none;">Submission Failed!</div>

                    <h4 class="card-title ">Data Push Logs </h4>

                    <form  action="" method="get">
                        @csrf

                        <div class="row">
                            <div class="col-sm-4 form-group">
                                <label class="font-weight-bold required" for="senqid">Enter Enquiry ID</label>
                                <input type="text" class="form-control" id="senqid" name="enqid" value="{{ old('enquiryId', request()->enquiryId ?? null) }}" placeholder="Enquiry Id" required>
                            </div>
                            <div class="col-sm-5 form-group">
                                <div class="row">
                                    <div class="col-md-6">
                                        <label class="required" for="">From Date</label>
                                        <input type="text" name="from_date" value="{{ old('from_date', request()->from_date) }}"  id="" class="datepickers form-control" placeholder="From" autocomplete="off" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="required" for="">To Date</label>
                                        <input type="text" name="to_date" value="{{ old('to_date', request()->to_date) }}"  id="" class="datepickers form-control" placeholder="To" autocomplete="off" required>
                                    </div>
                                </div>
                            </div>

                            <div class="col-sm-3 d-flex align-items-center">
                                <button type="submit" id="submit" class="btn btn-cus"><i class="fa fa-search m-1"></i> Search</button>
                            </div>

                            {{-- <div class="col-sm-3">
                                <div class="form-group">
                                    <label>Response Status</label>
                                    <select class="form-control" name="resstatus">
                                        <option value="" selected> Select Status</option>
                                        <option value="SUCCESS">Success</option>
                                        <option value="FAILED">Failed</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-sm-3">
                                <div class="form-group">
                                    <label>Server Status Code</label>
                                    <input type="text" name="httpstatus" class="form-control">
                                </div>
                            </div> --}}

                        </div>

                    </form>

                </div>
            </div>
        </div>
    </div>

    <div class="card" >
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped" style="width:100%" id="sdplogs">
                    <thead>
                        <tr>
                            <th class="text-wrap text-center" scope="col">View Request Response</th>
                            <th scope="col">#</th>
                            <th scope="col">Created At</th>
                            <th scope="col">Enquiry ID</th>
                            <th scope="col">Url</th>
                            <th scope="col">Request Headers</th>
                            <th scope="col">Request</th>
                            <th scope="col">Response</th>
                            <th scope="col">Status</th>
                            <th scope="col">Server Status</th>

                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($logs as $log)
                            <tr>
                                <td class="text-right">
                                    <div class="btn-group" role="group">
                                        @can('log.show')
                                            <a class="btn btn-sm btn-primary"
                                                href="{{ route('admin.datapush_log_show', ['id'=>$log->id]) }}"
                                                target="_blank">
                                                <i class="fa fa-eye"></i>
                                            </a>
                                            <a class="btn btn-sm btn-success"
                                                href="{{ route('admin.datapush_log_download', ['type' => 'datapushlog','id'=>$log->id ] )}}"  target="_blank" >
                                                <i class="fa fa-arrow-circle-down"></i>
                                            </a>
                                        @endcan
                                    </div>
                                </td>
                                <td class="cus-cell" style="max-width: 50px;" scope="row">{{ $loop->iteration }}</td>
                                <td class="cus-cell">{{ Carbon\Carbon::parse($log->created_at)->format('d-M-Y H:i:s') }}</td>
                                <td class="cus-cell">{{ customEncrypt($log->enquiry_id) }}</td>
                                <td class="cus-cell cell-exp">{{ $log->url }}</td>
                                <td class="cus-cell cell-exp">{{ json_encode($log->request_headers) }}</td>
                                <td class="cus-cell cell-exp">{{ json_encode($log->request)}}</td>
                                <td class="cus-cell cell-exp">{{ json_encode($log->response) }}</td>
                                <td class="cus-cell cell-exp">{{ $log->status }}</td>
                                <td class="cus-cell cell-exp">{{ $log->status_code}}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

    </div>

</div>

<script src="{{ asset('js/jquery-3.7.0.min.js') }}"  crossorigin="anonymous"></script>



<script >

     $(document).ready(function() {
        $('#sdplogs').DataTable();
        $('.datepickers').datepicker({
            todayBtn: "linked",
            autoclose: true,
            clearBtn: true,
            todayHighlight: true,
            toggleActive: true,
            format: "yyyy-mm-dd"
        });
        $('[name="sdplogs_length"]').attr({
            "data-style":"btn-sm btn-primary",
            "data-actions-box":"true",
            "class":"selectpicker px-3",
            "data-live-search":"true"
        });
        $('.selectpicker').selectpicker();
    });
</script>
@endsection
