@extends('admin_lte.layout.app', ['activePage' => 'payment_response', 'titlePage' => __('Payment Response')])
<style>
    @media (min-width: 576px){
        .modal-dialog {
            max-width: 911px;
            margin: 34px auto;
            word-wrap: break-word;
        }
    }
</style>
@section('content')
<div class="card">
    <div class="card-body">
        <table id="data-table" class="table table-bordered table-hover">
            <thead>
                <tr>
                    <th>Sr. No.</th>
                    <th>Company Alias</th>
                    <th>Section</th>
                    <th>View Response</th>
                    <th>Created At</th>
                    <th>Updated At</th>
                </tr>
            </thead>
            <tbody>
                @foreach($payment as $key => $value)
                <tr>
                    <td>{{++$key}}</td>
                    <td>{{$value->company_alias}}</td>
                    <td>{{$value->section}}</td>
                    <td style="text-align:center">
                    @can("payment_response.show")
                    <a class="btn btn-info ml-1 view" href="#" data-toggle="modal" data-target="#exampleModal" data="{{ $value->response }}" ><i class="fa fa-eye"></i></a>
                    @endcan
                    </td>
                    <td>{{ $value->created_at }}</td>
                    <td>{{ $value->updated_at }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="exampleModal" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">Complete Response <span></span></h5>
                <button type="button" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
                <div class="modal-body">
                    <div class="form-group">
                        <h2>Response</h2>
                        <span id="showdata"></span>
                    </div>
                </div>
                <div class="modal-footer">
                    <!-- <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button> -->
                </div>

        </div>
    </div>
</div>
@endsection
@section('scripts')
<script>
    $(document).on('click', '.view', function () {
        var data = $(this).attr('data');
        $('#showdata').html(data);
    });

    $(document).ready(function () {
    var table = $('#data-table').DataTable({
        initComplete: function () {
        var api = this.api();
        var header = $('#data-table thead');

        var selectRow = $('<tr></tr>').appendTo(header);

        api.columns().every(function (index) {
            var column = this;
            var th = $('<th></th>').appendTo(selectRow);

            if (index === 1 || index === 2) {
            var select = $('<select class="form-control"><option>Select</option></select>')
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
    });
    });
</script>
@endsection
