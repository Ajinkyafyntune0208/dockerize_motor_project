@extends('layout.app', ['titlePage' => 'Common Configurations'])
@section('content')
    <style>
        select {
            text-align: center;
            text-align-last: center;
            /* webkit*/
        }

        .ml-2 {
            margin-left: 0.5rem;
        }

        option {
            text-align: center;
            /* reset to left*/
        }
        thead, tbody, tfoot, tr, td, th {
            border-width: 1px !important;
        }
        .apiConfigClass td {
            padding: 2px !important;
        }
    </style>
    <div class="content-wrapper">
        <div class="row">
            <div class="col-lg-12 grid-margin">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Common Configurations</h5>
                        @if (session('status') && session('formType') == 'idv')
                            <div class="alert alert-{{ session('class') }}">
                                {{ session('status') }}
                            </div>
                        @endif
                        <form action="{{ route('admin.common-config-save') }}" method="POST" class="mt-3 idvForm" name="add idv" onsubmit="processForm(this.event)">
                            @csrf @method('POST')
                            <input type="hidden" value="idv" name="formType">
                            <div class="row">
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label>IDV setting</label>
                                        <select name="data[idv][value]" id="idv" data-style="btn-sm btn-primary" data-actions-box="true" class="selectpicker w-100" data-live-search="true" onChange=buttonHide()>
                                            <option value="default" {{ ($allData["idv_settings"] ?? "") == 'default' ? 'selected' : '' }} >Default</option>
                                            <option value="min_idv" {{ ($allData["idv_settings"] ?? "") == 'min_idv' ? 'selected' : '' }} >Min IDV</option>
                                            <option value="max_idv" {{ ($allData["idv_settings"] ?? "") == 'max_idv' ? 'selected' : '' }} >Max IDV</option>
                                        </select>
                                    </div>
                                    <input id="label" name="data[idv][label]" type="hidden" value="IDV Setting" class="form-control" placeholder="">
                                    <input id="key" name="data[idv][key]" type="hidden" value="idv_settings" class="form-control" placeholder="">
                                </div>
                                <div class="col-sm-6 text-right">
                                    <div class="form-group">
                                        <label></label>
                                        <div class="d-flex flex-row-reverse">
                                            <button hidden id="search" type="submit" class="btn btn-outline-primary">Submit</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                </br>
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Slack Configurations</h5>
                        @if (session('status') && session('formType') == 'slack')
                            <div class="alert alert-{{ session('class') }}">
                                {{ session('status') }}
                            </div>
                        @endif
                        <form action="{{ route('admin.common-config-save') }}" method="POST" class="mt-3 slackForm" onsubmit="processForm(this)">
                            @csrf @method('POST')
                            <input type="hidden" value="slack" name="formType">
                            <div class="row">
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label class="form-check-label" for="enableSlack">Enable Slack Notifications</label>
                                        <input class="form-check-input" id="enableSlack" type="checkbox" name="data[is_slack_enabled][value]" {{ ($allData['slack.notification.isEnabled'] ?? '') == "Y" ? 'checked' : '' }} onChange=buttonHide()>
                                        <input name="data[is_slack_enabled][label]" type="hidden" value="Enable Slack Notification" class="form-control" placeholder="">
                                        <input name="data[is_slack_enabled][key]" type="hidden" value="slack.notification.isEnabled" class="form-control" placeholder="">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-sm-3">
                                    <div class="form-group">
                                        <label class="form-check-label" for="enableFailedJob">Enable Failed Job Notification ?</label>
                                        <input class="form-check-input" id="enableFailedJob" type="checkbox" name="data[enable_failed_job_notification][value]" {{ ($allData['slack.failedJob.enabled'] ?? '') == 'Y' ? 'checked' : '' }} onChange=buttonHide()>
                                        <input name="data[enable_failed_job_notification][label]" type="hidden" value="Notify Failed Job" class="form-control" placeholder="">
                                        <input name="data[enable_failed_job_notification][key]" type="hidden" value="slack.failedJob.enabled" class="form-control" placeholder="">
                                    </div>
                                </div>
                                <div class="col-sm-3">
                                    <div class="input-group">
                                        <span class="input-group-text">Channel Name :</span>
                                        <input class="form-control" type="text" name="data[failed_job_channel][value]" value="{{ $allData['slack.failedJob.channel.name'] ?? '' }}">
                                        <input name="data[failed_job_channel][label]" type="hidden" value="Failed Job Channel Name" class="form-control" placeholder="">
                                        <input name="data[failed_job_channel][key]" type="hidden" value="slack.failedJob.channel.name" class="form-control" placeholder="">
                                    </div>
                                </div>
                                <div class="col-sm-4">
                                    <div class="input-group">
                                        <span class="input-group-text">Channel Hook URL :</span>
                                        <input class="form-control" type="text" name="data[failed_job_url][value]" value="{{ $allData['slack.failedJob.channel.url'] ?? '' }}">
                                        <input name="data[failed_job_url][label]" type="hidden" value="Notify Failed Channel URL" class="form-control" placeholder="">
                                        <input name="data[failed_job_url][key]" type="hidden" value="slack.failedJob.channel.url" class="form-control" placeholder="">
                                    </div>
                                </div>
                            </div>

                            @if(config('constants.motorConstant.SMS_FOLDER') == 'renewbuy')
                            <div class="row">
                                <div class="col-sm-3">
                                    <div class="form-group">
                                        <label class="form-check-label" for="enableKafkaFailedValidation">Enable Kafka Failed Validation Notification ?</label>
                                        <input class="form-check-input" id="enableKafkaFailedValidation" type="checkbox" name="data[kafka_failed_validation][value]" {{ ($allData['slack.kafkaFailedValidation.enable'] ?? '') == 'Y' ? 'checked' : '' }} onChange=buttonHide()>
                                        <input name="data[kafka_failed_validation][label]" type="hidden" value="Notify Kafka Validation Failed" class="form-control" placeholder="">
                                        <input name="data[kafka_failed_validation][key]" type="hidden" value="slack.kafkaFailedValidation.enable" class="form-control" placeholder="">
                                    </div>
                                </div>
                                <div class="col-sm-3">
                                    <div class="input-group">
                                        <span class="input-group-text">Channel Name :</span>
                                        <input class="form-control" type="text" name="data[kafka_failed_validation_channel][value]" value="{{$allData['slack.kafkaFailedValidation.channel.name'] ?? ''}}">
                                        <input name="data[kafka_failed_validation_channel][label]" type="hidden" value="Kafka Failed Validation Channel Name" class="form-control" placeholder="">
                                        <input name="data[kafka_failed_validation_channel][key]" type="hidden" value="slack.kafkaFailedValidation.channel.name" class="form-control" placeholder="">
                                    </div>
                                </div>
                                <div class="col-sm-4">
                                    <div class="input-group">
                                        <span class="input-group-text">Channel Hook URL :</span>
                                        <input class="form-control" type="text" name="data[kafka_failed_validation_url][value]" value="{{$allData['slack.kafkaFailedValidation.channel.url'] ?? ''}}">
                                        <input name="data[kafka_failed_validation_url][label]" type="hidden" value="Kafka Failed Validation Channel URL" class="form-control" placeholder="">
                                        <input name="data[kafka_failed_validation_url][key]" type="hidden" value="slack.kafkaFailedValidation.channel.url" class="form-control" placeholder="">
                                    </div>
                                </div>
                            </div>
                            @endif

                            <div class="row">
                                <div class="col-sm-3">
                                    <div class="form-group">
                                        <label class="form-check-label" for="enableIcTimeOutNotification">Enable Failed IC Timed-Out Notification ?</label>
                                        <input class="form-check-input" id="enableIcTimeOutNotification" type="checkbox" name="data[IcTimeoutNotification.isEnabled][value]" {{ ($allData['slack.IcTimeoutNotification.isEnabled'] ?? '') == 'Y' ? 'checked' : '' }} onChange=buttonHide()>
                                        <input name="data[IcTimeoutNotification.isEnabled][label]" type="hidden" value="Enable Failed IC Timed-Out Notification" class="form-control" placeholder="">
                                        <input name="data[IcTimeoutNotification.isEnabled][key]" type="hidden" value="slack.IcTimeoutNotification.isEnabled" class="form-control" placeholder="">
                                    </div>
                                </div>
                                <div class="col-sm-3">
                                    <div class="input-group">
                                        <span class="input-group-text">Channel Name :</span>
                                        <input class="form-control" type="text" name="data[IcTimeoutNotification.channel][value]" value="{{ $allData['slack.IcTimeoutNotification.channel.name'] ?? '' }}">
                                        <input name="data[IcTimeoutNotification.channel][label]" type="hidden" value="IC TimedOut Channel Name" class="form-control" placeholder="">
                                        <input name="data[IcTimeoutNotification.channel][key]" type="hidden" value="slack.IcTimeoutNotification.channel.name" class="form-control" placeholder="">
                                    </div>
                                </div>
                                <div class="col-sm-4">
                                    <div class="input-group">
                                        <span class="input-group-text">Channel Hook URL :</span>
                                        <input class="form-control" type="text" name="data[IcTimeoutNotification.url][value]" value="{{ $allData['slack.IcTimeoutNotification.channel.url'] ?? '' }}">
                                        <input name="data[IcTimeoutNotification.url][label]" type="hidden" value="IC TimeOut Channel URL" class="form-control" placeholder="">
                                        <input name="data[IcTimeoutNotification.url][key]" type="hidden" value="slack.IcTimeoutNotification.channel.url" class="form-control" placeholder="">
                                    </div>
                                </div>
                            </div>

                            <div class="col-sm-12">
                                <div class="form-group">
                                    <label></label>
                                    <div class="d-flex flex-row-reverse">
                                        <button hidden id= "slack" type="submit" class="btn btn-outline-primary">Submit</button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                </br>
                <!-- API TimeOut Configuration Start -->
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">API TimeOut Configurations (All value will be considered in seconds)</h5>
                        @if (session('status') && session('formType') == 'apiTimeOut')
                            <div class="alert alert-{{ session('class') }}">
                                {{ session('status') }}
                            </div>
                        @endif
                        <form action="{{ route('admin.common-config-save') }}" method="POST" class="mt-3 apiTimeOutForm" onsubmit="processForm(this)">
                            @csrf @method('POST')
                            <input type="hidden" value="apiTimeOut" name="formType">
                            <div class="row">
                                <div class="col-sm-4">
                                    <div class="input-group">
                                        <span class="input-group-text">Global Quote Page TimeOut :</span>
                                        <input class="form-control" type="number" name="data[global.timeout.quotePage][value]" value="{{ $allData['global.timeout.quotePage'] ?? '' }}">
                                        <input name="data[global.timeout.quotePage][label]" type="hidden" value="Global Quote Page TimeOut" class="form-control" placeholder="">
                                        <input name="data[global.timeout.quotePage][key]" type="hidden" value="global.timeout.quotePage" class="form-control" placeholder="">
                                    </div>
                                </div>
                                <div class="col-sm-4">
                                    <div class="input-group">
                                        <span class="input-group-text">Global Proposal Page TimeOut :</span>
                                        <input class="form-control" type="number" name="data[global.timeout.proposalPage][value]" value="{{ $allData['global.timeout.proposalPage'] ?? '' }}">
                                        <input name="data[global.timeout.proposalPage][label]" type="hidden" value="Global Proposal Page TimeOut" class="form-control" placeholder="">
                                        <input name="data[global.timeout.proposalPage][key]" type="hidden" value="global.timeout.proposalPage" class="form-control" placeholder="">
                                    </div>
                                </div>
                            </div>

                            <div class="row mt-3">
                                <div class="col-sm-4">
                                    <div class="form-group">
                                        <label class="form-check-label" for="enableAutoscaleTimeout">Enable AutoScale Timeout ?</label>
                                        <input class="form-check-input" id="enableAutoscaleTimeout" type="checkbox" name="data[global.timeout.autoScale.isEnabled][value]" {{ ($allData['global.timeout.autoScale.isEnabled'] ?? '') == "Y" ? 'checked' : '' }}>
                                        <input name="data[global.timeout.autoScale.isEnabled][label]" type="hidden" value="Enable AutoScale Timeout" class="form-control" placeholder="">
                                        <input name="data[global.timeout.autoScale.isEnabled][key]" type="hidden" value="global.timeout.autoScale.isEnabled" class="form-control" placeholder="">
                                    </div>
                                </div>

                                <div class="col-sm-4">
                                    <div class="input-group">
                                        <span class="input-group-text">AutoScale TimeOut by :</span>
                                        <input class="form-control" type="number" name="data[global.timeout.autoScaleBy][value]" value="{{ $allData['global.timeout.autoScaleBy'] ?? '' }}">
                                        <input name="data[global.timeout.autoScaleBy][label]" type="hidden" value="AutoScale TimeOut by" class="form-control" placeholder="">
                                        <input name="data[global.timeout.autoScaleBy][key]" type="hidden" value="global.timeout.autoScaleBy" class="form-control" placeholder="">
                                    </div>
                                </div>
                            </div>
                            <div class="">
                                <h6 class="text-center"><u>IC Wise configuration</u></h6>
                                <table class="table table-striped col-sm-6 apiConfigClass">
                                    <thead>
                                        <tr>
                                            <th colspan="3" class="text-center">Quote Page Timeout</th>
                                            <th colspan="3" class="text-center">Proposal Page Timeout</th>
                                        </tr>
                                        <tr>
                                            <th>IC Names</th>
                                            <th>TimeOut</th>
                                            <th>Auto Scaled TimeOut</th>
                                            <th>IC Names</th>
                                            <th>TimeOut</th>
                                            <th>Auto Scaled TimeOut</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php
                                            $auto_scaled_companies = App\Models\ApiTimeoutAutoScale::whereIn('company_alias', $companies->pluck('company'))->get();
                                        @endphp
                                        @foreach($companies as $comp)
                                            @php
                                            $comp_alias = $comp->company;
                                            $short_name = ucwords(str_replace('_', ' ', $comp_alias));
                                            @endphp
                                            <tr>
                                                <td>{{ $loop->iteration . ' - ' . $short_name}}</td>
                                                <input type="hidden" name="data[global.timeout.quotePage.{{$comp_alias}}][label]" value="Quote IC TimeOut {{$short_name}}">
                                                <input type="hidden" name="data[global.timeout.quotePage.{{$comp_alias}}][key]" value="global.timeout.quotePage.{{$comp_alias}}">
                                                <td><input type="number" name="data[global.timeout.quotePage.{{$comp_alias}}][value]" value="{{$allData['global.timeout.quotePage.'.$comp_alias] ?? '' }}"></td>
                                                @php
                                                    $quote_as = $auto_scaled_companies->where('company_alias', $comp_alias)->where('transaction_type', 'quote')->first();
                                                @endphp
                                                @if ($quote_as)
                                                    <td align="center" data-toggle="tooltip" data-html="true" title="URL : {{$quote_as->endpoint_url}}<br>Created At : {{$quote_as->created_at}}">{{$quote_as->timeout}}</td>
                                                @else
                                                    <td align="center">-</td>
                                                @endif
                                                <td>{{ $loop->iteration . ' - ' . $short_name}}</td>
                                                <input type="hidden" name="data[global.timeout.proposalPage.{{$comp_alias}}][label]" value="Proposal IC TimeOut {{$short_name}}">
                                                <input type="hidden" name="data[global.timeout.proposalPage.{{$comp_alias}}][key]" value="global.timeout.proposalPage.{{$comp_alias}}">
                                                <td><input type="number" name="data[global.timeout.proposalPage.{{$comp_alias}}][value]" value="{{$allData['global.timeout.proposalPage.'.$comp_alias] ?? '' }}"></td>
                                                @php
                                                    $proposal_as = $auto_scaled_companies->where('company_alias', $comp_alias)->where('transaction_type', 'proposal')->first();
                                                @endphp
                                                @if ($proposal_as)
                                                    <td align="center" data-toggle="tooltip" data-html="true" title="URL : {{$proposal_as->endpoint_url}}<br>Created At : {{$proposal_as->created_at}}">{{$proposal_as->timeout}}</td>
                                                @else
                                                    <td align="center">-</td>
                                                @endif
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            

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
                <!-- API TimeOut Configuration End -->

                @include('common_configurations.renewal-config')

                @include('common_configurations.loading-config')
                @include('common_configurations.mongo-connection')
            </div>
        </div>
    </div>
@endsection
@push('scripts')
    <script>
        function buttonHide(){
            $('#search').attr('hidden',false);
            $('#slack').attr('hidden',false);
        }
        function processForm(form) {
            var checkboxes = form.querySelectorAll('input[type="checkbox"]');

            for (var i = 0; i < checkboxes.length; i++) {
                var checkbox = checkboxes[i];
                var hiddenInput = document.createElement('input');

                hiddenInput.type = 'hidden';
                hiddenInput.name = checkbox.name;

                hiddenInput.value = checkbox.checked ? 'Y' : 'N';

                form.appendChild(hiddenInput);
            }
        }
        $(function () {
            $('[data-toggle="tooltip"]').tooltip({
                html: true
            });
        })
    </script>
@endpush
