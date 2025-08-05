@extends('admin_lte.layout.app', ['activePage' => 'master-product', 'titlePage' => __('Menu Master')])
@section('content')
<a class="btn btn-primary mb-3" href="{{ url()->previous() }}"><i class="fa fa-arrow-left mx-2" aria-hidden="true"></i></a>
<div class="card">
    <div class="card-body">

            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

        <form action="{{ route('admin.menu.store') }}" method="POST" class="mt-3"
            name="add_master">
            @csrf @method('POST')
            <div class="row mb-3">
                <div class="col-sm-4">
                    <div class="form-group">
                        <label>Parent Name </label>
                        <select id="parent_id" name="parent_id" class="form-control" required>
                            <option value="0">None</option>
                            @foreach($menus as $menu)
                            <option value="{{$menu->menu_id}}">{{$menu->menu_name}}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="col-sm-4">
                    <div class="form-group">
                        <label>Menu Name <span style="color: red;">*</span> </label>
                        <input id="menu_name" name="menu_name" type="text" class="form-control" placeholder="Menu Name">
                    </div>
                </div>

                <div class="col-sm-4">
                    <div class="form-group">
                        <label>Menu Slug <span style="color: red;">*</span> </label>
                        <input id="menu_slug" name="menu_slug" type="text" class="form-control" placeholder="Menu Slug">
                    </div>
                </div>

                <div class="col-sm-4">
                    <div class="form-group">
                        <label>Menu URL <span style="color: red;">*</span> </label>
                        <input id="menu_url" name="menu_url" type="text" class="form-control" placeholder="Menu URL">
                    </div>
                </div>

                <div class="col-sm-4">
                    <div class="form-group">
                        <label>Menu ICON <span style="color: red;">*</span> </label>
                        <input id="menu_icon" name="menu_icon" type="text" class="form-control" placeholder='Ex : <i class="nav-icon fas fa-th"></i>'>
                    </div>
                </div>

                <div class="col-sm-4">
                    <div class="form-group">
                        <label>Status <span style="color: red;">*</span> </label> <br>
                        <input name="status" type="radio" value="Y" checked> Active &emsp;
                        <input name="status" type="radio" value="N"> Inactive
                    </div>
                </div>

                <div class="col-12 d-flex mt-3">
                    <button type="submit" class="btn btn-primary mr-2">Submit</button>

                </div>
        </form>
    </div>
</div>
@endsection
