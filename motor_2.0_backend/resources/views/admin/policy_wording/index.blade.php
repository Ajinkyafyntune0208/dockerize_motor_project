@extends('admin.layout.app')

@section('content')
<main class="container-fluid">
    <section class="mb-4">
        <div class="card">
            <div class="card-body">
                @if (session('status'))
                <div class="alert alert-{{ session('class') }}">
                    {{ session('status') }}
                </div>
                @endif
                @if(!empty($master_policies))
                <div class="table-responsive">
                    <table class="table table-striped" id="policy_wording_table">
                        <thead>
                            <tr>
                                <th scope="col">Policy ID</th>
                                <th scope="col">Comany Name</th>
                                <th scope="col">Product Name</th>
                                <th scope="col" class="text-right"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($master_policies as $key => $master_policy)
                            <tr>
                                <td scope="col">{{ $master_policy->policy_id }}</td>
                                <td scope="col">{{ $master_policy->company_name }}</td>
                                <td scope="col">{{ $master_policy->master_product['product_name'] ?? '' }}</td>
                                <td scope="col" class="text-right">
                                    <form action="{{ route('admin.policy-wording.destroy', $master_policy) }}" method="post">@csrf @method('DELETE')
                                    <div class="btn-group">
                                        @if(Storage::exists('policy_wordings/' . $master_policy->policy_id . '.pdf'))
                                        <a href="{{ Storage::url('policy_wordings/' . $master_policy->policy_id . '.pdf') }}" class="btn btn-sm btn-info" target="_blank"><i class="fa fa-eye"></i></a>
                                        @endif
                                        <button type="button" class="btn btn-success btn-sm change-policy-wording" data="{{ $master_policy->policy_id }}" data-toggle="modal" data-target="#exampleModal"><i class="fa fa-edit"></i></button>
                                        @if(Storage::exists('policy_wordings/' . $master_policy->policy_id . '.pdf'))
                                        <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you wants to delete Policy Wording PDF..?')"><i class="fa fa-trash"></i></button>
                                        @endif
                                        <!--  -->
                                    </div>
                                    </form>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </div>
        </div>
    </section>
</main>

<!-- Modal -->
<div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">Policy Wording - <span></span></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="{{ route('admin.policy-wording.store') }}" enctype="multipart/form-data" method="post">@csrf
                <div class="modal-body">
                    <div class="form-group">
                        <label class="btn btn-primary btn-sm mb-0"></i><input type="file" name="policy_wording" required accept="application/pdf"></label>
                        <input type="text" hidden name="policy_id" value="">
                    </div>
                </div>
                <div class="modal-footer">
                    <!-- <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button> -->
                    <button type="submit" class="btn btn-primary">Submit</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        $('#policy_wording_table').DataTable();
        $(document).on('click', '.change-policy-wording', function () {
            $('#exampleModalLabel span').text($(this).attr('data'));
            $('input[name=policy_id]').val($(this).attr('data'));
        });

    });
</script>
@endpush