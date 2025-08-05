@extends('admin_lte.layout.app', ['activePage' => 'admin user', 'titlePage' => __('Admin User')])
@section('content')
<!-- general form elements disabled -->
<style>
      select.form-control {
            display: inline;
            width: 160px;
        }
        #myTable_filter {
            margin-bottom: 0.5%;
        }
    </style>
<a  href="{{ route('admin.role.index') }}" class="btn btn-dark mb-4"><i class="fa fa-solid fa-arrow-left"></i></i></a>
<div class="card card-primary">
    <div class="card-header">
    <h3 class="card-title">Roles List</h3>
    </div>
    <!-- /.card-header -->
    <div class="card-body">
        <form action="{{ route('admin.role.store') }}" method="POST">@csrf
            <div class="form-group row">
                <label for="name" class="col-sm-2 col-form-label required">Role Name</label>
                <div class="col-sm-4">
                    <input type="text" name="name" class="form-control" id="name" placeholder="Role Name" value="{{ old('name') }}" required>
                    @error('name')<span class="text-danger">{{ $message }}</span>@enderror
                </div>
            </div>
            <br>
            <h5 class="mb-3">Permissions:</h5>
            <hr>
            <div class="d-flex w-100">
                <div class="d-flex ml-auto text-right">
                    <div class="col-sm-12 col-md-6" style="margin-bottom: 3%;">
                        <select id="officeFilter" class="form-control">
                            <option value="">All</option>
                            <option value="selected">selected</option>
                            <option value="unselected">unselected</option>
                        </select>
                    </div>
                </div>
            </div>
            <table id="myTable" class="table table-bordered table-hover">
                <thead>
                    <th>Module Name</th>
                    <th>Select All</th>
                    <th>List</th>
                    <th>Show</th>
                    <th>Create</th>
                    <th>Edit</th>
                    <th>Delete</th>
                </thead>
                <tbody>
            @foreach($menu as $value)
            <div class="row">
                <tr>
                <td>
                <div class="col-md-7">
                    <label class="form-label">{{$value->menu_name}}</label>
                </div>
                </td>
                <div class="col-md-2">
                    <td>
                    <div class="form-group">
                        <div class="form-check form-check-success">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input select-all" {{ $value->status == 'N' ? 'disabled' : '' }}>
                            </label>
                        </div>
                    </div>
                </td>
                </div>
                <div class="col-md-4">
                    <td>
                    <div class="form-group">
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="{{$value->menu_slug}}.list" {{ $value->status == 'N' ? 'disabled' : '' }}>
                            </label>
                        </div>
                    </div>
                </td>
                        <td>
                    <div class="form-group">
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="{{$value->menu_slug}}.show" {{ $value->status == 'N' ? 'disabled' : '' }}>
                            </label>
                        </div>
                    </div>
                </td>
                <td>
                    <div class="form-group">
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="{{$value->menu_slug}}.create" {{ $value->status == 'N' ? 'disabled' : '' }}>
                            </label>
                        </div>
                    </div>
                </td>
                <td>
                    <div class="form-group">
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="{{$value->menu_slug}}.edit" {{ $value->status == 'N' ? 'disabled' : '' }}>
                            </label>
                        </div>
                    </div>
                </td>
                <td>
                    <div class="form-group">
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="{{$value->menu_slug}}.delete" {{ $value->status == 'N' ? 'disabled' : '' }}>
                            </label>
                        </div>
                    </div>
                </td>
                </div>
            </tr>
            </div>
            @endforeach
            </tbody>
            </table>
            <button type="submit" class="btn btn-primary mb-2">Submit</button>
        </form>
    </div>
</div>
@endsection('content')
@section('scripts')
<script>
    $(document).ready(function() {
        $('.select-all').click(function() {
            if (!$(this).prop('disabled')) {
            if ($(this).prop('checked')) {
                $(this).parent().parent().parent().parent().parent().find('.check-all').prop('checked', true);
            } else {
                $(this).parent().parent().parent().parent().parent().find('.check-all').prop('checked', false);
            }
            }
        });
        $('.check-all').click(function() {
                var totalCheckboxes = $(this).closest('tr').find('.check-all').length;
                var checkedCheckboxes = $(this).closest('tr').find('.check-all:checked').length;
                if (checkedCheckboxes === totalCheckboxes) {
                    $(this).closest('tr').find('.select-all').prop('checked', true);
                } else {
                    $(this).closest('tr').find('.select-all').prop('checked', false);
                }
            });
        var tables = $('#myTable').DataTable({
                "paging": false,
                "searching": true,
                "info": true,
                "lengthChange": true,
                "sorting": false,
            });
            $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
                var filterValue = $('#officeFilter').val();
                var isChecked = $(tables.row(dataIndex).node()).find('.check-all').is(':checked');
                if (filterValue === "selected" && isChecked) {
                    return true;
                } else if (filterValue === "unselected" && !isChecked) {
                    return true;
                } else if (filterValue === "") {
                    return true;
                }
                return false;
            });
            $('#officeFilter').change(function() {
                tables.draw();
            });
    });
</script>
@endsection
