@extends('admin_lte.layout.app', ['activePage' => 'rto-master', 'titlePage' => __('RTO Master')])
@section('content')
    <div class="card">
        <div class="card-header">
            <a href="{{ route('admin.sync_rto') }}" class="btn btn-primary float-right">Sync RTO</a>
        </div>
        <div class="card-body">
            <table id="data-table" class="table table-bordered table-hover">
                <thead>
                    <tr>
                        <th>Sr. No.</th>
                        <th>RTO Code</th>
                        <th>RTO Name</th>
                        <th>State</th>
                        <th>Zone</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rto_master as $key => $value)
                        <tr>
                            <td>{{ ++$key }}</td>
                            <td>{{ $value->rto_code }}</td>
                            <td>{{ $value->rto_name }}</td>
                            <td>{{ $value->state_name }}</td>
                            <td>{{ $value->zone_name }}</td>
                            @if ($value->status !== 'Active')
                                <td class="text-danger">{{ $value->status }}</td>
                            @else
                                <td class="text-success">{{ $value->status }}</td>
                            @endif
                            <td>
                                <!-- <div class="btn-group btn-group-toggle"> -->
                                @can('rto_master.edit')
                                <a class="btn btn-info mr-1 view" href="#" data-toggle="modal"
                                    data-target="#modal-default" data="{{ $value }}"><i class="far fa-edit"></i></a>
                                @endcan
                                <!-- </div> -->
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <!-- Modal -->
    <div class="modal fade" id="modal-default" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel"> Edit RTO <span></span></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">

                        <form action="{{ route('admin.rto-master.update', [1, 'data' => request()->all()]) }}"
                            method="post">
                            @csrf
                            @method('PUT')
                            <div class="row">
                                <input type="text" name="id" id="id" hidden />
                                <div class="col-4 col-sm-4 col-md-4 col-lg-4">
                                    <div class="form-group">
                                        <label for="rto_code">RTO Code</label>
                                        <input type="text" class="form-control" name="rto_code" id="rto_code"
                                            required />
                                    </div>
                                </div>
                                <div class="col-4 col-sm-4 col-md-4 col-lg-4">
                                    <div class="form-group">
                                        <label for="rto_name">RTO Name</label>
                                        <input type="text" class="form-control" name="rto_name" id="rto_name"
                                            required />
                                    </div>
                                </div>
                                <div class="col-4 col-sm-4 col-md-4 col-lg-4">
                                    <div class="form-group">
                                        <label for="rto_state">State</label>
                                        <select class="form-control" name="rto_state" id="rto_state" required>
                                            @foreach ($state_master as $key => $value)
                                                <option value="{{ $value->state_id }}">{{ $value->state_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-6 col-sm-6 col-md-6 col-lg-6">
                                    <div class="form-group">
                                        <label for="rto_zone">Zone</label>
                                        <select class="form-control" name="rto_zone" id="rto_zone" required>
                                            @foreach ($zone_master as $key => $value)
                                                <option value="{{ $value->zone_id }}">{{ $value->zone_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-6 col-sm-6 col-md-6 col-lg-6">
                                    <div class="form-group">
                                        <label for="rto_status">Status</label>
                                        <select class="form-control" name="rto_status" id="rto_status" required>
                                            <option value="Active">Active</option>
                                            <option value="Inactive">Inactive</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-12 col-sm-12 col-md-12 col-lg-12">
                                    <button type="submit" id="update-rto-btn" class="btn btn-primary w-100">Update
                                        RTO</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="modal-footer">
                    <!-- <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button> -->
                </div>

            </div>
        </div>
    </div>
@endsection('content')
@section('scripts')
    <script>
        $(function() {
            $('#data-table').DataTable({
                "dom": 'Bfrtip',
                "buttons": [
                    'copy', 'csv', 'excel', 'pdf', 'print', {
                        extend: 'colvis',
                        columns: 'th:not(:nth-child(3))'
                    }
                ],
                "initComplete": function() { //column wise filter
                    var notApplyFilterOnColumn = [0,3, 4, 5, 6];
                    var inputFilterOnColumn = [];
                    var showFilterBox = 'afterHeading'; //beforeHeading, afterHeading
                    $('.gtp-dt-filter-row').remove();
                    var theadSecondRow = '<tr class="gtp-dt-filter-row">';
                    $(this).find('thead tr th').each(function(index) {
                        theadSecondRow += '<td class="gtp-dt-select-filter-' + index +
                        '"></td>';
                    });
                    theadSecondRow += '</tr>';

                    if (showFilterBox === 'beforeHeading') {
                        $(this).find('thead').prepend(theadSecondRow);
                    } else if (showFilterBox === 'afterHeading') {
                        $(theadSecondRow).insertAfter($(this).find('thead tr'));
                    }

                    this.api().columns().every(function(index) {
                        var column = this;
                        if (inputFilterOnColumn.indexOf(index) >= 0 && notApplyFilterOnColumn
                            .indexOf(index) < 0) {
                            $('td.gtp-dt-select-filter-' + index).html(
                                '<input type="text" class="gtp-dt-input-filter">');
                            $('td input.gtp-dt-input-filter').on('keyup change clear',
                            function() {
                                if (column.search() !== this.value) {
                                    column
                                        .search(this.value)
                                        .draw();
                                }
                            });
                        } else if (notApplyFilterOnColumn.indexOf(index) < 0) {
                            var select = $(
                                    '<select data-style="btn-sm btn-primary" data-actions-box="true" class="selectpicker" data-live-search="true"><option value="">Select</option></select>'
                                    )
                                .on('change', function() {
                                    var val = $.fn.dataTable.util.escapeRegex(
                                        $(this).val()
                                    );

                                    column
                                        .search(val ? '^' + val + '$' : '', true, false)
                                        .draw();
                                });
                            $('td.gtp-dt-select-filter-' + index).html(select);
                            column.data().unique().sort().each(function(d, j) {
                                select.append('<option value="' + d + '">' + d +
                                    '</option>')
                            });
                        }
                    });
                }
            });



            $(document).on('click', '.view', function() {
                if (!popupShown) {
                    var data = JSON.parse($(this).attr('data'));

                    $('#exampleModalLabel').html(`Edit ${data.rto_name} RTO Details:`);

                    $("#rto_code").val(data.rto_code);
                    $("#rto_name").val(data.rto_name);
                    $("#rto_state").val(data.state_id);
                    $("#rto_zone").val(data.zone_id);
                    $("#rto_status").val(data.status);

                    // update button dynamic name
                    $('#update-rto-btn').html(`Update ${data.rto_name} RTO`);

                    $('#id').val(data.rto_id);

                    $('#showdata').html(data);
                    popupShown = true;
                }
            });
        });
        $(document).ready(function() {
            $('[name="response_log_length"]').attr({
                "data-style": "btn-sm btn-primary",
                "data-actions-box": "true",
                "class": "selectpicker w-100 px-3",
                "data-live-search": "true"
            });
            $('.selectpicker').selectpicker();
        })
    </script>
@endsection('scripts')
