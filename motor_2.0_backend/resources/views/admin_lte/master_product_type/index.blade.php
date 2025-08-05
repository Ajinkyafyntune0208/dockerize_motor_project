@extends('admin_lte.layout.app', ['activePage' => '', 'titlePage' => __('Master Product Type')]) 
@section('content')
<div class="container mt-4">
    @if ($errors->any())
        <div class="alert alert-danger mb-4">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    <form action="{{ route('admin.master_product_type.index') }}" method="POST" id="productForm">
        @csrf
        <input type="hidden" name="reset_action" id="reset_action" value="">
        <div class="card">
            <div class="card-body">
                <ul class="list-unstyled">
                    @foreach($hierarchy[0] ?? [] as $parent)
                        <li class="mb-3">
                            <div class="form-check">
                                <input
                                    class="form-check-input parent-checkbox"
                                    type="checkbox"
                                    name="product_sub_type_code[]"
                                    value="{{ $parent->product_sub_type_code }}"
                                    id="product_sub_type_{{ $parent->id }}"
                                    {{ $parent->status == 'Active' ? 'checked' : '' }}
                                    data-placement="top"
                                    title="{{ $parent->status == 'Active' ? 'Active' : 'Inactive' }}"
                                    onchange="toggleChildren(this)"
                                >
                                <label class="form-check-label font-weight-bold" for="product_sub_type_{{ $parent->id }}">
                                    {{ $parent->product_sub_type_code }}
                                </label>
                            </div>

                            @if(isset($hierarchy[$parent->product_sub_type_id]))
                                <ul class="ml-4">
                                    @foreach($hierarchy[$parent->product_sub_type_id] as $child)
                                        <li class="mb-2">
                                            <div class="form-check">
                                                <input
                                                    class="form-check-input child-checkbox"
                                                    type="checkbox"
                                                    name="product_sub_type_code[]"
                                                    value="{{ $child->product_sub_type_code }}"
                                                    id="product_sub_type_{{ $child->id }}"
                                                    {{ $child->status == 'Active' ? 'checked' : '' }}
                                                    data-toggle="tooltip"
                                                    data-placement="top"
                                                    title="{{ $child->status == 'Active' ? 'Active' : 'Inactive' }}"
                                                    {{ $parent->status != 'Active' ? 'disabled' : '' }}
                                                >
                                                <label class="form-check-label" for="product_sub_type_{{ $child->id }}">
                                                    {{ $child->product_sub_type_code }}
                                                </label>
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>

        <div class="mt-4">
            <button type="submit" class="btn btn-primary btn-lg">Submit <i class="fas fa-check-circle"></i></button>
            <button type="button" class="btn btn-warning btn-lg" id="resetBtn">Reset <i class="fas fa-sync-alt"></i></button>
            <a href="{{ url('/admin/dashboard') }}" class="btn btn-secondary btn-lg">Back <i class="fas fa-arrow-left"></i></a>
        </div>
    </form>

    <script>
        document.getElementById('resetBtn').addEventListener('click', function() {
            if(confirm("Are you sure you want to reset the inactive items?")) {
                document.getElementById('reset_action').value = "reset";
                document.getElementById('productForm').submit();
            }
        });

            function toggleChildren(parentCheckbox) {
                let parentListItem = parentCheckbox.closest('li');
                let childCheckboxes = parentListItem.querySelectorAll('.child-checkbox');
                for (let i = 0; i < childCheckboxes.length; i++) {
                    childCheckboxes[i].disabled = !parentCheckbox.checked;
                }
            }

            window.onload = function() {
                let parentCheckboxes = document.getElementsByClassName('parent-checkbox');
                for (let i = 0; i < parentCheckboxes.length; i++) {
                    let parentCheckbox = parentCheckboxes[i];
                    let parentListItem = parentCheckbox.closest('li');
                    let childCheckboxes = parentListItem.querySelectorAll('.child-checkbox');
                    for (let i = 0; i < childCheckboxes.length; i++) {
                        childCheckboxes[i].disabled = !parentCheckbox.checked;
                    }
                }
            };
    </script>
</div>
@endsection
