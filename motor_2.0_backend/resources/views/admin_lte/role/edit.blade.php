@extends('admin_lte.layout.app', ['activePage' => 'admin user', 'titlePage' => __('Admin User')])
@section('content')
<!-- general form elements disabled -->
<a href="{{ route('admin.role.index') }}" class="btn btn-dark mb-4"><i class="fa fa-solid fa-arrow-left"></i></i></a>
<style>
.addButtonPermission {
    background-color: #007bff;
    border-color: #005f6b;
    float: right;
    margin-top: -6px;
}
.modal-dialog.modal-extra-small {
    max-width: 400px;
    margin: 1.75rem auto;
}
.modal-body {
    max-height: 300px;
    overflow-y: auto;
}
.permissionModel{
    margin-right: 22px;
}
.dataTables_filter {
    margin-bottom: -2px !important;
}
select.form-control {
            display: inline;
            width: 160px;
        }
/* #addButtonQuick{
    display: none;
} */
</style>
<div class="card card-primary">
    <div class="card-header">
    <h3 class="card-title">Roles List</h3>
    </div>
    <!-- /.card-header -->
    <div class="card-body">
        <form action="{{ route('admin.role.update', $role) }}" method="POST">@csrf @method('PUT')

            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            <div class="form-group row">
                <label for="name" class="col-sm-2 col-form-label required">Role Name</label>
                <div class="col-sm-4">
                    <input type="text" name="name" class="form-control" id="name" placeholder="Role Name" value="{{ old('name', $role->name) }}" required>
                    @error('name')<span class="text-danger">{{ $message }}</span>@enderror
                </div>
            </div>
            <br>
            @can('role.edit')
            <h5 class="mb-3">Permissions :&nbsp;<button type="button" class="btn btn-primary addButtonPermission" id="addButtonQuick" role_id="{{$role->id}}">+ Add Custom Quick Links</button>
            @endcan
            <button type="button" class="btn btn-primary addButtonPermission permissionModel" id="addButton" role_id="{{$role->id}}"> + Add Additional Permissions</button>
            </h5>
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
            <table id="myTables" class="table table-bordered table-hover">
                <thead>
                    <th>Module Name</th>
                    <th>Select All</th>
                    <th>List</th>
                    <th>Lhow</th>
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
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="{{$value->menu_slug}}.list" {{ in_array($value->menu_slug.'.list', $permissions) ? 'checked' : '' }} {{ $value->status == 'N' ? 'disabled' : '' }}>
                            </label>
                        </div>
                    </div>
                    </td>
                    <td>
                    <div class="form-group">
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="{{ $value->menu_slug }}.show" {{ in_array($value->menu_slug.'.show', $permissions) ? 'checked' : '' }} {{ $value->status == 'N' ? 'disabled' : '' }}>
                            </label>
                        </div>
                    </div>
                    </td>
                    <td>
                    <div class="form-group">
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="{{ $value->menu_slug }}.create" {{ in_array($value->menu_slug.'.create', $permissions) ? 'checked' : '' }} {{ $value->status == 'N' ? 'disabled' : '' }}>
                            </label>
                        </div>
                    </div>
                </td>
                <td>
                    <div class="form-group">
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="{{ $value->menu_slug }}.edit" {{ in_array($value->menu_slug.'.edit', $permissions) ? 'checked' : '' }} {{ $value->status == 'N' ? 'disabled' : '' }}>
                            </label>
                        </div>
                    </div>
                </td>
                <td>
                    <div class="form-group">
                        <div class="form-check">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input check-all" name="permission[]" value="{{ $value->menu_slug }}.delete" {{ in_array($value->menu_slug.'.delete', $permissions) ? 'checked' : '' }} {{ $value->status == 'N' ? 'disabled' : '' }}>
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
            @foreach($additional_permissions as $permission)
            <input type="hidden" name="additionalPermission[]" value="{{ $permission->permission_name }}" checked>
            @endforeach
            <button type="submit" class="btn btn-primary mb-2" id="submitButton">Submit</button>
        </form>
    </div>

    <div class="modal fade" id="addModal" tabindex="-1" role="dialog" aria-labelledby="addModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-extra-small" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addModalLabel">Add Permission</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addForm">
                    <div class="col-sm-4">
                        <input type="hidden" name="name" class="form-control" id="name" value="{{ old('name', $role->name ?? '') }}" required>
                        @error('name')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                    <div>
                        <input type="hidden" name="role_id" value="{{$role->id}}">
                    </div>
                    @if(auth()->user()->email == "motor@fyntune.com")
                    <div class="form-group mt-3">
                        <label for="additionalInfo">Custom Permission:</label>
                        <textarea class="form-control" id="additionalInfo" name="additional_info" rows="3" placeholder="Add any Custom Permission here..."></textarea>
                    </div>
                    @endif
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal" id="closeModel">Close</button>
                <button type="submit" class="btn btn-primary" id="saveBtn">Save</button>
            </div>
        </div>
    </div>
</div>

{{-- quick link model start --}}
<div class="modal fade" id="addModalQuick" tabindex="-1" role="dialog" aria-labelledby="addModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-extra-small" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addModalLabel">Custom Quick Link</h5>
            </div>
            <div class="modal-body">
                <form id="addFormCheck">
                    <div>
                        <input type="hidden" name="role_id" value="{{ $role->id }}" class="form-control" placeholder="Role ID">
                    </div>

                    <div class="form-group row">
                        <label class="required ml-2 mt-2">Status</label>
                        <div class="custom-control custom-switch custom-switch-off-danger custom-switch-on-success">
                            <input type="checkbox" name="authorization_status" class="custom-control-input" id="authorization_status"
                                value="on" {{ $quicklink->authorization_status ?? null == 'on' ? 'checked' : '' }}>
                            <label class="custom-control-label ml-5 mt-2" for="authorization_status"></label>
                        </div>
                        <span id="authorization_status_label" class="ml-3 mt-2">
                            {{ $quicklink->authorization_status ?? null == 'on' ? 'Active' : 'Inactive' }}
                        </span>
                        @error('authorization_status')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>
                    <div id="dynamicFormData"></div>

                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal" id="closeModalQuick">Close</button>
                <button type="submit" class="btn btn-primary" id="saveBtnQuick">Save</button>
            </div>
        </div>
    </div>
</div>
</div>
{{-- quick link model end--}}
</div>
@endsection('content')
@section('scripts')
<script>
    $(document).ready(function() {
    $('.select-all').each(function() {
        var allChecked = true;
        $(this).parent().parent().parent().parent().parent().find('.check-all').each(function() {
            if (!$(this).prop('checked')) {
                allChecked = false;
                return false;
            }
        });
        $(this).prop('checked', allChecked);
    });

        $('.select-all').click(function() {
            if ($(this).prop('checked')) {
                $(this).parent().parent().parent().parent().parent().find('.check-all').prop('checked', true);
            } else {
                $(this).parent().parent().parent().parent().parent().find('.check-all').prop('checked', false);
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
        var tables = $('#myTables').DataTable({
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

    $('#addButton').click(function() {
    var roleId = $(this).attr('role_id');
    $('#addModal').modal('show');
    $('#additionalInfo').val('');
    $('#addForm').find('.form-group').not(':first').remove();

            const fetchPermissionsUrl = "{{ route('admin.permission') }}";
            $.ajax({
            url: fetchPermissionsUrl,
            data: {'id' : roleId},
            type: 'POST',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.status) {
                    let formData = '';

                    $.each(response.data, function(index, permission) {
                    const isChecked = permission.status == 1 ? 'checked' : '';  // Check if permission is already assigned
                    formData += `
                        <div class="col-md-12">
                            <div class="form-group">
                                <div class="form-check">
                                    <label class="form-check-label">
                                        <input type="checkbox" class="form-check-input" name="permission[]" value="${permission.permission_name}" ${isChecked}> ${permission.permission_name}
                                    </label>
                                </div>
                            </div>
                        </div>
                    `;
                });

                    $('#addForm').append(formData);
                } else {
                    alert('Failed to load data.');
                }
            },
            error: function(xhr) {
                console.log(xhr.responseText);
            }
        });
    });

            $('#saveBtn').click(function(e) {
            e.preventDefault();
            $('#saveBtn').prop('disabled', true);
            $('#closeModel').prop('disabled', true);

            const savePermission = "{{ route('admin.save-permission') }}";
            $.ajax({
                url: savePermission,
                type: 'POST',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                data: $('#addForm').serialize(),
                success: function(response) {
                    if (response.status) {
                        if(response.custom_status){
                            alert(response.custom_message);
                            $('#addModal').modal('hide');
                            window.location.reload();
                            return '';
                        }
                        alert(response.message);
                        $('#addModal').modal('hide');
                        window.location.reload();
                    } else {
                        alert('Failed to save permission');
                    }
                },
                error: function(xhr) {
                    console.log(xhr.responseText);
                    $('#saveBtn').prop('disabled', false);
                    $('#closeModel').prop('disabled', false);
                }
            });
        });
        $('#closeModel').click(function(e) {
            e.preventDefault();
            $('#addModal').modal('hide');

        });

        //Quick link part -1 ------------------------------------>
        function fetchFormData(roleId) {
        const fetchPermissionsUrl = "{{ route('admin.quick-link') }}";
        $.ajax({
            url: fetchPermissionsUrl,
            type: 'POST',
            data: { 'id': roleId },
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.status) {
                    let formData = '';
                    const maxSelections = 12;
                    let selectedCount = 0;

                    $.each(response.data, function(index, permission) {
                        const isChecked = permission.status === 1 ? 'checked' : '';
                        if (isChecked) {
                            selectedCount++;   // Increment count if permission is already checked
                        }
                        formData += `
                            <div class="col-md-12">
                                <div class="form-group">
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input permission-checkbox" name="permission[]" value="${permission.menu_id},${permission.permission_id}," ${isChecked}> ${permission.menu_name}
                                        </label>
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                    $('#dynamicFormData').html(formData);
                    if (selectedCount >= maxSelections) {
                        $('.permission-checkbox:not(:checked)').prop('disabled', true);
                    }
                    $('.permission-checkbox').on('change', function() {
                        if (this.checked) {
                            selectedCount++;
                        } else {
                            selectedCount--;
                        }
                        if (selectedCount >= maxSelections) {
                            $('.permission-checkbox:not(:checked)').prop('disabled', true);
                        } else {
                            $('.permission-checkbox:not(:checked)').prop('disabled', false);
                        }
                    });
                } else {
                    alert('Failed to Fetch User Permissions.');
                }
            },
            error: function(xhr) {
                console.log('Error:', xhr.responseText);
                alert('An error occurred while Fetching permissions');
            }
});

    }
    $('#addButtonQuick').click(function() {
        $('#addModalQuick').modal('show');
        const roleId = $(this).attr('role_id');
        $('#dynamicFormData').html('');

        if ($('#authorization_status').is(':checked')) {
            fetchFormData(roleId);
        }
    });

    $('#authorization_status').change(function() {
        const roleId = $('#addButtonQuick').attr('role_id');
        if ($(this).is(':checked')) {
            fetchFormData(roleId);
        } else {
            $('#dynamicFormData').html('');
        }
    });

    // saving quick link data----------------------->
    $('#saveBtnQuick').click(function(e) {
            e.preventDefault();
            $('#saveBtnQuick').prop('disabled', true);
            $('#closeModalQuick').prop('disabled', true);
            const saveUrl = "{{ route('admin.save-quick-link') }}";
            var formData = $('#addFormCheck').serialize();
            $.ajax({
                url: saveUrl,
                type: 'POST',
                data: formData,
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    if(response.status) {
                        alert(response.message);
                        $('#addModalQuick').modal('hide');
                        window.location.reload();
                    }
                    else{
                        alert("Error in Storing Link")
                    }
                },
                error: function(xhr) {
                    console.log('Error:', xhr.responseText);
                    alert('An error occurred while saving the quick link');
                    $('#saveBtnQuick').prop('disabled', true);
                    $('#closeModalQuick').prop('disabled', true);
                }
            });
    });
});

        document.addEventListener('DOMContentLoaded', function () {
        const authorizationStatusCheckbox = document.getElementById('authorization_status');
        const authorizationStatusLabel = document.getElementById('authorization_status_label');
        function updateLabel() {
            if (authorizationStatusCheckbox.checked) {
                authorizationStatusLabel.textContent = 'Active';
            } else {
                authorizationStatusLabel.textContent = 'InActive';
            }
        }
        updateLabel();
        authorizationStatusCheckbox.addEventListener('change', updateLabel);
        });
        $('#closeModalQuick').click(function(e) {
            e.preventDefault();
            $('#addModalQuick').modal('hide');
    });
</script>
<script>

    $(document).ready(function() {
    $('#submitButton').click(function() {
        $(this).prop('disabled', true);
        $(this).closest('form').submit();
    });
});

</script>
@endsection
