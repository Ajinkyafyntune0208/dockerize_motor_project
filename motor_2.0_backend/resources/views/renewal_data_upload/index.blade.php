@extends('layout.app', ['activePage' => 'renewal_upload_excel', 'titlePage' => __('Renewal Data Upload')])
@section('content')
<style>
    :root{
    --primary: #1f3bb3;
    }
    .btn-cus{
        width: 150px;
        height: 40px;
        border-radius: 10px;
        background: transparent;
        border: 1px solid var(--primary);
        color: var(--primary);
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .btn-cus-2{
        border-radius: 10px;
        padding: 10px;
        background: transparent;
        border: 1px solid var(--primary);
        color: var(--primary);
    }
    .btn-cus:hover , .btn-cus-2:hover{
        background-color: var(--primary);
        color: white;
    }
    .btn-cus.active{
        background-color: var(--primary);
        color: white;
    }
</style>
<div class="content-wrapper">

    <div class="row">
        <div class="col-sm-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <div class="alert" role="alert" hidden id="alert">

                      </div>
                    <h5 class="card-title">Upload Renewal Data</h5>
                    @if (session()->has('status'))
                        <div class="alert alert-success ">
                            <p> Total Records: {{session()->get('totaldata')}} <br>
                                <span>Recorded uploaded: {{session()->get('datauploaded')}}</span> <br>
                                <span class="text-danger">Total Error in records: {{session()->get('errorcount')}} </span> <br>
                                @if ( session()->get('errorcount') > 0)
                                    <span><button class="btn btn-cus-2" id="viewerrors"> <i class="fa fa-download" style="margin-right: 0.25rem;"></i>View errors</button></span>
                                @endif
                            </p>
                        </div>
                    @endif
                    @if (session()->has('error') || session()->has('message') )
                        <div class="alert alert-danger ">
                            {{session()->get('message')}}
                            <br>
                            {{session()->get('error')}}
                        </div>
                    @endif
                    @error('renewal_excel')
                        <div class="alert alert-danger">{{ $message}}</div>
                    @enderror
                    @error('have_dashboard')
                        <div class="alert alert-danger">{{ $message}}</div>
                    @enderror

                    <form action="{{route('admin.renewal_upload_excel_post')}}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="row">
                            <div class="col-sm-6 form-group">
                                <label for="renewal_excel" class="required">Please upload excel file </label>
                                <input class="form-control" type="file" name="renewal_excel" id="renewal_excel" style="background: transparent;">
                                <div style="margin-top: 20px;">
                                    <input type="checkbox" class="form-check-input" style="margin-right:5px;" id="have_dashboard" name="have_dashboard" value="true" />
                                    <label class="form-check-label" for="ckyc_mandate">Do you have dashboard ?</label>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-6">
                                <button type="submit" class="btn btn-cus">Upload</button>
                            </div>
                            @if (auth()->user()->can('renewal_data_upload.sync'))
                                <div class="col-6">
                                    <a class="view btn btn-primary float-end"  id ="sync" onclick="syncData()">
                                        <i class="mdi mdi-rotate-3d"></i>
                                        Sync Data
                                    </a>
                                </div>
                            @endif
                        </div>
                        <div class="my-2">
                            Note: <span class="text-warning" style="font-size: 16px;">Please make sure your excel format is approved before you use this utility</span>
                        </div>
                    </form>
                    @if (session()->has('status'))
                        <form action="{{route('admin.renewal_excel_validation_error')}}" method="POST" id="exportform">
                            @csrf
                            {{--exporting in excel via form to download data when validation fails --}}
                            <input type="hidden" name="errorexcel" value="{{session()->get('allDatawitherror')}}">
                            <input type="hidden" name="errorexcelheading" value="{{session()->get('collectheading')}}">
                        </form>
                    @endif


                </div>
            </div>
        </div>
    </div>

</div>
<script src="{{ asset('js/jquery-3.7.0.min.js') }}" integrity="sha256-2Pmvv0kuTBOenSvLm6bvfBSSHrUJ+3A7x6P5Ebd07/g=" crossorigin="anonymous"></script>
<script>
    function syncData(){
        $.ajax({
            url: '/manualDataMigration',
            type: 'GET',
            dataType: 'json',
            success: function(data) {
            if (data.status) {
                $('#alert').text(data.message).attr('hidden',false).addClass('alert-success');
            } else {
                $('#alert').text(data.message).attr('hidden',false).addClass('alert-danger');
            }
        },
        });
        }
    $(document).ready(function() {

        $('#viewerrors').on('click', function() {
            $('#exportform').submit();
        });

    });

</script>
@endsection
