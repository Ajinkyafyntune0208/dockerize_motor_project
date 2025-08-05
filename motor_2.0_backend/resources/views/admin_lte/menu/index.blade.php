@extends('admin_lte.layout.app', ['activePage' => 'company', 'titlePage' => __('Menu Master')])
@section('content')
<div class="card">
    <div class="card-header">
        <div class="card-tools">
            @can('role.create')
            <a href="{{ route('admin.menu.create') }}">
                <button type="button" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add
                </button>
            </a>
            @endcan
        </div>
    </div>

    <div class="card-body">
    <table id="data-table" class="table table-bordered mt-3">
                <thead>
                    <tr>
                        <th scope="col">Sr. No.</th>
                        <th scope="col">Menu Name</th>
                        <th scope="col">Menu Slug</th>
                        <th scope="col">Status</th>
                        <th scope="col">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($menus as $key => $menu)
                    <tr>
                        <th scope="col">{{ $key + 1 }}</th>
                        <td scope="col">{{ $menu->menu_name }}</td>
                        <td scope="col">{{ $menu->menu_slug }}</td>
                        <td scope="col"> {{$menu->status == 'Y' ? 'Active' : 'Inactive'}} </td>
                        <td>
                            <div class="btn-group btn-group-toggle">
                                @can('menu.edit')
                                <a class="btn btn-info mr-1" href="{{url('admin/menu/edit/'.base64_encode($menu->menu_id))}}" title="edit"><i class="far fa-edit"></i></a>
                                @endcan
                            </div>
                        </td>

                    </tr>
                    @endforeach
                </tbody>
            </table>
    </div>
</div>
@endsection('content')

@section('scripts')
<script>

    $(function () {
    $("#data-table").DataTable({
      "responsive": false, "lengthChange": true, "autoWidth": false,
       scrollX: true,
       "buttons": ["copy", "csv", "excel",{
                    extend: 'pdfHtml5',
                    orientation : 'landscape',
                    pageSize : 'A2',
                },"print",  {
                    extend: 'colvis',
                    columns: 'th:nth-child(n+3)'
                },
    ]}).buttons().container().appendTo('#data-table_wrapper .col-md-6:eq(0)');
  });

</script>
@endsection('scripts')
