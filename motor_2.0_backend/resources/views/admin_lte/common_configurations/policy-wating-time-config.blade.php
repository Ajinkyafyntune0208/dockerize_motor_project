@php
    $icList = App\Models\MasterCompany::whereNotNull('company_alias')
    ->get()
    ->pluck('company_alias');
    $chunks = $icList->count() / 2;
    $chunks = $icList->count() % 2 == 0 ? $chunks : $chunks + 1;
    $icList = $icList->chunk($chunks)->toArray();
@endphp
<div class="card mt-3">
    <div class="card-header">
        <h3 class="card-title">Policy Waiting Time Configurator</h3>
    </div>
    <div class="card-body">
        <form action="{{ route('admin.common-config-save') }}" method="POST">
            <input type="hidden" value="policyWaitingTime" name="formType">
            @csrf
            <div class="row">
                <div class="col-12">

                    <input type="hidden" name="data[policyWaitingTime.activation][label]" value="Policy Waiting Time Activation">
                    <input type="hidden" name="data[policyWaitingTime.activation][key]" value="policyWaitingTime.activation">

                    <label for="policyWaitingTimeActivation">Activate Config : </label>
                    <select name="data[policyWaitingTime.activation][value]" id="policyWaitingTimeActivation">
                        <option value="Y" {{($allData['policyWaitingTime.activation'] ?? 'N') == 'Y' ? 'selected' : ''}}>Enable</option>
                        <option value="N" {{($allData['policyWaitingTime.activation'] ?? 'N') != 'Y' ? 'selected' : ''}}>Disable</option>
                    </select>
                </div>
            </div>
            <div class="row">
                @foreach ($icList as $lists)
                    <div class="col-md-6">
                        <table class="table table-striped col-sm-12 loading-table">
                            <thead>
                                <tr class="text-center">
                                    <th>IC Names</th>
                                    <th>Waiting Time (in secs)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($lists as $index => $ic)
                                    <tr class="w-100">
                                        <td>{{ $index + 1 . ' - '. $ic}}</td>
                                        <td class="text-center">
                                            <input type="hidden" name="data[policyWaitingTime.ic.{{$ic}}][label]" value="Policy Waiting Time of {{ucFirst(str_replace('_', ' ', $ic))}}">
                                            <input type="hidden" name="data[policyWaitingTime.ic.{{$ic}}][key]" value="policyWaitingTime.ic.{{$ic}}">
                                           <input name="data[policyWaitingTime.ic.{{$ic}}][value]" type="number"
                                           min="0" required title="Please enter a valid value"
                                           value="{{$allData['policyWaitingTime.ic.'.$ic] ?? 0}}">
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
                    <div class="d-flex flex-row-reverse">
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </div>
           </div>
        </form>
    </div>
</div>