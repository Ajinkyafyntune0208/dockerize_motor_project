@extends('admin_lte.layout.app', ['activePage' => '', 'titlePage' => __('MDM Master')])

@section('content')
<div class="container">
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    {{-- CREATE FORM --}}
    <form action="{{ route('admin.mdm_master.store') }}" method="POST" class="mb-4">
        @csrf
        <div class="form-group">
            <label>Allowed Table</label>
            <input type="text" name="mdm_allowed_table" class="form-control" required>
        </div>
        <button class="btn btn-success mt-2">Add</button>
    </form>

    {{-- TABLE LIST --}}
    <table id="data-table"  class="table table-bordered">
        <thead>
            <tr>
                <th>Sr No</th>
                <th>Allowed Table</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data as $key => $item)
                <tr>
                    <td>{{ $key + 1 }}</td>
                    <td>{{ $item->mdm_allowed_table }}</td>
                    <td>
                        {{-- Edit triggers reload with edit_id --}}
                        <a href="{{ route('admin.mdm_master.index', ['edit_id' => $item->id]) }}"  style="padding-left: 6px; padding-right: 10px;" class="btn btn-sm btn-success" ><i
                        class="fa fa-edit"></i></a>
                        <form action="{{ route('admin.mdm_master.destroy') }}" method="POST" style="display:inline;">
                            @csrf
                            <input type="hidden" name="id" value="{{ $item->id }}">
                            <button onclick="return confirm('Delete this?')" class="btn btn-sm btn-outline-danger"  style="padding-right: 6px; padding-left: 10px;"> <i
                                                    class="fa fa-trash"></i></button>
                        </form>
                        <button onclick="alert('Allowed Table: {{ $item->mdm_allowed_table }}')"  class="btn btn-sm btn-outline-primary"><i
                        class="fa fa-eye"></i></button>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

{{-- EDIT MODAL --}}
@if($editItem)
<!-- Modal -->
<div class="modal fade show" id="editModal" tabindex="-1" role="dialog" style="display:block;" aria-modal="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <form action="{{ route('admin.mdm_master.update') }}" method="POST">
        @csrf
        <input type="hidden" name="id" value="{{ $editItem->id }}">
        <div class="modal-header">
          <h5 class="modal-title">Edit Allowed Table</h5>
          <a href="{{ route('admin.mdm_master.index') }}" class="close" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </a>
        </div>
        <div class="modal-body">
          <div class="form-group">
              <label>Allowed Table</label>
              <input type="text" name="mdm_allowed_table" value="{{ $editItem->mdm_allowed_table }}" class="form-control" required>
          </div>
        </div>
        <div class="modal-footer">
          <a href="{{ route('admin.mdm_master.index') }}" class="btn btn-secondary">Cancel</a>
          <button type="submit" class="btn btn-primary">Update</button>
        </div>
      </form>
    </div>
  </div>
</div>

{{-- Backdrop --}}
<div class="modal-backdrop fade show"></div>
@endif
@endsection

@section('scripts')
<script>
   $(document).ready(function() {
    var activeTable = $("#data-table").DataTable({
        "responsive": true,
        "lengthChange": true,
        "autoWidth": false,
        "scrollX": false,
    });
});

</script>
@endsection
