@extends('admin_lte.layout.app', ['activePage' => 'ic_configurator', 'titlePage' => __('IC Version Configurator')])
@section('content')

<link rel="stylesheet" href="{{asset('css/icversionconfigurator.css')}}">

<section>
    {{-- <button type="button" class="btn btn-primary m-3 buttonLabel addData" data-toggle="modal" data-target="#exampleModal">
       <a href="{{ route('admin.ic-configuration.version.add-version-data')}}">  <i class=" fa fa-sharp fa-solid fa-plus"> Add Data</i></a></button> --}}
    {{-- <button class="btn btn-primary back-btn" id="save-btn" type="submit"><a href="{{ url('admin/ic-configuration/version/ic-version-configurator') }}"><i class=" fa fa-solid fa-arrow-left"></i> BACK</a></button> --}}
    @if(!empty($listing))
    <div class="table-container" id="view-table">      
        <table class="table table-bordered table-striped version-data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Insurance Company</th>
                    <th>Integration Type</th>
                    <th>Version</th>
                    <th>Kit Type</th>
                    <th>Segment</th>
                    <th>Business</th>
                    <th>Comment</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>

                @foreach($versionData as $key => $searchData)
                <tr>
                    <td>{{$key + 1}}</td>
                    <td class="">{{$searchData['ic_alias']}}</td>
                    <td class="">{{$searchData['integration_type']}}</td>
                    <td class="">{{$searchData['version']}}</td>
                    <td class="">{{$searchData['kit_type']}}</td>
                    <td class="">{{$searchData['segment']}}</td>
                    <td class="">{{$searchData['business_type']}}</td>
                    <td class="">{{$searchData['description']}}</td>                 
                    <td class="">@if ($searchData['is_active'] == 'Active' )
                        <span class = "badge badge-success">Active</span>
                        @else
                        <span class = "badge badge-danger">InActive</span>
                        @endif 
                </td>
                <td>
                    @if (auth()->user()->can('ic_version_configurator.edit'))
                    <button type="button" class="btn btn-primary editModal" data-toggle="modal" data-target="#editModal"  company="{{$searchData['ic_alias']}}" segment="{{$searchData['segment']}}" versionValueData="{{$searchData['version']}}" kitType="{{$searchData['kit_type']}}" description="{{$searchData['description']}}" slug = "{{$searchData['slug']}}" integrationType = "{{$searchData['integration_type']}}"  businessType = "{{$searchData['business_type']}}">
                        <i class="fas fa-edit"></i>
                    </button>
                    @endif
                </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @else
    <div class="table-container" id="view-table">
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Insurance Company</th>
                    <th>Integration Type</th>
                    <th>Version</th>
                    <th>Kit Type</th>
                    <th>Segment</th>
                    <th>Comment</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td colspan="8">No record found</td>
                </tr>
            </tbody>
        </table>
    </div>
    @endif

    <div class="modal fade" id="editModal" tabindex="-1" role="dialog" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-sm" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editModalLabel">Edit Status</h5>
                </div>
                <div class="modal-body">
                    <form id="editForm">
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select class="form-control" id="status" name="status" required>
                                <option value="">select</option>
                                <option value="1">Active</option>
                                <option value="0">InActive</option>
                            </select>
                        </div>
                        <input type="hidden" id="company" name="company">
                        <input type="hidden" id="segment" name="segment">
                        <input type="hidden" id="version" name="version">
                        <input type="hidden" id="kit" name="kit">
                        <input type="hidden" id="slug" name="slug">
                        <input type="hidden" id="integration_type" name="integration_type">
                        <input type="hidden" id="business_type" name="business_type">

                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary btn-sm" id="saveChanges">Save</button>
                        </div>
                    </form>
                </div>            
            </div>
        </div>
    </div>

</section>
@endsection
@section('scripts')
 <script>
    const saveVersion = "{{ route('admin.ic-configuration.version.save-version-data') }}";
 </script>
<script src="{{asset('admin1/js/ic-config/version.js')}}"></script>
@endsection