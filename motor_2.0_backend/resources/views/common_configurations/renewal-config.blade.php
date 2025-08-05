@include('common_configurations.renewal-attempt-config')


<div class="card mt-3">
    <div class="card-body" style="max-width: 1000px;overflow-x:scroll">
        <h5 class="card-title text-primary">Renewal Configurator <span style="font-size: 12px;color:gray">(Note:- Checkbox Selected: Editing Enabled)</span></h5>
        @if (session('status') && session('formType') == 'renewalConfig')
            <div class="alert alert-{{ session('class') }}">
                {{ session('status') }}
            </div>
        @endif
        @php

        $sections = [
            'Retail to Retail (Online)' => [
                'elements' => [
                    'NCB', 'IDV'
                ]
            ],
            'Offline to Retail' => [
                'Fetch' => [
                    'elements' => ['NCB', 'IDV']
                ], 'Vahan' => [
                    'elements' => ['NCB', 'IDV']
                ],
                'Client Data' => [
                    'elements' => ['NCB', 'IDV']
                ]
            ],
        ];
        $section_list = array_keys($sections);
        @endphp
         <form action="{{ route('admin.common-config-save') }}" method="POST" class="mt-3 renewalConfig">
            @csrf

            <input type="hidden" value="renewalConfig" name="formType">
            <table class="table table-striped col-sm-6 renewal-config-table">
                <thead>
                    <tr>
                        @foreach ($section_list as $list)
                            <th colspan="6" class="text-center">{{$list}}</th>
                        @endforeach
                    </tr>
                    <tr>
                        <th>Editable Category</th>
                        <th>IDV</th>
                        <th>NCB</th>
                        <th>Claim</th>
                        <th>Proposal</th>
                        <th>Ownership</th>
                        <th>Editable Category</th>
                        <th>IDV</th>
                        <th>NCB</th>
                        <th>Claim</th>
                        <th>Proposal</th>
                        <th>Ownership</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Online</td>
                        <td>
                            <input type="hidden" name="data[renewalConfig.online.idv.edit][label]" value="Online Renewal IDV Editable">
                            <input type="hidden" name="data[renewalConfig.online.idv.edit][key]" value="renewalConfig.online.idv.edit">
                            <input class="form-check-input" type="checkbox" name="data[renewalConfig.online.idv.edit][value]" {{ ($allData['renewalConfig.online.idv.edit'] ?? '') == "Y" ? 'checked' : '' }}>
                        </td>
                        <td>
                            <input type="hidden" name="data[renewalConfig.online.ncb.edit][label]" value="Online Renewal NCB Editable">
                            <input type="hidden" name="data[renewalConfig.online.ncb.edit][key]" value="renewalConfig.online.ncb.edit">
                            <input class="form-check-input" type="checkbox" name="data[renewalConfig.online.ncb.edit][value]" {{ ($allData['renewalConfig.online.ncb.edit'] ?? '') == "Y" ? 'checked' : '' }}>
                        </td>

                        <td>
                            <input type="hidden" name="data[renewalConfig.online.claim.edit][label]" value="Online Renewal Claim Editable">
                            <input type="hidden" name="data[renewalConfig.online.claim.edit][key]" value="renewalConfig.online.claim.edit">
                            <input class="form-check-input" type="checkbox" name="data[renewalConfig.online.claim.edit][value]" {{ ($allData['renewalConfig.online.claim.edit'] ?? '') == "Y" ? 'checked' : '' }}>
                        </td>

                        <td>
                            <input type="hidden" name="data[renewalConfig.online.proposal.edit][label]" value="Online Renewal Proposal Editable">
                            <input type="hidden" name="data[renewalConfig.online.proposal.edit][key]" value="renewalConfig.online.proposal.edit">
                            <input class="form-check-input" type="checkbox" name="data[renewalConfig.online.proposal.edit][value]" {{ ($allData['renewalConfig.online.proposal.edit'] ?? '') == "Y" ? 'checked' : '' }}>
                        </td>

                        <td>
                            <input type="hidden" name="data[renewalConfig.online.ownership.edit][label]" value="Online Renewal Ownership Editable">
                            <input type="hidden" name="data[renewalConfig.online.ownership.edit][key]" value="renewalConfig.online.ownership.edit">
                            <input class="form-check-input" type="checkbox" name="data[renewalConfig.online.ownership.edit][value]" {{ ($allData['renewalConfig.online.ownership.edit'] ?? '') == "Y" ? 'checked' : '' }}>
                        </td>
    
    
                        <td>IC Fetch</td>
                        <td>
                            <input type="hidden" name="data[renewalConfig.offline.icFetch.idv.edit][label]" value="Renewal IC Fetch IDV Editable">
                            <input type="hidden" name="data[renewalConfig.offline.icFetch.idv.edit][key]" value="renewalConfig.offline.icFetch.idv.edit">
                            <input class="form-check-input" type="checkbox" name="data[renewalConfig.offline.icFetch.idv.edit][value]" {{ ($allData['renewalConfig.offline.icFetch.idv.edit'] ?? '') == "Y" ? 'checked' : '' }}>
                        </td>
                        <td>
                            <input type="hidden" name="data[renewalConfig.offline.icFetch.ncb.edit][label]" value="Renewal IC Fetch NCB Editable">
                            <input type="hidden" name="data[renewalConfig.offline.icFetch.ncb.edit][key]" value="renewalConfig.offline.icFetch.ncb.edit">
                            <input class="form-check-input" type="checkbox" name="data[renewalConfig.offline.icFetch.ncb.edit][value]" {{ ($allData['renewalConfig.offline.icFetch.ncb.edit'] ?? '') == "Y" ? 'checked' : '' }}>
                        </td>

                        <td>
                            <input type="hidden" name="data[renewalConfig.offline.icFetch.claim.edit][label]" value="Renewal IC Fetch Claim Editable">
                            <input type="hidden" name="data[renewalConfig.offline.icFetch.claim.edit][key]" value="renewalConfig.offline.icFetch.claim.edit">
                            <input class="form-check-input" type="checkbox" name="data[renewalConfig.offline.icFetch.claim.edit][value]" {{ ($allData['renewalConfig.offline.icFetch.claim.edit'] ?? '') == "Y" ? 'checked' : '' }}>
                        </td>

                        <td>
                            <input type="hidden" name="data[renewalConfig.offline.icFetch.proposal.edit][label]" value="Renewal IC Fetch Proposal Editable">
                            <input type="hidden" name="data[renewalConfig.offline.icFetch.proposal.edit][key]" value="renewalConfig.offline.icFetch.proposal.edit">
                            <input class="form-check-input" type="checkbox" name="data[renewalConfig.offline.icFetch.proposal.edit][value]" {{ ($allData['renewalConfig.offline.icFetch.proposal.edit'] ?? '') == "Y" ? 'checked' : '' }}>
                        </td>

                        <td>
                            <input type="hidden" name="data[renewalConfig.offline.icFetch.ownership.edit][label]" value="Renewal IC Fetch Ownership Editable">
                            <input type="hidden" name="data[renewalConfig.offline.icFetch.ownership.edit][key]" value="renewalConfig.offline.icFetch.ownership.edit">
                            <input class="form-check-input" type="checkbox" name="data[renewalConfig.offline.icFetch.ownership.edit][value]" {{ ($allData['renewalConfig.offline.icFetch.ownership.edit'] ?? '') == "Y" ? 'checked' : '' }}>
                        </td>
                    </tr>
                    <tr>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td>Vahan</td>
                        <td>
                            <input type="hidden" name="data[renewalConfig.offline.vahan.idv.edit][label]" value="Renewal Vahan IDV Editable">
                            <input type="hidden" name="data[renewalConfig.offline.vahan.idv.edit][key]" value="renewalConfig.offline.vahan.idv.edit">
                            <input class="form-check-input" type="checkbox" name="data[renewalConfig.offline.vahan.idv.edit][value]" {{ ($allData['renewalConfig.offline.vahan.idv.edit'] ?? '') == "Y" ? 'checked' : '' }}>
                        </td>
                        <td>
                            <input type="hidden" name="data[renewalConfig.offline.vahan.ncb.edit][label]" value="Renewal Vahan NCB Editable">
                            <input type="hidden" name="data[renewalConfig.offline.vahan.ncb.edit][key]" value="renewalConfig.offline.vahan.ncb.edit">
                            <input class="form-check-input" type="checkbox" name="data[renewalConfig.offline.vahan.ncb.edit][value]" {{ ($allData['renewalConfig.offline.vahan.ncb.edit'] ?? '') == "Y" ? 'checked' : '' }}>
                        </td>

                        <td>
                            <input type="hidden" name="data[renewalConfig.offline.vahan.claim.edit][label]" value="Renewal Vahan Claim Editable">
                            <input type="hidden" name="data[renewalConfig.offline.vahan.claim.edit][key]" value="renewalConfig.offline.vahan.claim.edit">
                            <input class="form-check-input" type="checkbox" name="data[renewalConfig.offline.vahan.claim.edit][value]" {{ ($allData['renewalConfig.offline.vahan.claim.edit'] ?? '') == "Y" ? 'checked' : '' }}>
                        </td>

                        <td>
                            <input type="hidden" name="data[renewalConfig.offline.vahan.proposal.edit][label]" value="Renewal Vahan Proposal Editable">
                            <input type="hidden" name="data[renewalConfig.offline.vahan.proposal.edit][key]" value="renewalConfig.offline.vahan.proposal.edit">
                            <input class="form-check-input" type="checkbox" name="data[renewalConfig.offline.vahan.proposal.edit][value]" {{ ($allData['renewalConfig.offline.vahan.proposal.edit'] ?? '') == "Y" ? 'checked' : '' }}>
                        </td>

                        <td>
                            <input type="hidden" name="data[renewalConfig.offline.vahan.ownership.edit][label]" value="Renewal Vahan Ownership Editable">
                            <input type="hidden" name="data[renewalConfig.offline.vahan.ownership.edit][key]" value="renewalConfig.offline.vahan.ownership.edit">
                            <input class="form-check-input" type="checkbox" name="data[renewalConfig.offline.vahan.ownership.edit][value]" {{ ($allData['renewalConfig.offline.vahan.ownership.edit'] ?? '') == "Y" ? 'checked' : '' }}>
                        </td>
                    </tr>
    
                    <tr>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td>Client Data</td>
                        <td>
                            <input type="hidden" name="data[renewalConfig.offline.clientData.idv.edit][label]" value="Renewal Client Data IDV Editable">
                            <input type="hidden" name="data[renewalConfig.offline.clientData.idv.edit][key]" value="renewalConfig.offline.clientData.idv.edit">
                            <input class="form-check-input" type="checkbox" name="data[renewalConfig.offline.clientData.idv.edit][value]" {{ ($allData['renewalConfig.offline.clientData.idv.edit'] ?? '') == "Y" ? 'checked' : '' }}>
                        </td>
                        <td>
                            <input type="hidden" name="data[renewalConfig.offline.clientData.ncb.edit][label]" value="Renewal Client Data NCB Editable">
                            <input type="hidden" name="data[renewalConfig.offline.clientData.ncb.edit][key]" value="renewalConfig.offline.clientData.ncb.edit">
                            <input class="form-check-input" type="checkbox" name="data[renewalConfig.offline.clientData.ncb.edit][value]" {{ ($allData['renewalConfig.offline.clientData.ncb.edit'] ?? '') == "Y" ? 'checked' : '' }}>
                        </td>

                        <td>
                            <input type="hidden" name="data[renewalConfig.offline.clientData.claim.edit][label]" value="Renewal Client Claim Editable">
                            <input type="hidden" name="data[renewalConfig.offline.clientData.claim.edit][key]" value="renewalConfig.offline.clientData.claim.edit">
                            <input class="form-check-input" type="checkbox" name="data[renewalConfig.offline.clientData.claim.edit][value]" {{ ($allData['renewalConfig.offline.clientData.claim.edit'] ?? '') == "Y" ? 'checked' : '' }}>
                        </td>

                        <td>
                            <input type="hidden" name="data[renewalConfig.offline.clientData.proposal.edit][label]" value="Renewal Client Proposal Editable">
                            <input type="hidden" name="data[renewalConfig.offline.clientData.proposal.edit][key]" value="renewalConfig.offline.clientData.proposal.edit">
                            <input class="form-check-input" type="checkbox" name="data[renewalConfig.offline.clientData.proposal.edit][value]" {{ ($allData['renewalConfig.offline.clientData.proposal.edit'] ?? '') == "Y" ? 'checked' : '' }}>
                        </td>

                        <td>
                            <input type="hidden" name="data[renewalConfig.offline.clientData.ownership.edit][label]" value="Renewal Client Ownership Editable">
                            <input type="hidden" name="data[renewalConfig.offline.clientData.ownership.edit][key]" value="renewalConfig.offline.clientData.ownership.edit">
                            <input class="form-check-input" type="checkbox" name="data[renewalConfig.offline.clientData.ownership.edit][value]" {{ ($allData['renewalConfig.offline.clientData.ownership.edit'] ?? '') == "Y" ? 'checked' : '' }}>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div class="col-sm-12">
                <div class="form-group">
                    <label></label>
                    <div class="d-flex flex-row-reverse">
                        <button type="submit" class="btn btn-outline-primary">Submit</button>
                    </div>
                </div>
            </div>
         </form>
    </div>
</div>