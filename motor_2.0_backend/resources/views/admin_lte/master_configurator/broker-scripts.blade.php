
<style>
    .scrollable-td {
        max-height: 100px; /* Adjust height as needed */
        overflow-y: auto;
        white-space: nowrap;
    }

</style>
@php
    $brokerScripts = $brokerConfigAsset->where('key', 'scripts')->pluck('value')->first();
    
    $priorityList = [
        'start' => 'Start',
        'middle' => 'Middle',
        'end' => 'End',
    ];

    $pageList = [
        'all' => 'All',
        'quote' => 'Quote',
        'proposal' => 'Proposal',
        'thank_you' => 'Thank You',
    ];
@endphp
<div class="row">
    <div class="col-12 grid-margin stretch-card">
        <div class="card">
            <div class="card-body">
            <h4 class="form-tab">Broker Scripts Configurator</h4>
                <!-- <div class="row"> -->
                    
                    <div class="row justify-content-end">
                        <div class="col-1 text-right">
                            <a class="btn btn-primary btn-xs add-script-btn">Add Script</a>
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="existing-scripts-section">
                            @if (!empty($brokerScripts))
                                @php
                                    $s = -1;
                                @endphp
                                @foreach ($brokerScripts as $item)
                                    @php
                                    $s++;
                                    @endphp
                                    <input type="hidden" name="scriptType[{{$s}}]" value="{{$item['type']}}">
                                    <input type="hidden" name="brokerScript[{{$s}}]" value="{{base64_decode($item['scripts'])}}">
                                    <input type="hidden" name="brokerPage[{{$s}}]" value="{{$item['appliedPage']}}">
                                    <input type="hidden" name="scriptPriority[{{$s}}]" value="{{$item['priority']}}">
                                    <input type="hidden" name="brokerScriptAttributes[{{$s}}]" value="{{base64_decode($item['attributes'] ?? null)}}">
                                @endforeach
                            @endif
                        </div>
                        @if (!empty($brokerScripts))
                            <table class="table table-bordered">
                                <thead>
                                    <th>Script</th>
                                    <th>Type</th>
                                    <th>Attributes</th>
                                    <th>Page Applied</th>
                                    <th>Priority</th>
                                    <th>Action</th>
                                </thead>
                                <tbody>
                                    @foreach ($brokerScripts as $key => $item)
                                        <tr>
                                            <td>
                                                {{ Str::limit(base64_decode($item['scripts']), 50) }}
                                                @if (strlen(base64_decode($item['scripts'])) > 50)
                                                    <button class="btn btn-sm btn-link" onclick="showFullScript({{ $key }})">Show More</button>
                                                @endif
                                            </td>
                                            <td>{{$item['type']}}</td>
                                            <td>
                                                @if (!empty($item['attributes']))
                                                    {{ Str::limit(base64_decode($item['attributes']), 50) }}
                                                    @if (strlen(base64_decode($item['attributes'])) > 50)
                                                        <button class="btn btn-sm btn-link" onclick="showFullScript({{ $key }})">Show More</button>
                                                    @endif
                                                @endif
                                            </td>
                                            <td>{{$item['appliedPage']}}</td>
                                            <td>{{$item['priority']}}</td>
                                            <td>
                                                <button class="btn btn-sm btn-success" onclick="editBtnClicked({{$key}})">
                                                    <i class="fa fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" onclick="deleteBtnClicked({{$key}})">
                                                    <i class="fa fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>
                <!-- </div> -->
            </div>
        </div>
    </div>
</div>

<!-- Modal for view scripts -->
<div class="modal fade" id="scriptViewModal" tabindex="-1" aria-labelledby="scriptViewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="scriptViewModalLabel">Script Details</h5>
              
                <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close" >
            <span aria-hidden="true">&times;</span>
          </button>
            </div>
            <div class="modal-body">
                <pre id="scriptContent"></pre>
            </div>
        </div>
    </div>
</div>


{{-- update or create modal --}}
<div class="modal fade" id="scriptsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="scriptsModalLabel">Add Scripts</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close" onclick="closeScriptsModal()">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <form class="scripts-add-form" method="POST">
            <div class="existing-form-data">

            </div>
            <input name="operationType" type="hidden">
            @csrf
            <div class="form-group">
                <label for="scriptType" class="required">Script Type :</label>
                <div class="form-check form-check-inline">
                    <input class="form-check-input scriptType-radio" type="radio" name="scriptType[]" id="scriptType1" value="code">
                    <label class="form-check-label" for="scriptType1">
                        Code
                    </label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input scriptType-radio" type="radio" name="scriptType[]" id="scriptType2" value="url">
                    <label class="form-check-label" for="scriptType2">
                        Url
                    </label>
                </div>
            </div>
            <div class="form-group code-script">
              
            </div>

            <div class="form-group url-script">
                
            </div>

            <div class="form-group url-script-attributes">
                
            </div>

           <div class="form-group">
                <label for="brokerPage" class="required">Apply Page</label>
                <select name="brokerPage[]" id="brokerPage" class="form-control" required>
                    <option value="" disabled selected>choose one</option>
                    @foreach ($pageList as $key => $item)
                    <option value="{{$key}}">{{$item}}</option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label for="scriptPriority" class="required">Script Priority</label>
                <select id="scriptPriority" name="scriptPriority[]" class="form-control" required>
                    <option value="" disabled selected>choose one</option>
                    @foreach ($priorityList as $key => $item)
                        <option value="{{$key}}">{{$item}}</option>
                    @endforeach
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal" onclick="closeScriptsModal()">Close</button>
                <button type="submit" class="btn btn-primary">Submit</button>
              </div>
          </form>
        </div>
      </div>
    </div>
  </div>
  <form method="post" class="delete-script-form d-none">
    @csrf
    <div class="delete-script-form-content">
        
    </div>
  </form>
  <script>
    const scriptCreationUrl = "{{route('admin.config-onboarding.broker-scripts')}}";
    const brokerScripts = @json($brokerScripts);
  </script>

  <script src="{{asset('admin1/js/broker-config/broker-script.js')}}"></script>