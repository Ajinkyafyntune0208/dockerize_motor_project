@extends('admin_lte.layout.app', ['activePage' => 'Bucket List', 'titlePage' => __('Bucket List')])

@section('content')
<style>
    .wrap-td-content {
        word-wrap: break-word !important;
        white-space: normal !important;
        max-width: 200px;
    }
</style>
<div class="card card-primary">
    <div class="card-body">
        <div class="row justify-content-end">
            <div class="col-auto">
                @if (auth()->user()->can('buckets.create'))
                <div class="form-group">
                    <a class="btn btn-primary" style="margin-top: 20px;" href="{{ route('admin.ic-configuration.buckets.create') }}">+ Create Bucket</i></a>
                </div>
                @endif
            </div>
            <div class="col-lg-12 grid-margin stretch-card">
                <div class="table-responsive">
                    <table class="table-striped table border expression-table table-hover">
                        <thead>
                            <tr>
                                <th scope="col">Sr. No</th>
                                <th scope="col">Bucket Name</th>
                                <th scope="col">Discount</th>
                                <th scope="col">Created At</th>
                                <th scope="col">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($bucketList as $item)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{$item->bucket_name}}</td>
                                    <td>{{$item->discount}}</td>
                                    <td>{{$item->created_at}}</td>
                                    <td>
                                        <div class="btn-group">
                                            @if (auth()->user()->can('buckets.show'))
                                            <button class="btn btn-sm btn-outline-primary" data-href="{{route('admin.ic-configuration.buckets.view', ['id' => $item->id])}}" onclick="viewFormula(this)"
                                                style="padding-left: 6px; padding-right: 10px;"><i
                                                    class="fa fa-eye"></i>
                                                </button>
                                            @endif
                                            @if (auth()->user()->can('buckets.edit'))
                                            <a class="btn btn-sm btn-success" href="{{route('admin.ic-configuration.buckets.edit', ['id'=> $item->id])}}"
                                                style="padding-right: 6px; padding-left: 10px;"><i
                                                    class="fa fa-edit"></i></a>
                                            @endif
                                            @if (auth()->user()->can('buckets.delete'))
                                            <button class="btn btn-sm btn-outline-danger" onclick="deleteConfig({{$item->id}})"
                                                style="padding-left: 6px; padding-right: 10px;"><i
                                                    class="fa fa-trash"></i></button>
                                            @endif
                                            
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                            <tfoot>
                                <td colspan="3">
                                    {{$bucketList->links()}}
                                </td>
                            </tfoot>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="viewModal" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
    <div class="modal-dialog" style="max-width:80vw">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalLabel">View Bucket List</h5>
                <button type="button" onclick="closeModal()" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <table class="table view-table" style="border: none">
                    <tbody>
                        
                    </tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal" onclick="closeModal()">Close</button>
            </div>
        </div>
    </div>
</div>
<!--  -->

<div class="d-none">
    <form action="" method="post" class="deleteForm">
        @method('DELETE')
        @csrf
        <input type="hidden" name="deleteId" value="">
    </form>
</div>
@endsection

@section('scripts')
<script>

    function deleteConfig(id)
    {
        if (confirm('Are you sure..?')) {
            document.querySelector('[name="deleteId"]').value = id;
            document.querySelector('.deleteForm').submit();
        }
    }

    function viewFormula(e) {

        document.querySelector('.view-table tbody').innerHtML = '';
        getMethod(e.getAttribute('data-href')).then((res) => {
            let data = res.response;
            if(data.status === true) {
                let result = data.data;
                let tbody = `
                        <tr>
                            <th>Bucket Name</th>
                            <td>:</td>
                            <td>${result.bucket.bucket_name}</td>
                        </tr>
                        <tr>
                            <th>Discount</th>
                            <td>:</td>
                            <td>${result.bucket.discount}</td>
                        </tr>
                        <tr>
                            <th>Mandatory Addons</th>
                            <td>:</td>
                            <td class="wrap-td-content">${result.mandatoryAddonsSelected}</td>
                        </tr>
                         <tr>
                            <th>Optional Addons</th>
                            <td>:</td>
                            <td class="wrap-td-content">${result.optionalAddonsSelected}</td>
                        </tr>
                        <tr>
                            <th>Excluded Addons</th>
                            <td>:</td>
                            <td class="wrap-td-content">${result.excludedAddonsSelected}</td>
                        </tr>
                `;
                document.querySelector('.view-table tbody').innerHTML = tbody
                $('#viewModal').modal('show');
            }
        });
    }

    function closeModal(e) {
        $('#viewModal').modal('hide');
    }
    $(document).ready(function() {
        $('.expression-table').DataTable({
            paging: false,
            info: false
        });
    });


    async function getMethod(url) {
        const response = await fetch(url);
        response.response = await response.json();
        return response;
    }
</script>
@endsection