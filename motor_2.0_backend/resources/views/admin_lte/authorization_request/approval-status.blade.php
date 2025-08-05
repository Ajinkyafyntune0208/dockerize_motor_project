@extends('admin_lte.layout.app', ['activePage' => 'approval-status', 'titlePage' => __('Approval Status')])

@section('content')
<div class="card">
    <div class="card-body">
        <ul class="nav nav-tabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" data-toggle="tab" href="#tab1">My Requests</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-toggle="tab" href="#tab2">Approval Requests to Me</a>
            </li>
        </ul>

        <div class="tab-content">
            <div id="tab1" class="container tab-pane fade"><br>
                <table id="data-table2" class="table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>Sr.&nbsp;No.</th>
                            <th>Approval&nbsp;By</th>
                            <th>Status</th>
                            <th>Requested&nbsp;Date</th>
                            <th>Action&nbsp;Taken&nbsp;On</th>
                            <th>Requested&nbsp;Option</th>
                            <th>Reject&nbsp;Comment</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($request_data as $key => $value)
                            <tr>
                                <td>{{ ++$key }}</td>
                                <td>{{ $value->requested_user }}</td>
                                <td style="width:15%; color: 
                                    {{ $value->approved_status == 'Y' ? 'green' : 
                                        ($value->approved_status == 'N' ? 'black' : 'red') }};
                                    font-weight: bold;">
                                    {{ $value->approved_status == 'Y' ? 'Approved' : 
                                        ($value->approved_status == 'N' ? 'Pending' : 'Rejected') }}
                                </td>             
                                <td>{{ Carbon\Carbon::parse($value->requested_date)->format('d-m-Y') }}</td>
                                <td>{{ is_null($value->approved_date) ? null : Carbon\Carbon::parse($value->approved_date)->format('d-m-Y') }}</td>
                                <td>{{ $value->reference_model }}</td>
                                <td>{{ $value->reject_comment }}</td>                         
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div id="tab2" class="container tab-pane active"><br>
                <table id="data-table1" class="table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>Sr.&nbsp;No.</th>
                            <th>Requested&nbsp;By</th>
                            <th>Status</th>
                            <th>Requested&nbsp;Date</th>
                            <th>Action&nbsp;Taken&nbsp;On</th>
                            <th>Requested&nbsp;Option</th>
                            <th>Reject&nbsp;Comment</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($response_data as $key => $value)
                            <tr>
                                <td>{{ ++$key }}</td>
                                <td>{{ $value->requested_user }}</td>
                                <td style="width:15%; color: 
                                    {{ $value->approved_status == 'Y' ? 'green' : 
                                        ($value->approved_status == 'N' ? 'black' : 'red') }};
                                    font-weight: bold;">
                                    {{ $value->approved_status == 'Y' ? 'Approved' : 
                                        ($value->approved_status == 'N' ? 'Pending' : 'Rejected') }}
                                </td>             
                                <td>{{ Carbon\Carbon::parse($value->requested_date)->format('d-m-Y') }}</td>
                                <td>{{ is_null($value->approved_date) ? null : Carbon\Carbon::parse($value->approved_date)->format('d-m-Y') }}</td>
                                <td>{{ $value->reference_model }}</td>
                                <td>{{ $value->reject_comment }}</td>                         
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    $(document).ready(function() {
        function initDataTableWithFilters(tableId) {
            var table = $(tableId).DataTable({
                buttons: ["copy", "csv", "excel", "pdf", "print", "colvis"]
            });

            table.buttons().container().appendTo(tableId + '_wrapper .col-md-6:eq(0)');

            var header = $(tableId + ' thead');

            var selectRow = $('<tr></tr>').appendTo(header);

            table.columns().every(function (index) {
                var column = this;
                var th = $('<th></th>').appendTo(selectRow);

                if (index === 2) {
                    var select = $('<select class="form-control form-control-sm" style="width:120%;"><option value="">Select</option></select>')
                        .appendTo(th)
                        .on('change', function () {
                            var val = $.fn.dataTable.util.escapeRegex($(this).val());
                            column.search(val ? '^' + val + '$' : '', true, false).draw();
                        });

                    column.data().unique().sort().each(function (d, j) {
                        select.append('<option value="' + d + '">' + d + '</option>');
                    });
                }
            });
        }

        initDataTableWithFilters('#data-table1');
        initDataTableWithFilters('#data-table2');
    });
</script>
@endsection
