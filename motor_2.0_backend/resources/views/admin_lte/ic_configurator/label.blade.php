@extends('admin_lte.layout.app', ['activePage' => 'ic_configurator', 'titlePage' => __('Label And Attribute')])
@section('content')

<link rel="stylesheet" type="text/css" href="{{ asset('css/premcalclabel.css') }}">



<section>
    <div class="content">
        @if (session('status'))
        <div class="alert alert-{{ session('class') }}">
            {{ session('status') }}
        </div>
        @endif
        <div class="row mb-3">

            <div class="d-flex justify-content-end w-100 ">
                <!-- <form action="" method="POST" class="d-flex">
                    @csrf
                    <input type="text" name="search" placeholder="Search..." class="form-control mr-2" id="search" value="{{ old('search-data', request()->search) }}">

                    <button type="submit" class="btn btn-info">Search</button>
                </form> -->
            </div>

            <!-- model start -->
            <div class="modal fade" id="exampleModal" name="exampleModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-body">
                            <div class="container mt-5">
                                <div class="card">
                                    <div class="card-header bg-dark text-white">
                                        Label
                                    </div>
                                    <form action="#" id="create-label">
                                        <div class="card-body">
                                            <div class="form-group">
                                                <label for="labelInput" class="required_field">Label</label>
                                                <input type="text" class="form-control" id="labelInput" placeholder="Free text" required>
                                            </div>
                                            <div class="form-group">
                                                <label for="labelInput" class="required_field">Key</label>
                                                <input type="text" class="form-control" id="keyInput" placeholder="Free text" required>
                                            </div>
                                            <div class="form-group">
                                                <label for="selectIC" class="required_field" >Label Group</label>
                                                <select class="form-control" id="group_by" required name="group_by">
                                                    <option value="">Select</option>
                                                    <option value="Own Damage">Own Damage</option>
                                                    <option value="Liablity">Liablity</option>
                                                    <option value="CPA">CPA</option>
                                                    <option value="Addons">Addons</option>
                                                    <option value="Accessories">Accessories</option>
                                                    <option value="Additional Covers">Additional Covers</option>
                                                    <option value="IMT">IMT</option>
                                                    <option value="Discounts">Discounts</option>
                                                    <option value="Deductibles">Deductibles</option>
                                                    <option value="Others">Others</option>
                                                </select>
                                            </div>
                                            <button class="btn btn-primary" id="saveButton" type="submit">Save</button>
                                            <!-- <button class="btn btn-secondary" id="resetButton">Reset</button> -->
                                        </div>
                                    </form>
                                    <button class="btn btn-secondary" id="resetButton">Reset</button>

                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- model end -->




            <!-- edit model -->
            <div class="modal fade" id="editModal" name="editModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-body">
                            <div class="container mt-5">
                                <div class="card">
                                    <div class="card-header bg-dark text-white">
                                        Edit Label
                                    </div>
                                    <form action="#" id="Editcreate-form">
                                        <div class="card-body">
                                            <div class="form-group">
                                                <label for="EditlabelInput" class="required_field"> Label</label>
                                                <input type="text" class="form-control" id="EditlabelInput" name="EditlabelInput" placeholder="Free text" required>
                                            </div>
                                            <div class="form-group">
                                                <label for="showKey" id="showKey"></label>
                                            </div>
                                            <input type="hidden" name="label_id" id="label_id" required>
                                            <div class="form-group">
                                                <label for="selectIC" class="required_field">Label Group</label>
                                                <select class="form-control" id="group_by_edit" name="group_by_edit" required>
                                                    <option value="">Select</option>
                                                    <option value="Own Damage">Own Damage</option>
                                                    <option value="Liablity">Liablity</option>
                                                    <option value="CPA">CPA</option>
                                                    <option value="Addons">Addons</option>
                                                    <option value="Accessories">Accessories</option>
                                                    <option value="Additional Covers">Additional Covers</option>
                                                    <option value="IMT">IMT</option>
                                                    <option value="Discounts">Discounts</option>
                                                    <option value="Deductibles">Deductibles</option>
                                                    <option value="Others">Others</option>
                                                </select>
                                            </div>
                                            <button class="btn btn-primary" type="submit"> UPDATE </button>
                                            <!-- <button class="btn btn-secondary" id="resetButton"> Re-set </button> -->
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- end edit model -->

        </div>
        <div class="table-container"  id="table-label-list">
            <div class="d-flex flex-row-reverse mb-2">
                <button type="button" class="btn btn-primary m-2" data-toggle="modal" data-target="#exampleModal">
                    <i class=" fa fa-sharp fa-solid fa-plus"></i> Add Label
                </button>
            </div>
            <div class="clearfix"></div>
        @if(!empty($count))
            <table class="table table-bordered table-striped show-data-label">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Label</th>
                        <th>Label Key</th>

                        <th>Label Group</th>
                        <th>Mapping Count</th>
                        <th>Map</th>

                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>

                    @foreach($count as $key => $count)
                    <tr>
                        <td>{{$key + 1}}</td>
                        <td>{{$count['label_name']}}</td>
                        <td>{{$count['label_key']}}</td>

                        <td>{{$count['label_group']}}</td>

                        <td>{{$count['mapping_count']}}</td>
                        <td>
                            <a href="{{ route('admin.ic-configuration.map-attributes', ['id'=>Crypt::encryptString($count['id'])]) }}"><i class=" fa fa-sharp fa-solid fa-plus"></i> MAP</a>
                        </td>
                        <td>
                            @if (auth()->user()->can('label_and_attribute.show'))
                            <button type="button" class="btn btn-outline-primary m-1 view-btn view-btn-data" label="{{$count['label_name'] }}" groupby="{{$count['label_group'] }}" key="{{$count['label_key'] }}" count="{{$count['mapping_count']}}" company="{{$count['ic_alias']}}" vehicle_type="{{$count['segment']}}" business_type=" {{$count['business_type']}}" attribute="{{$count['attribute_name']}}">
                                <a href="{{ route('admin.ic-configuration.view-label', ['id'=>Crypt::encryptString($count['id'])]) }}"><i class="fa fa-eye" aria-hidden="true"></i></a> </button>
                            @endif

                            @if (auth()->user()->can('label_and_attribute.edit'))
                            <button type="button" class="btn btn-success m-1 edit-btn" data-toggle="modal" data-target="#editModal" label="{{$count['label_name'] }}" groupby="{{$count['label_group'] }}" id="{{$count['id'] }}" key="{{$count['label_key'] }}">
                                <i class="fas fa-edit"></i>
                            </button>
                            @endif                         

                            @if (auth()->user()->can('label_and_attribute.delete'))
                            <button type="" class="btn btn-outline-danger delete-btn m-1" id="{{$count['id'] }}" label="{{$count['label_name'] }}"><i class=" fa fa-regular fa-trash"></i></button>
                            @endif           
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Label</th>
                        <th>Label Key</th>
                        <th>Label Group</th>
                        <th>Mapping Count</th>
                        <th>Map</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="7">No record found</td>
                    </tr>
                </tbody>
            </table>
        @endif
        </div>
    </div>


</section>
@endsection
@section('scripts')
<script>
    const saveLabel = "{{ route('admin.ic-configuration.save-label') }}";
    const editLabel = "{{ route('admin.ic-configuration.edit-label') }}";
    const deleteLabel =  "{{ route('admin.ic-configuration.delete-label') }}";
    const homePage = "{{ route('admin.ic-configuration.label-attributes') }}"
</script>
<script src="{{asset('admin1/js/ic-config/label.js')}}"></script>
@endsection