@extends('admin_lte.layout.app', ['activePage' => 'Formula List', 'titlePage' => __('Expression Formula List')])

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
                @if (auth()->user()->can('formulas.create'))
                <div class="form-group">
                    <a class="btn btn-primary" style="" href="{{ route('admin.ic-configuration.formula.create-formula') }}">+ Add Formula</i></a>
                </div>
                @endif
            </div>
            <div class="col-lg-12 grid-margin stretch-card">
                <div class="table-responsive">
                    <table class="table-striped table border expression-table table-hover">
                        <thead>
                            <tr>
                                <th scope="col">Sr. No</th>
                                <th scope="col">Expression Name</th>
                                <th scope="col">Created At</th>
                                <th scope="col">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($formulas as $item)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{$item->formula_name}}</td>
                                    <td>{{$item->created_at}}</td>
                                    <td>
                                        <div class="btn-group">
                                            @if (auth()->user()->can('formulas.show'))
                                            <button class="btn btn-sm btn-outline-primary" data-href="{{route('admin.ic-configuration.formula.view-formula', ['id' => $item->id])}}" onclick="viewFormula(this)"
                                                style="padding-left: 6px; padding-right: 10px;"><i
                                                    class="fa fa-eye"></i></button>
                                            @endif
                                            @if (auth()->user()->can('formulas.edit'))
                                            <a class="btn btn-sm btn-success" href="{{route('admin.ic-configuration.formula.edit-formula', ['id'=> $item->id])}}"
                                                style="padding-right: 6px; padding-left: 10px;"><i
                                                    class="fa fa-edit"></i></a>
                                            @endif
                                            @if (auth()->user()->can('formulas.delete'))
                                            <button class="btn btn-sm btn-outline-danger" onclick="deleteConfig({{$item->id}})"
                                                style="padding-left: 6px; padding-right: 10px;"><i
                                                    class="fa fa-trash"></i></button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
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
                <h5 class="modal-title" id="modalLabel">View Expression</h5>
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
        document.querySelector('body').style.cursor = 'progress'
        document.querySelector('.view-table tbody').innerHtML = '';
        getMethod(e.getAttribute('data-href')).then((res) => {
            document.querySelector('body').style.cursor = 'auto'
            let data = res.response;
            if(data.status === true) {
                let result = data.data;
                let tbody = `
                        <tr>
                            <th>Formula Name</th>
                            <td>:</td>
                            <td>${result.name}</td>
                        </tr>
                        <tr>
                            <th>Formula Matrix</th>
                            <td>:</td>
                            <td class="wrap-td-content">${result.matrix}</td>
                        </tr>
                        <tr>
                            <th>Formula</th>
                            <td>:</td>
                            <td class="wrap-td-content">${result.short_formula}</td>
                        </tr>
                        <tr>
                            <th>Full Formula</th>
                            <td>:</td>
                            <td class="wrap-td-content">${result.formula}</td>
                        </tr>
                        <tr>
                            <th>Full Formula (Front End)</th>
                            <td>:</td>
                            <td class="wrap-td-content">${result.full_formula}</td>
                        </tr>`;

                        if (result.used) {
                            tbody+=`<tr>
                            <th>Formula Used in : </th>
                            <td>:</td>
                            <td class="wrap-td-content">${result.used}</td>
                            </tr>`;
                        }

                        if (result.config) {
                            tbody +=`<tr>
                            <th>Formula Configured : </th>
                            <td>:</td>
                            <td class="wrap-td-content">
                                <table class="table table-bordered table-sm table-responsive text-xs" style="max-height:60vh;">
                                    <tr>
                                        <th>IC Alias</th>
                                        <th>Segment</th>
                                        <th>Business Type</th>
                                        <th>Integration Type</th>
                                        <th>Label</th>
                                        </tr><tbody>`
                            result.config.forEach(config => {
                                tbody+=`
                                <tr>
                                        <td>${config.ic_alias}</td>
                                        <td>${config.segment}</td>
                                        <td>${config.business_type}</td>
                                        <td>${config.integration_type}</td>
                                        <td>${config.label_key}</td>
                                        </tr>
                                `;
                            });
                            tbody+=`</tbody></table>
                            </td>
                            </tr>`;
                        }
                document.querySelector('.view-table tbody').innerHTML = tbody
                $('#viewModal').modal('show');
            }
        });
    }

    function closeModal(e) {
        $('#viewModal').modal('hide');
    }
    $(document).ready(function() {
        $('.expression-table').DataTable();
    });


    async function getMethod(url) {
        const response = await fetch(url);
        response.response = await response.json();
        return response;
    }
</script>
@endsection