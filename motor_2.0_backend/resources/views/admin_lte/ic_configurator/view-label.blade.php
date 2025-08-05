@extends('admin_lte.layout.app', ['activePage' => 'ic_configurator', 'titlePage' => __('VIEW')])
@section('content')

<link rel="stylesheet" href="{{asset('css/viewlabel.css')}}">
<section>
<button class="btn btn-primary" id="save-btn" type="submit"><a href="{{ url('admin/ic-configuration/label-attributes') }}"> <i class="fa fa-solid fa-arrow-left"></i> BACK</a>
</button>

<div class="row mb-4">
    <div class="col-md-4">
        <div class="box">
        @foreach($label as $key => $searchData)
            <p><strong>Label Name - </strong><span>{{$searchData['label_name']}}</span></p>
   @endforeach
        </div>
    </div>
    <div class="col-md-4">
        <div class="box">
            <p><strong>Label Key - </strong><span>{{$searchData['label_key']}}</span></p>
        </div>
    </div>
    <div class="col-md-4">
        <div class="box">
            <p><strong>Label Group - </strong><span>{{$searchData['label_group']}}</span></p>
        </div>
    </div>
</div>
<div>
    <ul class="nav nav-tabs" role="tablist">
        @can('label_and_attribute.list')
        <li class="nav-item" role="presentation"><button class="nav-link active" href="#tab-table1" data-bs-toggle="tab" data-bs-target="#tab-table1">Mapped Attributes</button></li>
        @endcan
        @can('formulas.list')
        <li><button class="nav-link" href="#tab-table2" data-bs-toggle="tab" data-bs-target="#tab-table2">Formula List</button></li>
        @endcan
        @can('premium_calculation_configurator.list')
        <li class="nav-item" role="presentation">
            <button class="nav-link" href="#tab-table3" data-bs-toggle="tab" data-bs-target="#tab-table3">Configured in IC's</button>
        </li>
        @endcan
    </ul>
    <div class="tab-content pt-2">
        <div class="tab-pane show active" id="tab-table1">
            <table id="myTable1" class="table table-striped table-bordered" cellspacing="0" width="100%">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>IC Integration</th>
                        <th>Segment</th>
                        <th>Business Type</th>
                        <th>Attribute</th>
                    </tr>
                </thead>
                <tbody>
                    @if(!empty($listing->all()))
                    @foreach($listing as $key => $searchData)
                    <tr id="row-{{$searchData['id']}}">
                        <td>{{$key + 1}}</td>
                        <td class="company">{{$searchData['ic_alias']}}</td>
                        <td class="vehicle">{{$searchData['segment']}}</td>
                        <td class="business">{{$searchData['business_type']}}</td>
                        <td class="attribute">{{$searchData['final_attribute']}}</td>
                    </tr>
                    @endforeach
                    @else
                    <thead>
                    <tr>
                        {{-- <td colspan="5" class="view-tabel">No record found</td>  --}}
                    </tr>
                </thead>
                    @endif
                </tbody>
            </table>
        </div>


        <div class="tab-pane" id="tab-table2">
            <table id="myTable2" class="table table-striped table-bordered" cellspacing="0" width="100%">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Formula Name</th>
                        <th>View Formula</th>
                    </tr>
                </thead>
                <tbody>
                    @if(!empty($formula))
                    @foreach($formula as $key => $searchData)
                    <tr id="row-{{$searchData['id']}}">
                        <td>{{$key + 1}}</td>
                        <td class="company">{{$searchData['formula_name']}}</td>
                        <td class="company">
                            <button type="button" class="btn btn-primary m-1 edit-btn" data-toggle="modal" data-target="#editModal" id="{{$searchData['id']}}" formulaLabelName = "{{$searchData->short_formula}}">
                                <i class="fa fa-eye" aria-hidden="true"></i></i>
                            </button>
                        </td>
                    </tr>
                    @endforeach
                    @else
                    <thead>
                    <tr>
                        {{-- <td colspan="2" class="view-tabel">No record found</td> --}}
                    </tr>
                </thead>
                    @endif
                </tbody>
            </table>
        </div>

        <div class="tab-pane" id="tab-table3">
            <table id="myTable3" class="table table-striped table-bordered table-3" cellspacing="0" width="100%">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>IC Integration Type</th>
                        <th>Segment</th>
                        <th>Business Type</th>
                        <th>Type</th>
                        <th>Value</th>
                    </tr>
                </thead>
                <tbody>
                    @if(!empty($configData))
                    @foreach($configData as $key => $searchData)
                    <tr id="row-{{$searchData['id']}}">
                        <td>{{$key + 1}}</td>
                        <td class="company">{{$searchData['ic_integration_type']}}</td>
                        <td class="vehicle">{{$searchData['segment']}}</td>
                        <td class="business">{{$searchData['business_type']}}</td>
                        <td class="attribute">{{$searchData['type']}} - @if ($searchData['type'] == "formula")
                            <button type="button" class="btn btn-primary m-1 edit-btn" data-toggle="modal" data-href="{{route('admin.ic-configuration.formula.view-formula', ['id' => $searchData['id']])}}" onclick="viewFormula(this)">
                                <i class="fa fa-eye" aria-hidden="true"></i></i>
                            </button>
                        @endif</td>
                        <td class="attribute">{{$searchData['value']}}</td>
                    </tr>
                    @endforeach
                    @else
                <thead>
                    <tr>
                        {{-- <td colspan="6" class="view-tabel">No record found</td> --}}
                    </tr>
                </thead>
                    @endif
                </tbody>
            </table>
        </div>


    </div>
</div>

<div class="modal fade" id="editModal" name="editModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-body">
                <div class="container mt-5">
                    <div class="card">
                        <div class="card-header bg-dark text-white">
                            View Formula
                        </div>
                        <form action="#" id="Editcreate-form">
                            <div class="card-body">
                                <div class="form-group">
                                    <label for="EditlabelInput" class="required_field"> Formula : </label>
                                    <p class="showFormula" id = "showFormula"></p>
                                </div>
                                <div class="form-group">
                                    <label for="showKey" id="showKey"></label>
                                </div>
                        <button type="button" class="btn btn-primary" data-dismiss="modal">CLOSE</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- part -2  --}}
<div class="modal fade" id="formulaModal" name="formulaModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-body">
                <div class="container mt-5">
                    <div class="card">
                        <div class="card-header bg-dark text-white">
                            View Formula
                        </div>
                        <form action="#" id="Editcreate-form">
                            <div class="card-body">
                                <div class="form-group">
                                    <label for="EditlabelInput" class="required_field"> Formula : </label>
                                    <p class="showFormula_1" id = "showFormula_1"></p>
                                </div>
                                <div class="form-group">
                                    <label for="showKey" id="showKey"></label>
                                </div>
                        <button type="button" class="btn btn-primary" data-dismiss ="modal" onclick="closeModal()">CLOSE</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</section>
@endsection

@section('scripts')
<script src="{{asset('admin1/js/ic-config/viewlabel.js')}}"></script>
@endsection