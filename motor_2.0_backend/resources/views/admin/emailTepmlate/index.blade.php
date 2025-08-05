@extends('admin.layout.app')

@section('content')
<main class="container-fluid">
    <div class="card">
        <div class="card-header">
            <h5 class="card-title">SMS Email Template</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-12 text-right">
                    <a href="{{ route('admin.email-sms-template.create') }}" class="btn btn-sm btn-primary"><i class="fa fa-plus-circle"></i> Add</a>
                </div>
            </div>
            @if (session('status'))
            <div class="alert alert-{{ session('class') }}">
                {{ session('status') }}
            </div>
            @endif
            <table class="table table-bordered mt-3">
                <thead>
                    <tr>
                        <th scope="col">Sr. No.</th>
                        <th scope="col">Name</th>
                        <th scope="col">Type</th>
                        <th scope="col">Subject</th>
                        <th scope="col">Status</th>
                        <th scope="col"></th>
                    </tr>
                </thead>
                <tbody>
                    <!-- @ dd($email_sms_templates) -->
                    @foreach($email_sms_templates as $key => $email_sms_template)
                    <tr>
                        <th scope="col">{{ $key + 1 }}</th>
                        <td scope="col">{{ $email_sms_template->email_sms_name }}</td>
                        <td scope="col">{{ $email_sms_template->type }}</td>
                        <td scope="col">{{ $email_sms_template->subject }}</td>
                        <td scope="col"><a href="#" class="{{ $email_sms_template->status == 'inactive' ? 'badge badge-danger' : 'badge badge-success' }} show-template" data="{{ route('admin.email-sms-template.update', $email_sms_template) }}" data-toggle="modal" data-target="#exampleModal">{{ $email_sms_template->status }}</a></td>
                        <td scope="col" class="text-right">
                            <form action="{{ route('admin.email-sms-template.destroy', $email_sms_template) }}" method="post" onsubmit="return confirm('Are you sure..?')"> @csrf @method('DELETE')
                                <div class="btn-group">
                                    <a href="{{ route('admin.email-sms-template.show', $email_sms_template) }}" class="btn btn-sm btn-outline-info"><i class="fa fa-eye"></i></a>
                                    <a href="{{ route('admin.email-sms-template.edit', $email_sms_template) }}" class="btn btn-sm btn-outline-success"><i class="fa fa-edit"></i></a>
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fa fa-trash"></i></button>
                                </div>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</main>

<!--  -->
<!-- Modal -->
<div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form action="" method="post" id="update-status">@csrf @method('PUT')
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">Status</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <select name="status" class="form-control">
                            <option value="">Select</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </div>
        </form>
    </div>
</div>
<!--  -->
@endsection
@push('scripts')
<script>
    $(document).ready(function() {
        $('.show-template').click(function() {
            console.log($(this).attr('data'));
            $('#update-status').attr('action', $(this).attr('data'));
            var html = '<option selected>' + $(this).text() + '</option>';
            $('#update-status select').prepend(html);
        });

        // $.ajax({
        //     url: 
        // })
    });
</script>
@endpush