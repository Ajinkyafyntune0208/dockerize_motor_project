@extends('admin_lte.layout.app', ['activePage' => 'cashless_garage', 'titlePage' => __('Cashless Garage')])
@section('content')
<div class="card">
    <div class="card-header">
        <div class="card-tools">
            <a href="{{ route('admin.cashless_garage.create',['file'=>'csv_file']) }}">
                <button type="button" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Upload CSV
                </button>
            </a>
        </div>
    </div>
</div>
@endsection('content')
@section('scripts')
<script>
    $(function () {
    $("#data-table").DataTable({
        "responsive": false, "lengthChange": true, "autoWidth": false, "scrollX": true,
      "buttons": ["copy", "csv", "excel", "pdf", "print",  {
                    extend: 'colvis',
                    columns: 'th:nth-child(n+3)'
                }]
    }).buttons().container().appendTo('#data-table_wrapper .col-md-6:eq(0)');
  });

</script>
@endsection('scripts')