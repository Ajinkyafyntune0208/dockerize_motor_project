<style>
    .loading-table td{
        padding-top: 0.5rem !important;
        padding-bottom: 0.5rem !important;
    }
</style>
<div class="card mt-3">  
    <div class="card-header">
        <h3 class="card-title">Loading Amount Configurator</h3>
    </div>
    <div class="card-body">
        @php
        $icList = App\Models\MasterCompany::whereNotNull('company_alias')
        ->pluck('company_alias');
        $chunks = $icList->count() / 2;
        $chunks = $icList->count() % 2 == 0 ? $chunks : $chunks + 1;
        $icList = $icList->chunk($chunks)->toArray();
        @endphp
         <form action="{{ route('admin.common-config-save') }}" method="POST" class="mt-3 loadingConfig">
            @csrf
            <input type="hidden" value="loadingConfig" name="formType">
            <div class="mb-2">
                <label for="loading-config-apply">Select All</label>
                <input type="checkbox" id="loading-config-apply">
            </div>
           <div class="row">
                @foreach ($icList as $lists)
                    <div class="col-md-6">
                        <table class="table table-striped col-sm-12 loading-table">
                            <thead>
                                <tr class="text-center">
                                    <th>IC Names</th>
                                    <th>Show</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($lists as $index => $ic)
                                    <tr class="w-100">
                                        <td>{{ $index + 1 . ' - '. $ic}}</td>
                                        <td class="text-center">
                                            <input type="hidden" name="data[loadingConfig.ic.{{$ic}}][label]" value="Show {{ucFirst(str_replace('_', ' ', $ic))}} Loading Amount">
                                            <input type="hidden" name="data[loadingConfig.ic.{{$ic}}][key]" value="loadingConfig.ic.{{$ic}}">
                                            <input class="form-check-input loading-checkbox" type="checkbox" name="data[loadingConfig.ic.{{$ic}}][value]" {{ ($allData['loadingConfig.ic.'.$ic] ?? '') == "Y" ? 'checked' : '' }}>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endforeach
            </div>
            <div class="col-sm-12">
                <div class="form-group">
                    <label></label>
                    <div class="d-flex flex-row-reverse">
                        <button type="submit" class="btn btn-primary">Submit</button>
                    </div>
                </div>
           </div>
         </form>
    </div>
</div>
<script>
    onload = ()=>{
        let isLoadingChecked = true;
        document.querySelectorAll('.loading-checkbox').forEach(element => {
            if (!element.checked && isLoadingChecked) {
                isLoadingChecked = false;
            }
        });

        if (isLoadingChecked) {
            document.querySelector('#loading-config-apply').checked = true;
        }
    }
    document.querySelector('#loading-config-apply').addEventListener('change', (e) => {
        if (e.target.checked) {
            document.querySelectorAll('.loading-checkbox').forEach(element => {
                element.checked = true;
            });
        } else {
            document.querySelectorAll('.loading-checkbox').forEach(element => {
                element.checked = false;
            });
        }
    })
</script>