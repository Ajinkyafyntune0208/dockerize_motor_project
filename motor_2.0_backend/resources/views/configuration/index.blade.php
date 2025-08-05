@extends('layout.app', ['activePage' => 'configuration', 'titlePage' => __('Configuration')])
@section('content')


<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/2.2.3/css/buttons.dataTables.min.css" />

    <div class="content-wrapper">
        <div class="grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">Configuration</h4>
                        <div id="errorAlert" class="alert alert-danger" role="alert" hidden>
                            Search field is required.
                          </div>
                        <form action="" method="GET" name="ConfigSettingsForm" onsubmit="return validateForm()">
                            <div class="row">
                                <div class="col-sm-3">
                                    <div class="form-group">
                                        <label for="config_search">Search by Label / Key / Value :</label>
                                        <input id="config_search" name="config_search" type="text" value="{{ old('config_search', request()->config_search ?? null) }}" class="form-control" placeholder="">
                                    </div>
                                </div>

                                <input type="hidden" name="paginate" value="30">


                                <div class="col-sm-3">
                                    <div class="form-group">
                                        <button class="btn btn-outline-primary" type="submit" style="margin-top: 20px;"><i class="fa fa-search"></i> Search</button>
                                        @can('configuration.create')
                                            <a class="btn btn-primary" style="margin-top: 20px;" href="{{ route('admin.configuration.create') }}">+ Add Config</i></a>
                                        @endcan
                                    </div>
                                </div>
                            </div>
                        </form>

                    </div>
                </div>
        </div>
        <div class="row">
            <div class="col-lg-12 grid-margin stretch-card">
                <div class="card">
                    @php
                        $dropDownValues = [10,20,30,40,50];
                    @endphp
                    <div class="card-body">
                        <div class="d-flex col-1" style="padding-top:30px">
                            <label class="mx-2">Show</label>
                            <select name="paginateDropdown" data-style="btn-sm btn-primary" data-actions-box="true" class="selectpicker w-100 mx-2" data-live-search="true">
                                @foreach ($dropDownValues as $item)
                                <option value="{{$item}}" {{ ($perPage == $item) ? 'selected' : '' }}>{{$item}}</option>
                                @endforeach
                            </select>
                            <label>entries</label>
                        </div>
                        @if (session('status'))
                            <div class="alert alert-{{ session('class') }}">
                                {{ session('status') }}
                            </div>
                        @endif

                        <div class="table-responsive">
                            <table class="table-striped table border">
                                <thead>
                                    <tr>
                                        <th scope="col">#</th>
                                        @if(auth()->user()->can('configuration.edit') || auth()->user()->can('configuration.delete'))
                                            <th scope="col">Action</th>
                                        @endif
                                        <th scope="col">Label</th>
                                        <th scope="col">Key</th>
                                        <th scope="col">Value</th>
                                        <th scope="col">Environment</th>
                                        <th scope="col">created_at</th>
                                        <th scope="col">updated_at</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($configs as $config)
                                        <tr>
                                            <td scope="row">{{ $loop->iteration }}</td>
                                            @if(auth()->user()->can('configuration.edit') || auth()->user()->can('configuration.delete'))
                                            <td>
                                                <form action="{{ auth()->user()->can('configuration.delete') ? route('admin.configuration.destroy', $config) : '' }}"
                                                    method="post" onsubmit="return confirm('Are you sure..?')"> @csrf
                                                    @method('DELETE')
                                                    <div class="btn-group">
                                                        @can('configuration.edit')
                                                        <a class="btn btn-sm btn-success" style="padding-right: 6px; padding-left: 10px;"
                                                            href="{{ route('admin.configuration.edit', $config->id) }}"><i
                                                                class="fa fa-edit"></i></a>
                                                        @endcan
                                                        @can('configuration.delete')
                                                        <button class="btn btn-sm btn-outline-danger" type="submit" style="padding-left: 6px; padding-right: 10px;"><i
                                                                class="fa fa-trash"></i></button>
                                                        @endcan
                                                    </div>
                                                </form>
                                            </td>
                                            @endif
                                            <td>{{ $config->label }}</td>
                                            <td><i class="text-info fa fa-copy ml-2" role="button"></i> {{ $config->key }}
                                            </td>
                                            <td><i class="text-info fa fa-copy ml-2" role="button"></i>
                                                {{ $config->value }}</td>
                                            <td>{{ $config->environment }}</td>
                                            <td>{{ $config->created_at }}</td>
                                            <td>{{ $config->updated_at }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mt-2">
                            <p scope="col">Total Result found: {{$configs->total()}}</p>
                            <p scope="col">Showing records per page: {{$configs->count()}}</p>
                            <div scope="col">
                                @if(!$configs->isEmpty())
                                    {{ $configs->appends(request()->query())->links() }}
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
@push('scripts')
    <script type="text/javascript" src="{{ asset('js/buttons.dataTables.buttons.min.js') }}"></script>
    <script type="text/javascript" src="{{ asset('js/buttons.jszip.min.js') }}"></script>
    <script type="text/javascript" src="{{ asset('js/buttons.html5.min.js') }}"></script>
    <script type="text/javascript" src="{{ asset('js/buttons.print.min.js') }}"></script>

    <script>
        function validateForm(e){
            if($('#config_search').val()=='' ||  $('#config_search').val()==null){
                $('#errorAlert').attr('hidden',false);
                return false;
            }
        }
        $(document).ready(function() {
            $('.table').DataTable({
                paging: false,
                ordering: false,
                info: false,
                // dom: 'Bfrtip',
                dom: 'lPfBrtpi',
                buttons: ['csv', 'excel'],
                searchPane: true,
                search: {
                    search: ''
                }
            });
            document.querySelector('[name="paginateDropdown"]').addEventListener('change', (e)=>{
                document.querySelector('[name="paginate"]').value=e.target.value

                document.querySelector('[name="ConfigSettingsForm"]').submit();
            })
            $(document).on('click', '.fa.fa-copy', function() {
                var text = $(this).parent('td').text();
                const elem = document.createElement('textarea');
                elem.value = text.trim();
                document.body.appendChild(elem);
                elem.select();
                document.execCommand('copy');
                document.body.removeChild(elem);
                alert('Text copied...!')
            });
        });
    </script>
@endpush
